<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSharedTokenRequest;
use App\Http\Resources\SharedAccessTokenResource;
use App\Models\Credential;
use App\Models\SharedAccessToken;
use App\Services\SharedAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SharedAccessTokenController extends Controller
{
    public function __construct(private SharedAccessTokenService $service) {}

    public function index(Request $request, Credential $credential): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [SharedAccessToken::class, $credential]);

        $tokens = $credential->sharedTokens()
            ->latest()
            ->paginate(50);

        return SharedAccessTokenResource::collection($tokens);
    }

    public function store(CreateSharedTokenRequest $request, Credential $credential): JsonResponse
    {
        $token = $this->service->create($credential, $request->user(), $request->validated());

        return response()->json(new SharedAccessTokenResource($token), 201);
    }

    public function revoke(Request $request, Credential $credential, SharedAccessToken $token): JsonResponse
    {
        abort_if($token->credential_id !== $credential->id, 404);

        $this->authorize('revoke', $token);

        $this->service->revoke($token);

        return response()->json(['message' => 'Token revocado.']);
    }
}
