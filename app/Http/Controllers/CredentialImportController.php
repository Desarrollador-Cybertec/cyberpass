<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\ImportCredentialsCommitRequest;
use App\Http\Requests\Asset\ImportCredentialsPreviewRequest;
use App\Models\Category;
use App\Services\AuditService;
use App\Services\CredentialImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CredentialImportController extends Controller
{
    // No confiamos en el mime del navegador (Laravel `mimes:` es poco
    // fiable para .xls entre distintos navegadores): validamos la
    // extensión real del archivo, en minúsculas.
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    public function __construct(
        private CredentialImportService $importer,
        private AuditService $audit,
    ) {}

    public function preview(ImportCredentialsPreviewRequest $request, Category $category): JsonResponse
    {
        $file = $request->file('file');

        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => ['El archivo debe tener extensión .csv, .xlsx o .xls.'],
            ]);
        }

        $result = $this->importer->parseAndValidate($file, $category, $request->user()->id);

        return response()->json($result);
    }

    public function commit(ImportCredentialsCommitRequest $request, Category $category): JsonResponse
    {
        try {
            $result = $this->importer->commit(
                $request->validated()['import_token'],
                $request->user()->id,
                $category->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request->user(), 'import', Category::class, $category->id, [
            'category_id'   => $category->id,
            'created_count' => $result['created_count'],
            'skipped_count' => $result['skipped_count'],
        ]);

        return response()->json($result);
    }
}
