<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $qrUri = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrSvg = $this->generateQrSvg($qrUri);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $qrSvg,
            'qr_uri' => $qrUri,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Primero ejecuta el setup de 2FA.'], 422);
        }

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $data['otp']);

        if (! $valid) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 422);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->audit->log($user, '2fa_enabled');

        return response()->json(['message' => '2FA activado correctamente.']);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $data['otp']);

        if (! $valid) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->audit->log($user, '2fa_disabled');

        return response()->json(['message' => '2FA desactivado correctamente.']);
    }

    private function generateQrSvg(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($uri);
    }
}
