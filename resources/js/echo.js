import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const getMeta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content');

const key = getMeta('reverb-key') || import.meta.env.VITE_REVERB_APP_KEY || '443810';
const rawHost = getMeta('reverb-host') || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const host = rawHost.replace(/^https?:\/\//, '');

const scheme = getMeta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
const isSecure = scheme === 'https' || window.location.protocol === 'https:';

// Default port: if HTTPS -> 443; if HTTP with port (e.g. localhost:8080) -> port; else 8080
const defaultPort = isSecure ? 443 : (window.location.port && window.location.port !== '80' ? parseInt(window.location.port, 10) : 8080);
const rawPort = getMeta('reverb-port') || import.meta.env.VITE_REVERB_PORT || defaultPort;
const port = parseInt(rawPort, 10);

console.log('[Reverb/Echo] Initializing connection with:', {
    host,
    port,
    isSecure,
    key,
    transport: isSecure ? 'wss' : 'ws'
});

try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: isSecure,
        enabledTransports: ['ws', 'wss'],
    });

    if (window.Echo.connector?.pusher?.connection) {
        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('🟢 [Reverb/Echo] WebSocket Connected successfully to channel!');
        });

        window.Echo.connector.pusher.connection.bind('error', (err) => {
            console.error('🔴 [Reverb/Echo] WebSocket Connection Error:', err);
        });
    }
} catch (error) {
    console.error('🔴 [Reverb/Echo] Failed to initialize Echo:', error);
}
