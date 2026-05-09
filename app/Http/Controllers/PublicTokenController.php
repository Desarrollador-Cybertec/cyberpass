<?php

namespace App\Http\Controllers;

use App\Models\SharedAccessToken;
use App\Services\SharedAccessTokenService;
use Illuminate\Http\JsonResponse;

class PublicTokenController extends Controller
{
    public function __construct(private SharedAccessTokenService $service) {}

    public function consume(string $token): JsonResponse
    {
        $record = SharedAccessToken::where('token', $token)->first();

        if (! $record || ! $record->isValid()) {
            return response()->json(['message' => 'Token inválido o expirado.'], 404);
        }

        $payload = $this->service->consume($record);

        return response()->json($payload);
    }
}
