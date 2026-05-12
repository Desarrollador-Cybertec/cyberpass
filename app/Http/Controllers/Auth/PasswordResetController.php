<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->upsert(
                ['email' => $user->email, 'token' => Hash::make($token), 'created_at' => Carbon::now()],
                ['email'],
            );

            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        }

        // Always 200 — do not reveal whether the email exists
        return response()->json(['message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        abort_if(! $user, 422, 'No se pudo restablecer la contraseña.');

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        abort_if(! $record, 422, 'Token inválido o expirado.');
        abort_if(! Hash::check($data['token'], $record->token), 422, 'Token inválido o expirado.');

        $createdAt = Carbon::parse($record->created_at);
        abort_if($createdAt->addMinutes(60)->isPast(), 422, 'El token ha expirado. Solicita uno nuevo.');

        $user->update(['password' => Hash::make($data['password'])]);

        // Invalidate all Sanctum tokens
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        $this->audit->log($user, 'password_reset', User::class, $user->id);

        return response()->json(['message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.']);
    }
}
