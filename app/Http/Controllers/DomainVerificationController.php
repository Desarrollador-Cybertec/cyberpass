<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Services\AuditService;
use App\Services\DomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainVerificationController extends Controller
{
    public function __construct(
        private DomainVerificationService $verifier,
        private AuditService $audit,
    ) {}

    public function initiate(Request $request, Organization $organization, OrganizationDomain $domain): JsonResponse
    {
        $this->authorize('manageDomains', $organization);
        abort_if($domain->organization_id !== $organization->id, 404);

        $txtRecord = $this->verifier->initiate($domain);

        return response()->json([
            'txt_record'   => $txtRecord,
            'instructions' => "Add a TXT record to your DNS for '{$domain->domain}' with the value above. Then call the confirm endpoint. The token expires in 72 hours.",
        ]);
    }

    public function confirm(Request $request, Organization $organization, OrganizationDomain $domain): JsonResponse
    {
        $this->authorize('manageDomains', $organization);
        abort_if($domain->organization_id !== $organization->id, 404);

        if ($domain->is_verified) {
            return response()->json(['message' => 'El dominio ya está verificado.']);
        }

        $verified = $this->verifier->confirm($domain);

        if (! $verified) {
            return response()->json([
                'message' => 'TXT record not found or token has expired. Verify your DNS configuration and try again.',
            ], 422);
        }

        $this->audit->log($request->user(), 'update', OrganizationDomain::class, $domain->id, [
            'action' => 'domain_verified',
        ]);

        return response()->json(['message' => "Dominio '{$domain->domain}' verificado correctamente."]);
    }
}
