import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export type SchemaEcho = Echo<'reverb'>;

declare global {
    interface Window {
        Echo?: SchemaEcho;
        Pusher?: typeof Pusher;
    }
}

const enabled = String(import.meta.env.VITE_REVERB_ENABLED ?? 'false') === 'true';
const key = String(import.meta.env.VITE_REVERB_APP_KEY ?? '');
const host = String(import.meta.env.VITE_REVERB_HOST ?? '');

export const createEcho = (): SchemaEcho | null => {
    if (!enabled || key === '' || host === '') return null;

    window.Pusher = Pusher;
    const echo = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: String(import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    window.Echo = echo;
    return echo;
};

export const schemaEcho = createEcho();
