import { defineStore } from 'pinia';

/**
 * Toast shape:
 * {
 *   id, type: 'success'|'error'|'warning'|'info',
 *   title: string,             // bold first line
 *   body?: string,              // optional body text
 *   hint?: string,              // optional small hint ("Что делать?")
 *   action?: {                  // optional inline button
 *     label: string,
 *     handler: () => void,
 *   },
 *   duration: number,           // ms, 0 = sticky
 *   createdAt: number,          // for progress bar
 * }
 */
export const useUiStore = defineStore('ui', {
    state: () => ({
        theme: localStorage.getItem('theme') || 'light',
        toasts: [],
        _toastId: 0,
    }),

    actions: {
        // ── Theme ─────────────────────────────────────────────────────────
        setTheme(theme) {
            this.theme = theme;
            localStorage.setItem('theme', theme);
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else document.documentElement.removeAttribute('data-theme');
        },
        toggleTheme() { this.setTheme(this.theme === 'dark' ? 'light' : 'dark'); },

        // ── Toasts ────────────────────────────────────────────────────────
        /**
         * Push a toast. Accepts either a string (legacy) or an options object.
         * @param {string|object} opts
         * @param {string} [type='info']
         * @param {number} [duration]
         */
        toast(opts, type = 'info', duration) {
            // String shorthand — wraps as { title: opts }
            const o = typeof opts === 'string' ? { title: opts } : { ...opts };
            const t = o.type ?? type;
            const def = { success: 4000, info: 4000, warning: 6000, error: 7000 }[t] ?? 4000;
            const id = ++this._toastId;
            const now = Date.now();

            const recent = [...this.toasts]
                .reverse()
                .find(item => item.type === t && now - item.createdAt < 900);

            if (
                recent
                && recent.title === (o.title ?? '')
                && recent.body === (o.body ?? '')
            ) {
                return recent.id;
            }

            const isDetailedValidation = t === 'error' && o.title === 'Проверьте введённые данные';
            if (isDetailedValidation && recent) {
                this.dismissToast(recent.id);
            } else if (
                t === 'error'
                && recent?.title === 'Проверьте введённые данные'
            ) {
                return recent.id;
            }

            const toast = {
                id,
                type: t,
                title:     o.title  ?? '',
                body:      o.body   ?? '',
                hint:      o.hint   ?? '',
                action:    o.action ?? null,
                duration:  o.duration ?? duration ?? def,
                createdAt: now,
            };
            this.toasts.push(toast);
            if (toast.duration > 0) {
                setTimeout(() => this.dismissToast(id), toast.duration);
            }
            return id;
        },

        success(opts) { return this.toast(typeof opts === 'string' ? { title: opts } : opts, 'success'); },
        info(opts)    { return this.toast(typeof opts === 'string' ? { title: opts } : opts, 'info'); },
        warning(opts) { return this.toast(typeof opts === 'string' ? { title: opts } : opts, 'warning'); },
        error(opts)   { return this.toast(typeof opts === 'string' ? { title: opts } : opts, 'error'); },

        dismissToast(id) {
            const idx = this.toasts.findIndex(t => t.id === id);
            if (idx !== -1) this.toasts.splice(idx, 1);
        },

        clearToasts() { this.toasts = []; },
    },
});
