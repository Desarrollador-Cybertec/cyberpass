<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AuditService;
use App\Services\CredentialExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CredentialExportController extends Controller
{
    public function __construct(
        private CredentialExportService $exporter,
        private AuditService $audit,
    ) {}

    public function export(Request $request, Organization $organization): StreamedResponse
    {
        $user = $request->user();

        abort_if(
            ! in_array($user->role, ['sysadmin', 'org_admin']),
            403,
            'No tienes permiso para exportar credenciales.',
        );

        if ($user->role === 'org_admin') {
            abort_if($user->organization_id !== $organization->id, 403);
        }

        $this->audit->log($user, 'export', Organization::class, $organization->id, [
            'format' => 'csv',
        ]);

        $filename = 'credentials-' . $organization->id . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($organization) {
            foreach ($this->exporter->toCsv($organization) as $chunk) {
                echo $chunk;
                ob_flush();
                flush();
            }
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
