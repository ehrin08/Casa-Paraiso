import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const reverbKey = document.querySelector('meta[name="reverb-app-key"]')?.getAttribute('content');

if (reverbKey) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: window.location.hostname,
        wsPort: Number(window.location.port || (window.location.protocol === 'https:' ? 443 : 80)),
        wssPort: Number(window.location.port || 443),
        forceTLS: window.location.protocol === 'https:',
        enabledTransports: ['ws', 'wss'],
    });
}
