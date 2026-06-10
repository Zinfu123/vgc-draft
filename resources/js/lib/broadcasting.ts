/**
 * True when the Vite build included Reverb client env (VITE_REVERB_APP_KEY).
 * If false, skip configureEcho and useEchoPublic so production without Reverb does not throw Axios "Network Error".
 */
export const isReverbBroadcastClientConfigured: boolean =
    typeof import.meta.env.VITE_REVERB_APP_KEY === 'string' && import.meta.env.VITE_REVERB_APP_KEY.length > 0;
