<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $invitation = OrganizationInvitation::where('email', $data['email'])->first();

        abort_if(! $invitation, 422, 'Invitación no encontrada.');
        abort_if(! Hash::check($data['token'], $invitation->token_hash), 422, 'Token de invitación inválido.');
        abort_if($invitation->isExpired(), 422, 'El token de invitación ha expirado.');

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->is_active && $user->account_type === 'personal') {
            // Existing personal user joining an org — no password change needed
            $user->update([
                'organization_id' => $invitation->organization_id,
                'account_type'    => 'enterprise',
                'role'            => $invitation->role,
            ]);

            $invitation->delete();

            $this->audit->log($user, 'invite_accepted', User::class, $user->id);

            return response()->json(['message' => 'Te has unido a la organización correctamente.']);
        }

        if ($user && $user->account_type === 'enterprise') {
            abort(422, 'Ya perteneces a una organización.');
        }

        // New user — must provide password
        abort_if(! $user || $user->is_active, 422, 'Invitación inválida o ya aceptada.');

        $user->update([
            'password'        => Hash::make($data['password']),
            'organization_id' => $invitation->organization_id,
            'role'            => $invitation->role,
            'is_active'       => true,
        ]);

        $invitation->delete();

        $this->audit->log($user, 'invite_accepted', User::class, $user->id);

        return response()->json(['message' => 'Cuenta activada correctamente. Ya puedes iniciar sesión.']);
    }
}
