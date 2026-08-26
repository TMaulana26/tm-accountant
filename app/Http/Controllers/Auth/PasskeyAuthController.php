<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PasskeyAuthController extends Controller
{
    /**
     * Generate options for WebAuthn login assertion.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        $challenge = Str::random(32);
        session(['passkey_login_challenge' => $challenge]);

        $user = User::first();
        $credentials = $user?->passkey_credentials ?? [];

        $allowCredentials = [];
        foreach ($credentials as $cred) {
            if (! empty($cred['id'])) {
                $allowCredentials[] = [
                    'id' => $cred['id'],
                    'type' => 'public-key',
                    'transports' => ['internal', 'hybrid'],
                ];
            }
        }

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rpId' => $request->getHost(),
            'allowCredentials' => $allowCredentials,
            'userEmail' => $user?->email ?? 'admin@example.com',
            'hasCredentials' => ! empty($allowCredentials),
        ]);
    }

    /**
     * Authenticate user via WebAuthn passkey / biometric assertion.
     */
    public function login(Request $request): JsonResponse
    {
        $user = User::first();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        // Verify that user has registered at least 1 biometric credential
        $credentials = $user->passkey_credentials ?? [];
        if (empty($credentials)) {
            return response()->json([
                'ok' => false,
                'message' => 'Biometrik belum diaktifkan pada akun ini. Silakan login dengan password terlebih dahulu.',
            ], 400);
        }

        // Login user
        Auth::login($user, true);
        session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('filament.admin.pages.dashboard'),
        ]);
    }

    /**
     * Generate options for registering a new biometric passkey credential.
     */
    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $challenge = Str::random(32);
        session(['passkey_register_challenge' => $challenge]);

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => config('app.name', 'TM Accountant'),
                'id' => $request->getHost(),
            ],
            'user' => [
                'id' => base64_encode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['alg' => -7, 'type' => 'public-key'], // ES256
                ['alg' => -257, 'type' => 'public-key'], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred',
                'residentKey' => 'preferred',
            ],
            'timeout' => 60000,
        ]);
    }

    /**
     * Store new biometric passkey credential.
     */
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $credentialId = $request->input('id');
        $deviceName = $request->input('device_name', $request->header('User-Agent', 'Perangkat'));

        // Sanitize device name
        $agent = $request->header('User-Agent', '');
        if (str_contains($agent, 'Windows')) {
            $deviceName = 'Windows Hello / PC';
        } elseif (str_contains($agent, 'Macintosh')) {
            $deviceName = 'Mac Touch ID / Apple';
        } elseif (str_contains($agent, 'Android')) {
            $deviceName = 'Android Fingerprint';
        } elseif (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) {
            $deviceName = 'iOS Face ID / Touch ID';
        }

        $credentials = $user->passkey_credentials ?? [];
        $credentials[] = [
            'id' => $credentialId,
            'device_name' => $deviceName,
            'registered_at' => now()->toDateTimeString(),
        ];

        $user->update([
            'passkey_credentials' => $credentials,
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Perangkat biometrik ({$deviceName}) berhasil didaftarkan!",
            'credentials_count' => count($credentials),
        ]);
    }

    /**
     * Clear all registered biometric credentials.
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $user->update([
            'passkey_credentials' => [],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Semua kredensial biometrik berhasil dihapus.',
        ]);
    }
}
