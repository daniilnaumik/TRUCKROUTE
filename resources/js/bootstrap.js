import axios from 'axios';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept']           = 'application/json';

// Restore Bearer token on page load
const token = localStorage.getItem('auth_token');
if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

/**
 * Global response interceptor — friendly notifications for unhandled errors.
 * Caller can opt out by setting `config.silent = true` on a request.
 *
 * Validation errors are also shown globally. Forms may additionally render
 * field-level hints; the UI store suppresses duplicate messages.
 */
axios.interceptors.response.use(
    res => res,
    err => {
        // Cancellations: ignore
        if (axios.isCancel?.(err)) return Promise.reject(err);

        const config = err?.config ?? {};
        if (config.silent) return Promise.reject(err);

        if (
            err?.response?.status === 403
            && String(config.method ?? 'get').toLowerCase() === 'get'
            && !config.preventForbiddenRedirect
        ) {
            window.dispatchEvent(new CustomEvent('app:forbidden', {
                detail: {
                    message: err.response?.data?.message,
                    from: window.location.pathname,
                },
            }));
        }

        // Lazy import to avoid circular dep with stores
        import('./stores/ui').then(({ useUiStore }) => {
            import('./utils/errorHelpers').then(({ explainError }) => {
                const status = err?.response?.status;
                // Skip 401 on /auth/me (silent refresh)
                if (status === 401 && config.url?.includes('/auth/me')) return;
                // Skip 404 on optional endpoints (trip/current returns 404 when no active session is valid use)
                if (status === 404 && /\/trip\/current$/.test(config.url ?? '')) return;

                const ui = useUiStore();
                const info = explainError(err);
                ui.error({
                    title: info.title,
                    body:  info.body,
                    hint:  info.hint,
                });
            });
        });

        return Promise.reject(err);
    }
);

function fieldTitle(element) {
    const field = element.closest?.('.field');
    const label = field?.querySelector?.('label')?.textContent?.trim();
    return label
        || element.getAttribute?.('aria-label')
        || element.getAttribute?.('placeholder')
        || element.getAttribute?.('name')
        || 'Поле';
}

function invalidMessage(element) {
    const validity = element.validity;
    const title = fieldTitle(element);
    const min = element.getAttribute?.('min');
    const max = element.getAttribute?.('max');
    const minLength = element.getAttribute?.('minlength');
    const maxLength = element.getAttribute?.('maxlength');

    if (validity?.valueMissing) return `${title}: заполните это поле.`;
    if (validity?.badInput) return `${title}: введите корректное число.`;
    if (validity?.typeMismatch && element.type === 'email') return `${title}: укажите email в формате name@example.com.`;
    if (validity?.typeMismatch && element.type === 'url') return `${title}: укажите полную ссылку, например https://example.com.`;
    if (validity?.rangeUnderflow) return `${title}: значение должно быть не меньше ${min}.`;
    if (validity?.rangeOverflow) return `${title}: значение должно быть не больше ${max}.`;
    if (validity?.stepMismatch) return `${title}: введите допустимое значение с правильным шагом.`;
    if (validity?.tooShort) return `${title}: введите не менее ${minLength} символов.`;
    if (validity?.tooLong) return `${title}: введите не более ${maxLength} символов.`;
    if (validity?.patternMismatch) return element.title || `${title}: проверьте формат значения.`;

    return element.title || `${title}: проверьте введённое значение.`;
}

document.addEventListener('invalid', event => {
    const element = event.target;
    if (!(element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement)) {
        return;
    }

    event.preventDefault();
    element.classList.add('has-error');
    element.setAttribute('aria-invalid', 'true');
    element.focus({ preventScroll: true });
    element.scrollIntoView({ behavior: 'smooth', block: 'center' });

    import('./stores/ui').then(({ useUiStore }) => {
        useUiStore().error({
            title: 'Проверьте введённые данные',
            body: invalidMessage(element),
            hint: minMaxHint(element),
        });
    });
}, true);

document.addEventListener('input', event => {
    const element = event.target;
    if (element?.classList?.contains('has-error')) {
        element.classList.remove('has-error');
        element.removeAttribute('aria-invalid');
    }
}, true);

function minMaxHint(element) {
    if (element.type !== 'number') return '';
    const min = element.getAttribute('min');
    const max = element.getAttribute('max');
    if (min !== null && max !== null) return `Допустимый диапазон: от ${min} до ${max}.`;
    if (min !== null) return `Минимальное значение: ${min}.`;
    if (max !== null) return `Максимальное значение: ${max}.`;
    return '';
}
