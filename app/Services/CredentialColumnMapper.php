<?php

namespace App\Services;

/**
 * Resuelve, a partir de la fila de encabezados de un archivo importado
 * (CSV/XLSX/XLS), qué columna corresponde a cada campo de la credencial.
 *
 * La comparación es insensible a mayúsculas/minúsculas y tolerante a tildes,
 * porque el archivo puede venir de un Excel en español (con o sin acentos
 * según cómo lo haya tecleado la persona que lo preparó).
 */
class CredentialColumnMapper
{
    /**
     * Cada clave es el campo interno; cada valor es la lista de encabezados
     * ya normalizados (minúsculas, sin tildes, sin espacios repetidos) que
     * se aceptan como sinónimo de ese campo.
     */
    private const SYNONYMS = [
        'password'          => ['clave', 'contrasena', 'password'],
        'email'              => ['correo electronico', 'correo', 'email'],
        'nextcloud_account'  => ['cuenta nextcloud', 'nextcloud'],
        'username'           => ['usuario', 'nombre'],
        'type'               => ['tipo', 'type'],
    ];

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>  campo => índice de columna (0-based)
     */
    public static function resolve(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $rawHeader) {
            $normalized = self::normalize((string) $rawHeader);

            if ($normalized === '') {
                continue;
            }

            foreach (self::SYNONYMS as $field => $synonyms) {
                if (array_key_exists($field, $map)) {
                    continue; // ya resuelto por una columna anterior
                }

                if (in_array($normalized, $synonyms, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    public static function normalize(string $value): string
    {
        // Por si el BOM UTF-8 de Excel quedó pegado al primer encabezado.
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);
        $value = mb_strtolower($value);

        $withoutAccents = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);

        return trim(preg_replace('/\s+/', ' ', $withoutAccents) ?? '');
    }
}
