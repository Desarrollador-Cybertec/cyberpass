<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        abort_if(! $user || $user->is_active, 422, 'Invitación inválida o ya aceptada.');

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        abort_if(! $record, 422, 'Token de invitación no encontrado.');

        abort_if(! Hash::check($data['token'], $record->token), 422, 'Token de invitación inválido.');

        $createdAt = \Carbon\Carbon::parse($record->created_at);
        abort_if($createdAt->addDays(7)->isPast(), 422, 'El token de invitación ha expirado.');

        $user->update([
            'password'  => Hash::make($data['password']),
            'is_active' => true,
        ]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        $this->audit->log($user, 'invite_accepted', User::class, $user->id);

        return response()->json(['message' => 'Cuenta activada correctamente. Ya puedes iniciar sesión.']);
    }
}
