import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const getMeta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content');

const key = getMeta('reverb-key') || import.meta.env.VITE_REVERB_APP_KEY || '443810';
const rawHost = getMeta('reverb-host') || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const host = rawHost.replace(/^https?:\/\//, '');

const scheme = getMeta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
const isSecure = scheme === 'https' || window.location.protocol === 'https:';

const rawPort = getMeta('reverb-port') || import.meta.env.VITE_REVERB_PORT || (isSecure ? 443 : 80);
const port = parseInt(rawPort, 10);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});
