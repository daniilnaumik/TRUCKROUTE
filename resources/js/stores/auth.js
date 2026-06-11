import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('auth_token') || null,
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,
        isAdmin: (state) => state.user?.role === 'admin',
        isProvider: (state) => state.user?.role === 'provider',
        isFleet: (state) => state.user?.role === 'fleet',
        isDriver: (state) => state.user?.role === 'driver',
        role: (state) => state.user?.role ?? null,
    },

    actions: {
        setToken(token) {
            this.token = token;
            if (token) {
                localStorage.setItem('auth_token', token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            } else {
                localStorage.removeItem('auth_token');
                delete axios.defaults.headers.common['Authorization'];
            }
        },

        async login(email, password) {
            this.loading = true;
            try {
                const { data } = await axios.post('/api/v1/auth/login', { email, password });
                this.setToken(data.token);
                this.user = data.user;
                return { ok: true };
            } catch (err) {
                const msg = err.response?.data?.message
                    ?? Object.values(err.response?.data?.errors ?? {})[0]?.[0]
                    ?? 'Ошибка входа';
                return { ok: false, message: msg };
            } finally {
                this.loading = false;
            }
        },

        async register(payload) {
            this.loading = true;
            try {
                const { data } = await axios.post('/api/v1/auth/register', payload);
                this.setToken(data.token);
                this.user = data.user;
                return { ok: true };
            } catch (err) {
                const errors = err.response?.data?.errors ?? {};
                const msg = err.response?.data?.message
                    ?? Object.values(errors)[0]?.[0]
                    ?? 'Ошибка регистрации';
                return { ok: false, message: msg, errors };
            } finally {
                this.loading = false;
            }
        },

        async fetchMe() {
            if (!this.token) return;
            try {
                const { data } = await axios.get('/api/v1/auth/me');
                this.user = data.user;
            } catch {
                // Token invalid or expired — clear it
                this.setToken(null);
                this.user = null;
            }
        },

        async logout() {
            try {
                await axios.post('/api/v1/auth/logout');
            } catch { /* ignore */ }
            this.setToken(null);
            this.user = null;
        },
    },
});
