// Helper: Convert base64 / base64url string to Uint8Array
function base64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const b64 = (base64 + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(b64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Helper: Convert ArrayBuffer to base64url string
function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Get CSRF Token
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || (document.querySelector('input[name="_token"]')?.value)
        || '';
}

// 1. Biometric / Passkey Login
window.loginWithPasskey = async function () {
    if (!window.PublicKeyCredential) {
        alert('Perangkat atau browser Anda belum mendukung autentikasi Biometrik / Passkey.');
        return;
    }

    const btn = document.getElementById('btn-biometric-login');

    try {
        if (btn) btn.disabled = true;

        // Try reading email from the login input field if entered
        const emailInput = document.querySelector('input[type="email"]')
            || document.querySelector('input[name="email"]')
            || document.querySelector('input[name="data.email"]')
            || document.getElementById('data.email');
        const email = emailInput?.value?.trim() || '';

        const url = email ? `/auth/passkey/login-options?email=${encodeURIComponent(email)}` : '/auth/passkey/login-options';
        const optionsRes = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });

        if (!optionsRes.ok) {
            throw new Error(`Server status ${optionsRes.status}`);
        }

        const options = await optionsRes.json();

        if (!options.hasCredentials) {
            alert('Belum ada kredensial biometrik (Passkey / Sidik Jari) yang terdaftar. Silakan masuk menggunakan kata sandi (password) terlebih dahulu, lalu daftarkan perangkat di halaman Profil.');
            if (btn) btn.disabled = false;
            return;
        }

        const publicKey = {
            challenge: base64ToUint8Array(options.challenge),
            rpId: options.rpId,
            timeout: 60000,
            userVerification: 'preferred',
        };

        if (options.allowCredentials && options.allowCredentials.length > 0) {
            publicKey.allowCredentials = options.allowCredentials.map(c => ({
                id: base64ToUint8Array(c.id),
                type: c.type,
                transports: c.transports || ['internal', 'hybrid'],
            }));
        }

        const assertion = await navigator.credentials.get({ publicKey });

        if (!assertion) {
            throw new Error('Biometrik dibatalkan atau tidak terdeteksi.');
        }

        const response = await fetch('/auth/passkey/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id: assertion.id,
                rawId: arrayBufferToBase64(assertion.rawId),
                type: assertion.type,
                email: email,
            }),
        });

        const result = await response.json();

        if (result.ok) {
            window.location.href = result.redirect || '/admin';
        } else {
            alert(result.message || 'Gagal memverifikasi biometrik.');
            if (btn) btn.disabled = false;
        }
    } catch (err) {
        console.error('Passkey login error:', err);
        // Ignore user cancellation without annoying alerts
        if (err.name !== 'NotAllowedError') {
            alert('Autentikasi biometrik tidak dapat diselesaikan: ' + (err.message || ''));
        }
        if (btn) btn.disabled = false;
    }
};

// 2. Biometric / Passkey Registration
window.registerPasskey = async function () {
    if (!window.PublicKeyCredential) {
        alert('Browser Anda tidak mendukung WebAuthn / Passkey Biometrik.');
        return;
    }

    try {
        const optionsRes = await fetch('/auth/passkey/register-options', {
            headers: { 'Accept': 'application/json' },
        });
        const options = await optionsRes.json();

        if (!options.challenge) {
            alert('Gagal memuat sesi pendaftaran.');
            return;
        }

        const publicKey = {
            challenge: base64ToUint8Array(options.challenge),
            rp: options.rp,
            user: {
                id: base64ToUint8Array(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName,
            },
            pubKeyCredParams: options.pubKeyCredParams,
            authenticatorSelection: options.authenticatorSelection,
            timeout: options.timeout || 60000,
        };

        const credential = await navigator.credentials.create({ publicKey });

        if (!credential) {
            throw new Error('Pendaftaran biometrik dibatalkan.');
        }

        const response = await fetch('/auth/passkey/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                id: credential.id,
                rawId: arrayBufferToBase64(credential.rawId),
                type: credential.type,
            }),
        });

        const result = await response.json();

        if (result.ok) {
            alert('🎉 ' + result.message);
            window.location.reload();
        } else {
            alert(result.message || 'Gagal menyimpan kredensial.');
        }
    } catch (err) {
        console.error('Passkey register error:', err);
        alert('Pendaftaran biometrik dibatalkan: ' + (err.message || ''));
    }
};

// 3. Clear Registered Passkeys
window.clearPasskeys = async function () {
    if (!confirm('Apakah Anda yakin ingin menghapus semua perangkat biometrik yang terdaftar?')) {
        return;
    }

    try {
        const response = await fetch('/auth/passkey/clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
        });

        const result = await response.json();
        if (result.ok) {
            alert('Kredensial biometrik berhasil dihapus.');
            window.location.reload();
        }
    } catch (err) {
        console.error('Clear passkeys error:', err);
    }
};
