import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const getMeta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content');

const key = getMeta('reverb-key') || import.meta.env.VITE_REVERB_APP_KEY || '443810';
const rawHost = getMeta('reverb-host') || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const host = rawHost.replace(/^https?:\/\//, '');

const scheme = getMeta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
const isSecure = scheme === 'https' || window.location.protocol === 'https:';

const defaultPort = isSecure ? 443 : (window.location.hostname === 'localhost' || window.location.hostname.endsWith('.test') ? 8080 : 80);
const rawPort = getMeta('reverb-port') || import.meta.env.VITE_REVERB_PORT || defaultPort;
const port = parseInt(rawPort, 10);

console.log('[Echo] Initializing Reverb WebSockets client:', { host, port, isSecure, key });

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});
