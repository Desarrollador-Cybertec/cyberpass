<?php

namespace App\Http\Controllers;

use App\Models\SharedAccessToken;
use App\Services\AuditService;
use App\Services\SharedAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class PublicTokenController extends Controller
{
    public function __construct(
        private SharedAccessTokenService $service,
        private AuditService $audit,
    ) {}

    public function info(string $token): JsonResponse
    {
        $record = SharedAccessToken::where('token', $token)->first();

        if (! $record || ! $record->isValid()) {
            return response()->json(['message' => 'Token inválido o expirado.'], 404);
        }

        return response()->json($this->service->info($record));
    }

    public function claim(Request $request, string $token): JsonResponse
    {
        $record = SharedAccessToken::where('token', $token)->first();

        if (! $record || ! $record->isValid()) {
            return response()->json(['message' => 'Token inválido o expirado.'], 404);
        }

        $orgId = $record->credential->organization_id;

        if ($record->requiresPin()) {
            $lockoutKey = "pin_attempts_{$token}";
            $attempts = Cache::get($lockoutKey, 0);

            if ($attempts >= 5) {
                return response()->json(['message' => 'Token bloqueado por múltiples intentos fallidos. Intenta en 15 minutos.'], 429);
            }

            $pin = $request->input('pin');

            if (! $pin || ! Hash::check($pin, $record->pin_hash)) {
                Cache::put($lockoutKey, $attempts + 1, now()->addMinutes(15));

                $this->audit->log(null, 'claim_failed', SharedAccessToken::class, $record->id, [
                    'reason'        => 'invalid_pin',
                    'credential_id' => $record->credential_id,
                ], $orgId);

                return response()->json(['message' => 'PIN incorrecto.'], 403);
            }

            Cache::forget($lockoutKey);
        }

        $payload = $this->service->claim($record, $request->input('pin'));

        $this->audit->log(null, 'claim', SharedAccessToken::class, $record->id, [
            'credential_id' => $record->credential_id,
        ], $orgId);

        return response()->json($payload);
    }
}
