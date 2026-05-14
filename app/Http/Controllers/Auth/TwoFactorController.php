<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditService $audit,
        private AuthService $auth,
    ) {}

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return $this->setupPendingRegistration($request);
        }

        $secret = $this->google2fa->generateSecretKey();

        // Almacenar en caché temporalmente hasta que el usuario confirme con OTP.
        // No se persiste en DB hasta que enable() valide el código correctamente.
        Cache::put("2fa_pending_setup_{$user->id}", $secret, now()->addMinutes(10));

        $qrUri = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return response()->json([
            'secret'     => $secret,
            'qr_data_url' => $this->renderQrPng($qrUri),
            'qr_uri'     => $qrUri,
        ]);
    }

    public function enable(EnableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return $this->enablePendingRegistration($request);
        }

        $pendingSecret = Cache::get("2fa_pending_setup_{$user->id}");

        if (! $pendingSecret) {
            return response()->json(['message' => 'El setup de 2FA expiró o no fue iniciado. Vuelve a ejecutar el setup.'], 422);
        }

        $this->validateOtp($pendingSecret, $request->validated('otp'), $user);

        // Solo ahora se persiste en DB, confirmando que el usuario tiene acceso al autenticador
        $user->forceFill([
            'two_factor_secret'       => $pendingSecret,
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        Cache::forget("2fa_pending_setup_{$user->id}");

        $this->audit->log($user, '2fa_enabled');

        return response()->json(['message' => '2FA activado correctamente.']);
    }

    public function disable(EnableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $this->validateOtp($user->two_factor_secret, $request->validated('otp'), $user);

        $user->update([
            'two_factor_secret'       => null,
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->audit->log($user, '2fa_disabled');

        return response()->json(['message' => '2FA desactivado correctamente.']);
    }

    private function validateOtp(string $secret, string $otp, ?object $user = null): void
    {
        if (! $this->google2fa->verifyKey($secret, $otp)) {
            if ($user instanceof \App\Models\User) {
                $this->audit->log($user, '2fa_otp_failed');
            }
            throw ValidationException::withMessages([
                'otp' => ['Código OTP incorrecto.'],
            ]);
        }
    }

    private function setupPendingRegistration(Request $request): JsonResponse
    {
        $registrationToken = $this->resolveRegistrationToken($request);
        $pendingRegistration = $this->auth->getPendingRegistration($registrationToken);

        $secret = $pendingRegistration['two_factor_secret'] ?? null;

        if (! $secret) {
            $secret = $this->google2fa->generateSecretKey();
            $pendingRegistration = $this->auth->rememberPendingRegistrationSecret($registrationToken, $secret);
        }

        $qrUri = $this->google2fa->getQRCodeUrl(config('app.name'), $pendingRegistration['email'], $secret);

        return response()->json([
            'secret'      => $secret,
            'qr_data_url' => $this->renderQrPng($qrUri),
            'qr_uri'      => $qrUri,
            'user'        => $this->auth->pendingRegistrationProfile($pendingRegistration),
        ]);
    }

    private function enablePendingRegistration(EnableTwoFactorRequest $request): JsonResponse
    {
        $registrationToken = $this->resolveRegistrationToken($request);
        $pendingRegistration = $this->auth->getPendingRegistration($registrationToken);
        $pendingSecret = $pendingRegistration['two_factor_secret'] ?? null;

        if (! $pendingSecret) {
            return response()->json([
                'message' => 'El setup de 2FA expiró o no fue iniciado. Vuelve a ejecutar el setup.',
            ], 422);
        }

        $this->validateOtp($pendingSecret, $request->validated('otp'));

        $result = $this->auth->completePendingRegistration($registrationToken);

        $this->audit->log($result['user'], '2fa_enabled');
        $this->audit->log($result['user'], 'login');

        return response()->json([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ], 201)
            ->cookie($this->makeAccessTokenCookie($result['token']))
            ->withoutCookie('registration_token');
    }

    private function resolveRegistrationToken(Request $request): string
    {
        $registrationToken = $request->input('registration_token')
            ?? $request->cookie('registration_token');

        if (! is_string($registrationToken) || $registrationToken === '') {
            throw ValidationException::withMessages([
                'registration_token' => ['No hay un registro pendiente válido para configurar 2FA.'],
            ]);
        }

        return $registrationToken;
    }

    private function makeAccessTokenCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            'access_token',
            $token,
            60 * 24 * 7,
            '/',
            null,
            true,
            true,
            false,
            'Strict'
        );
    }

    private function renderQrPng(string $uri): string
    {
        if (extension_loaded('imagick')) {
            $backend = new ImagickImageBackEnd;
        } else {
            // fallback: SVG wrapped en data URL (sin innerHTML en el frontend)
            $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd);
            $svg = (new Writer($renderer))->writeString($uri);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        $renderer = new ImageRenderer(new RendererStyle(200), $backend);
        $png = (new Writer($renderer))->writeString($uri);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
