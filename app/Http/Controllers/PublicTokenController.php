<?php

namespace App\Http\Controllers;

use App\Models\SharedAccessToken;
use App\Services\SharedAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PublicTokenController extends Controller
{
    public function __construct(private SharedAccessTokenService $service) {}

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

        if ($record->requiresPin()) {
            $pin = $request->input('pin');

            if (! $pin || ! Hash::check($pin, $record->pin_hash)) {
                return response()->json(['message' => 'PIN incorrecto.'], 403);
            }
        }

        return response()->json($this->service->claim($record, $request->input('pin')));
    }
}
