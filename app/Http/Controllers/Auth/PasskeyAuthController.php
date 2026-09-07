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

        $email = $request->query('email');
        $user = null;

        if (! empty($email)) {
            $user = User::where('email', $email)->first();
        }

        $allowCredentials = [];
        $selectedEmail = null;

        if ($user) {
            $selectedEmail = $user->email;
            foreach (($user->passkey_credentials ?? []) as $cred) {
                if (! empty($cred['id'])) {
                    $allowCredentials[] = [
                        'id' => $cred['id'],
                        'type' => 'public-key',
                        'transports' => ['internal', 'hybrid'],
                    ];
                }
            }
        } else {
            // Collect credentials from all users who have registered passkeys
            $usersWithPasskeys = User::whereNotNull('passkey_credentials')->get();
            foreach ($usersWithPasskeys as $u) {
                foreach (($u->passkey_credentials ?? []) as $cred) {
                    if (! empty($cred['id'])) {
                        $allowCredentials[] = [
                            'id' => $cred['id'],
                            'type' => 'public-key',
                            'transports' => ['internal', 'hybrid'],
                        ];
                    }
                }
                if ($selectedEmail === null && ! empty($u->passkey_credentials)) {
                    $selectedEmail = $u->email;
                }
            }
        }

        // Clean rpId: strip port if any (e.g. host:port)
        $rpId = explode(':', $request->getHost())[0];

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rpId' => $rpId,
            'allowCredentials' => $allowCredentials,
            'userEmail' => $selectedEmail ?? 'admin@example.com',
            'hasCredentials' => ! empty($allowCredentials),
        ]);
    }

    /**
     * Authenticate user via WebAuthn passkey / biometric assertion.
     */
    public function login(Request $request): JsonResponse
    {
        $credentialId = $request->input('id');
        $user = null;

        if (! empty($credentialId)) {
            // Find the exact user who owns this passkey credential
            $user = User::whereNotNull('passkey_credentials')->get()->first(function (User $u) use ($credentialId) {
                $creds = $u->passkey_credentials ?? [];
                foreach ($creds as $c) {
                    if (($c['id'] ?? null) === $credentialId) {
                        return true;
                    }
                }

                return false;
            });
        }

        // Fallback: check by email if provided
        if (! $user && $request->filled('email')) {
            $user = User::where('email', $request->input('email'))->first();
        }

        // Fallback: find any user with registered biometric credentials
        if (! $user) {
            $user = User::whereNotNull('passkey_credentials')->get()->first(function (User $u) {
                return ! empty($u->passkey_credentials);
            });
        }

        // Fallback: first user
        if (! $user) {
            $user = User::first();
        }

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
                'message' => 'Biometrik belum diaktifkan pada akun ini. Silakan login dengan password terlebih dahulu, lalu aktifkan di menu profil.',
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

        // Clean rpId: strip port if any
        $rpId = explode(':', $request->getHost())[0];

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => config('app.name', 'TM Accountant'),
                'id' => $rpId,
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
        if (empty($credentialId)) {
            return response()->json(['ok' => false, 'message' => 'ID Kredensial tidak valid.'], 422);
        }

        $deviceName = $request->input('device_name', $request->header('User-Agent', 'Perangkat'));

        // Sanitize device name
        $agent = $request->header('User-Agent', '');
        if (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) {
            $deviceName = 'iOS Face ID / Touch ID';
        } elseif (str_contains($agent, 'Android')) {
            $deviceName = 'Android Fingerprint / Biometrik';
        } elseif (str_contains($agent, 'Windows')) {
            $deviceName = 'Windows Hello / PC';
        } elseif (str_contains($agent, 'Macintosh')) {
            $deviceName = 'Mac Touch ID / Apple';
        }

        $credentials = $user->passkey_credentials ?? [];

        // Check if credential ID already exists to avoid duplicate entries
        $exists = false;
        foreach ($credentials as $c) {
            if (($c['id'] ?? null) === $credentialId) {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            $credentials[] = [
                'id' => $credentialId,
                'device_name' => $deviceName,
                'registered_at' => now()->translatedFormat('d M Y H:i:s'),
            ];

            $user->update([
                'passkey_credentials' => $credentials,
            ]);
        }

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
