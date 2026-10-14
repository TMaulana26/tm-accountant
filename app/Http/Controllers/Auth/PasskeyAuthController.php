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
        if (! is_string($credentialId) || trim($credentialId) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'ID kredensial biometrik wajib disertakan.',
            ], 422);
        }

        $expectedChallenge = session()->pull('passkey_login_challenge');
        if (! is_string($expectedChallenge) || $expectedChallenge === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Sesi tantangan biometrik tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.',
            ], 401);
        }

        if ($request->filled('clientDataJSON')) {
            if (! $this->verifyClientDataChallenge((string) $request->input('clientDataJSON'), $expectedChallenge, 'webauthn.get')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Verifikasi tantangan WebAuthn gagal.',
                ], 401);
            }
        }

        $matchedCredential = null;
        $user = User::whereNotNull('passkey_credentials')->get()->first(function (User $u) use ($credentialId, &$matchedCredential) {
            $creds = $u->passkey_credentials ?? [];
            foreach ($creds as $c) {
                if (isset($c['id']) && is_string($c['id']) && hash_equals($c['id'], $credentialId)) {
                    $matchedCredential = $c;

                    return true;
                }
            }

            return false;
        });

        if (! $user || ! $matchedCredential) {
            return response()->json([
                'ok' => false,
                'message' => 'Kredensial biometrik tidak dikenali atau tidak terdaftar.',
            ], 401);
        }

        if (! empty($matchedCredential['public_key'])) {
            $authenticatorData = (string) $request->input('authenticatorData', '');
            $clientDataJson = (string) $request->input('clientDataJSON', '');
            $signature = (string) $request->input('signature', '');

            if ($authenticatorData === '' || $clientDataJson === '' || $signature === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Payload tanda tangan kriptografi WebAuthn tidak lengkap.',
                ], 401);
            }

            if (! $this->verifyAssertionSignature(
                $matchedCredential['public_key'],
                $authenticatorData,
                $clientDataJson,
                $signature,
                (int) ($matchedCredential['alg'] ?? -7)
            )) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tanda tangan kriptografi biometrik tidak valid.',
                ], 401);
            }
        }

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
        if (! is_string($credentialId) || trim($credentialId) === '') {
            return response()->json(['ok' => false, 'message' => 'ID Kredensial tidak valid.'], 422);
        }

        $expectedChallenge = session()->pull('passkey_register_challenge');
        if (! is_string($expectedChallenge) || $expectedChallenge === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Sesi tantangan pendaftaran biometrik tidak valid atau telah kedaluwarsa.',
            ], 422);
        }

        if ($request->filled('clientDataJSON')) {
            if (! $this->verifyClientDataChallenge((string) $request->input('clientDataJSON'), $expectedChallenge, 'webauthn.create')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Verifikasi tantangan pendaftaran WebAuthn gagal.',
                ], 422);
            }
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
            $credentialRecord = [
                'id' => $credentialId,
                'device_name' => $deviceName,
                'registered_at' => now()->translatedFormat('d M Y H:i:s'),
            ];

            if ($request->filled('public_key') && is_string($request->input('public_key'))) {
                $credentialRecord['public_key'] = $request->input('public_key');
                $credentialRecord['alg'] = (int) $request->input('public_key_algorithm', -7);
            }

            $credentials[] = $credentialRecord;

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
     * Verify WebAuthn clientDataJSON type and session challenge.
     */
    protected function verifyClientDataChallenge(string $encodedClientData, string $expectedChallenge, string $expectedType): bool
    {
        $decodedJson = $this->base64UrlDecode($encodedClientData);
        if ($decodedJson === false) {
            return false;
        }

        $clientData = json_decode($decodedJson, true);
        if (! is_array($clientData) || ($clientData['type'] ?? null) !== $expectedType) {
            return false;
        }

        $receivedChallengeBytes = $this->base64UrlDecode((string) ($clientData['challenge'] ?? ''));
        if ($receivedChallengeBytes === false) {
            return false;
        }

        return hash_equals($expectedChallenge, $receivedChallengeBytes)
            || hash_equals(base64_encode($expectedChallenge), (string) ($clientData['challenge'] ?? ''));
    }

    /**
     * Verify WebAuthn assertion signature against stored SPKI public key.
     */
    protected function verifyAssertionSignature(
        string $encodedSpkiPublicKey,
        string $encodedAuthenticatorData,
        string $encodedClientDataJson,
        string $encodedSignature,
        int $algorithm = -7
    ): bool {
        $spkiDer = $this->base64UrlDecode($encodedSpkiPublicKey);
        $authData = $this->base64UrlDecode($encodedAuthenticatorData);
        $clientData = $this->base64UrlDecode($encodedClientDataJson);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($spkiDer === false || $authData === false || $clientData === false || $signature === false) {
            return false;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spkiDer), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $signedData = $authData.hash('sha256', $clientData, true);
        $opensslAlg = $algorithm === -257 ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA256;

        return openssl_verify($signedData, $signature, $pem, $opensslAlg) === 1;
    }

    /**
     * Decode standard base64 or base64url string.
     */
    protected function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'), true);
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
