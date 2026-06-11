import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import Constants from 'expo-constants';

function normalizeHostUri(value?: string | null) {
    if (!value) return null;

    return value
        .replace(/^exp:\/\//, '')
        .replace(/^https?:\/\//, '')
        .replace(/\/.*$/, '');
}

function hostFromBundleUrl(value?: string | null) {
    if (!value) return null;

    try {
        return new URL(value).host;
    } catch {
        return normalizeHostUri(value);
    }
}

function getExpoTunnelApiUrl() {
    const constants = Constants as any;
    const hostUri = normalizeHostUri(
        constants.expoConfig?.hostUri
        ?? constants.expoGoConfig?.debuggerHost
        ?? constants.manifest2?.extra?.expoClient?.hostUri
        ?? constants.linkingUri
        ?? constants.manifest?.debuggerHost,
    ) ?? hostFromBundleUrl(
        constants.manifest2?.extra?.expoClient?.bundleUrl
        ?? constants.manifest?.bundleUrl,
    );

    if (!hostUri) return null;

    const protocol = hostUri.includes('exp.direct') ? 'https' : 'http';

    return `${protocol}://${hostUri}/api/v1`;
}

function getDevApiUrl() {
    return getExpoTunnelApiUrl()
        ?? process.env.EXPO_PUBLIC_API_URL
        ?? Constants.expoConfig?.extra?.apiUrl
        ?? 'http://localhost:8000/api/v1';
}

export const API_BASE = __DEV__
    ? getDevApiUrl()
    : process.env.EXPO_PUBLIC_API_URL
        ?? Constants.expoConfig?.extra?.apiUrl
        ?? 'https://your-production-server.com/api/v1';

export function appOrigin() {
    return API_BASE.replace(/\/api\/v1\/?$/, '');
}

export function mediaUrl(value?: string | null) {
    if (!value) return null;

    const origin = appOrigin();

    if (value.startsWith('/')) {
        return `${origin}${value}`;
    }

    try {
        const url = new URL(value);
        if (
            url.hostname === 'localhost'
            || url.hostname === '127.0.0.1'
            || url.hostname.startsWith('192.168.')
            || url.hostname.startsWith('10.')
        ) {
            return `${origin}${url.pathname}${url.search}`;
        }
    } catch {
        return value;
    }

    return value;
}

const client = axios.create({
    baseURL: API_BASE,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        // ngrok free tier requires this header to skip browser warning page
        'ngrok-skip-browser-warning': 'true',
    },
    timeout: 10000,
});

/** Attach Bearer token on every request */
client.interceptors.request.use(async (config) => {
    const token = await SecureStore.getItemAsync('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

/** Handle 401 → clear token */
client.interceptors.response.use(
    (res) => res,
    async (err) => {
        if (err.response?.status === 401) {
            await SecureStore.deleteItemAsync('auth_token');
        }
        return Promise.reject(err);
    }
);

export default client;
