import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import {
    AuthUser,
    login as apiLogin,
    register as apiRegister,
    me,
    logout as apiLogout,
    LoginPayload,
    RegisterPayload,
} from '@/api/auth';
import { API_BASE } from '@/api/client';
import { apiErrorMessage } from '@/utils/errors';

interface AuthState {
    user: AuthUser | null;
    loading: boolean;
    initialized: boolean;

    initialize: () => Promise<void>;
    login: (p: LoginPayload) => Promise<{ ok: boolean; message?: string }>;
    register: (p: RegisterPayload) => Promise<{ ok: boolean; message?: string; errors?: Record<string, string[]> }>;
    logout: () => Promise<void>;

    isAuthenticated: () => boolean;
    isAdmin: () => boolean;
    isDriver: () => boolean;
}

function firstApiError(err: any, fallback: string) {
    if (!err.response) {
        return {
            message: `Нет подключения к API: ${API_BASE}. Проверьте, открыт ли этот адрес на телефоне в Safari.`,
            errors: undefined,
        };
    }

    const errors = err.response?.data?.errors as Record<string, string[]> | undefined;
    const message = apiErrorMessage(err, fallback);

    return { message, errors };
}

export const useAuthStore = create<AuthState>((set, get) => ({
    user: null,
    loading: false,
    initialized: false,

    initialize: async () => {
        const token = await SecureStore.getItemAsync('auth_token');
        if (token) {
            try {
                const user = await me();
                set({ user, initialized: true });
            } catch {
                await SecureStore.deleteItemAsync('auth_token');
                set({ user: null, initialized: true });
            }
        } else {
            set({ initialized: true });
        }
    },

    login: async (payload) => {
        set({ loading: true });
        try {
            const user = await apiLogin(payload);
            set({ user });
            return { ok: true };
        } catch (err: any) {
            const { message } = firstApiError(err, 'Ошибка входа');
            return { ok: false, message };
        } finally {
            set({ loading: false });
        }
    },

    register: async (payload) => {
        set({ loading: true });
        try {
            const user = await apiRegister(payload);
            set({ user });
            return { ok: true };
        } catch (err: any) {
            const { message, errors } = firstApiError(err, 'Ошибка регистрации');
            return { ok: false, message, errors };
        } finally {
            set({ loading: false });
        }
    },

    logout: async () => {
        await apiLogout();
        set({ user: null });
    },

    isAuthenticated: () => !!get().user,
    isAdmin: () => get().user?.role === 'admin',
    isDriver: () => get().user?.role === 'driver',
}));
