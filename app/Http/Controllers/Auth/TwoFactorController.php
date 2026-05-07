<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Services\AuditService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditService $audit,
    ) {}

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        $user->update(['two_factor_secret' => $secret]);

        $qrUri = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->renderQrSvg($qrUri),
            'qr_uri' => $qrUri,
        ]);
    }

    public function enable(EnableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Primero ejecuta el setup de 2FA.'], 422);
        }

        $this->validateOtp($user->two_factor_secret, $request->validated('otp'));

        $user->update([
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->audit->log($user, '2fa_enabled');

        return response()->json(['message' => '2FA activado correctamente.']);
    }

    public function disable(EnableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->validateOtp($user->two_factor_secret, $request->validated('otp'));

        $user->update([
            'two_factor_secret'       => null,
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->audit->log($user, '2fa_disabled');

        return response()->json(['message' => '2FA desactivado correctamente.']);
    }

    private function validateOtp(string $secret, string $otp): void
    {
        if (! $this->google2fa->verifyKey($secret, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Código OTP incorrecto.'],
            ]);
        }
    }

    private function renderQrSvg(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($uri);
    }
}
