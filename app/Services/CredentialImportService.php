<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Credential;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use RuntimeException;

/**
 * Importación masiva de credenciales desde un archivo CSV/XLSX/XLS.
 *
 * Contrato de dos pasos:
 *  - parseAndValidate() lee el archivo, cifra las contraseñas válidas de
 *    inmediato (el texto plano nunca se persiste ni se cachea) y deja el
 *    resultado en caché bajo un token de un solo uso.
 *  - commit() recibe ese token, confirma que pertenece al mismo usuario y
 *    categoría, y crea en bloque las credenciales válidas.
 */
class CredentialImportService
{
    private const CACHE_PREFIX = 'credential_import:';

    private const TTL_MINUTES = 15;

    private const KNOWN_TYPES = [
        'password', 'api_key', 'ssh', 'certificate', 'other',
        'email', 'nextcloud', 'email_nextcloud',
    ];

    public function __construct(private EncryptionService $encryption) {}

    public function parseAndValidate(UploadedFile $file, Category $category, int $userId): array
    {
        $rows = $this->readRows($file);
        $header = array_shift($rows) ?? [];

        if (isset($header[0])) {
            $header[0] = str_replace("\xEF\xBB\xBF", '', (string) $header[0]);
        }

        $columns = CredentialColumnMapper::resolve($header);

        $preview = [];
        $cachedRows = [];
        $validCount = 0;
        $skipCount = 0;

        // Firmas ya aceptadas dentro de este mismo archivo, para no crear dos
        // veces la misma credencial cuando el propio archivo trae la fila
        // repetida (además de comparar contra lo que ya existe en BD).
        $acceptedInFile = [];

        $rowNumber = 1; // la fila 1 es el encabezado

        foreach ($rows as $rawRow) {
            $rowNumber++;

            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            $username  = $this->cellValue($rawRow, $columns['username'] ?? null);
            $email     = $this->cellValue($rawRow, $columns['email'] ?? null);
            $nextcloud = $this->cellValue($rawRow, $columns['nextcloud_account'] ?? null);
            $password  = $this->cellValue($rawRow, $columns['password'] ?? null);
            $rawType   = $this->cellValue($rawRow, $columns['type'] ?? null);

            $type = $this->resolveType($rawType, $email, $nextcloud);
            $name = $username ?: ($email ?: ($nextcloud ?: "Credencial importada (fila {$rowNumber})"));

            $hasPassword = $password !== null && $password !== '';
            $valid       = $hasPassword;
            $skipReason  = null;

            if (! $hasPassword) {
                $skipReason = 'Sin contraseña';
            } else {
                $identity = $this->presentIdentityFields($username, $email, $nextcloud);

                if ($identity !== [] && (
                    $this->matchesAny($identity, $acceptedInFile)
                    || $this->existsInCategory($category, $identity)
                )) {
                    $valid      = false;
                    $skipReason = 'Ya existe una credencial similar en esta categoría';
                }
            }

            $encryptedPassword = null;
            $iv                = null;

            if ($valid) {
                ['ciphertext' => $encryptedPassword, 'iv' => $iv] = $this->encryption->encrypt($password);
                $acceptedInFile[] = $this->presentIdentityFields($username, $email, $nextcloud);
                $validCount++;
            } else {
                $skipCount++;
            }

            $preview[] = [
                'row_number'        => $rowNumber,
                'name'              => $name,
                'username'          => $username,
                'email'             => $email,
                'nextcloud_account' => $nextcloud,
                'type'              => $type,
                'valid'             => $valid,
                'skip_reason'       => $skipReason,
                'has_password'      => $hasPassword,
            ];

            $cachedRows[] = [
                'row_number'         => $rowNumber,
                'name'               => $name,
                'username'           => $username,
                'email'              => $email,
                'nextcloud_account'  => $nextcloud,
                'type'               => $type,
                'valid'              => $valid,
                'skip_reason'        => $skipReason,
                'encrypted_password' => $encryptedPassword,
                'iv'                 => $iv,
            ];
        }

        $token = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id'     => $userId,
            'category_id' => $category->id,
            'rows'        => $cachedRows,
        ], now()->addMinutes(self::TTL_MINUTES));

        return [
            'import_token' => $token,
            'total_rows'   => $validCount + $skipCount,
            'valid_count'  => $validCount,
            'skip_count'   => $skipCount,
            'preview'      => $preview,
        ];
    }

    public function commit(string $token, int $userId, int $categoryId): array
    {
        $key  = self::CACHE_PREFIX.$token;
        $data = Cache::get($key);

        if (! is_array($data)
            || (int) ($data['user_id'] ?? 0) !== $userId
            || (int) ($data['category_id'] ?? 0) !== $categoryId
        ) {
            throw new RuntimeException('El token de importación no es válido o ya expiró.');
        }

        // Uso único: se borra apenas se valida, para que un commit repetido
        // (doble clic, reintento de red) no vuelva a crear las credenciales.
        Cache::forget($key);

        $category = Category::find($categoryId);

        if (! $category) {
            throw new RuntimeException('La categoría de destino ya no existe.');
        }

        $createdCount = 0;
        $skipped      = [];

        foreach ($data['rows'] as $row) {
            if (! $row['valid']) {
                $skipped[] = [
                    'row_number' => $row['row_number'],
                    'reason'     => $row['skip_reason'],
                ];

                continue;
            }

            Credential::create([
                'category_id'        => $category->id,
                'organization_id'    => $category->organization_id,
                'created_by'         => $userId,
                'name'               => $row['name'],
                'username'           => $row['username'],
                'email'              => $row['email'],
                'nextcloud_account'  => $row['nextcloud_account'],
                'encrypted_password' => $row['encrypted_password'],
                'iv'                 => $row['iv'],
                'type'               => $row['type'],
            ]);

            $createdCount++;
        }

        return [
            'created_count' => $createdCount,
            'skipped_count' => count($skipped),
            'skipped'       => $skipped,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $path   = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);

        if ($reader instanceof CsvReader) {
            $delimiter = $this->detectCsvDelimiter($path);

            if ($delimiter !== null) {
                $reader->setDelimiter($delimiter);
            }

            $reader->setInputEncoding('UTF-8');
        }

        $spreadsheet = $reader->load($path);
        $sheet       = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    /**
     * Excel en español exporta CSV con ';' porque la coma ya es el separador
     * decimal de esa configuración regional. Contamos ocurrencias en la
     * primera línea cruda del archivo para decidir el delimitador; si no
     * encontramos ninguno de los dos, dejamos que la librería lo infiera.
     */
    private function detectCsvDelimiter(string $path): ?string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return null;
        }

        $firstLine = str_replace("\xEF\xBB\xBF", '', $firstLine);

        $semicolons = substr_count($firstLine, ';');
        $commas     = substr_count($firstLine, ',');

        if ($semicolons === 0 && $commas === 0) {
            return null;
        }

        return $semicolons >= $commas ? ';' : ',';
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellValue(array $row, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $value = trim((string) $row[$index]);

        return $value === '' ? null : $value;
    }

    private function resolveType(?string $rawType, ?string $email, ?string $nextcloud): string
    {
        if ($rawType !== null) {
            $normalized = CredentialColumnMapper::normalize($rawType);

            foreach (self::KNOWN_TYPES as $knownType) {
                if ($normalized === $knownType) {
                    return $knownType;
                }
            }
            // Si no coincide con ningún tipo conocido, se ignora y se
            // autodetecta según correo/cuenta Nextcloud (ver abajo).
        }

        if ($email !== null && $nextcloud !== null) {
            return 'email_nextcloud';
        }

        if ($email !== null) {
            return 'email';
        }

        if ($nextcloud !== null) {
            return 'nextcloud';
        }

        return 'password';
    }

    /**
     * Solo incluye en la firma los campos que la fila realmente trae, tal
     * como pide la especificación: username/email/nextcloud_account
     * ausentes no participan en la comparación de duplicados.
     *
     * @return array<string, string>
     */
    private function presentIdentityFields(?string $username, ?string $email, ?string $nextcloud): array
    {
        $fields = [];

        if ($username !== null) {
            $fields['username'] = Str::lower(trim($username));
        }

        if ($email !== null) {
            $fields['email'] = Str::lower(trim($email));
        }

        if ($nextcloud !== null) {
            $fields['nextcloud_account'] = Str::lower(trim($nextcloud));
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $identity
     * @param  array<int, array<string, string>>  $candidates
     */
    private function matchesAny(array $identity, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($candidate === $identity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $identity
     */
    private function existsInCategory(Category $category, array $identity): bool
    {
        $allowedFields = ['username', 'email', 'nextcloud_account'];
        $query         = $category->credentials();

        foreach ($identity as $field => $value) {
            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            $query->whereRaw('LOWER(TRIM('.$field.')) = ?', [$value]);
        }

        return $query->exists();
    }
}
