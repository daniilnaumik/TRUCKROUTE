<template>
    <main class="auth-screen">
        <section class="auth-card auth-card--login" role="main" aria-labelledby="login-title">
            <header class="auth-header">
                <span class="badge">Вход в аккаунт</span>
                <h1 id="login-title">Войти в TruckRoute</h1>
                <p>Доступ к маршрутам, профилю и уведомлениям.</p>
            </header>

            <AlertCard
                v-if="alert"
                :type="alert.type"
                :title="alert.title"
                :body="alert.body"
                :hint="alert.hint"
                :dismissible="true"
                class="auth-alert"
                @close="alert = null"
            />

            <form class="auth-form" @submit.prevent="submit" novalidate>
                <div class="auth-fields">
                    <div class="field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            :class="{ 'has-error': errors.email }"
                            type="email"
                            autocomplete="email"
                            required
                            placeholder="driver@example.com"
                            @input="clearError('email')"
                        >
                        <FieldError :error="errors.email" />
                    </div>

                    <div class="field">
                        <label for="password">Пароль</label>
                        <input
                            id="password"
                            v-model="form.password"
                            :class="{ 'has-error': errors.password }"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Введите пароль"
                            @input="clearError('password')"
                        >
                        <FieldError :error="errors.password" />
                    </div>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn auth-primary" :disabled="auth.loading">
                        {{ auth.loading ? 'Входим...' : 'Войти' }}
                    </button>
                    <p>
                        Нет аккаунта?
                        <RouterLink :to="{ name: 'register' }">Создать аккаунт</RouterLink>
                    </p>
                </div>
            </form>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore }   from '@/stores/ui';
import AlertCard from '@/components/AlertCard.vue';
import FieldError from '@/components/FieldError.vue';

const auth   = useAuthStore();
const ui     = useUiStore();
const router = useRouter();
const route  = useRoute();

const form   = ref({ email: '', password: '' });
const errors = ref({});
const alert  = ref(null);

function clearError(field) {
    if (errors.value[field]) errors.value = { ...errors.value, [field]: '' };
    alert.value = null;
}

function validate() {
    const e = {};
    if (!form.value.email) e.email = 'Введите email';
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.value.email)) e.email = 'Похоже, email некорректен';
    if (!form.value.password) e.password = 'Введите пароль';
    errors.value = e;
    return Object.keys(e).length === 0;
}

async function submit() {
    alert.value = null;
    if (!validate()) return;

    const res = await auth.login(form.value.email, form.value.password);
    if (res.ok) {
        ui.success({ title: 'Добро пожаловать!', body: 'Вход выполнен успешно.' });
        const redirect = route.query.redirect || '/';
        router.push(redirect);
        return;
    }

    // Friendly mapping of common auth errors
    const msg = (res.message || '').toLowerCase();
    if (msg.includes('credentials') || msg.includes('пароль') || msg.includes('email')) {
        alert.value = {
            type:  'error',
            title: 'Не удалось войти',
            body:  'Неверный email или пароль.',
            hint:  'Проверьте раскладку клавиатуры и Caps Lock.',
        };
    } else if (msg.includes('block')) {
        alert.value = {
            type:  'error',
            title: 'Аккаунт заблокирован',
            body:  'Обратитесь к администратору сервиса.',
        };
    } else {
        alert.value = {
            type:  'error',
            title: 'Ошибка входа',
            body:  res.message || 'Попробуйте ещё раз через несколько секунд.',
        };
    }
}
</script>

<style scoped>

.auth-screen {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 48px 24px;
    background: var(--bg);
}

.auth-card {
    width: min(760px, 100%);
    padding: 48px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--s0);
}

.auth-header {
    max-width: 620px;
}

.auth-header h1 {
    margin: 18px 0 0;
    color: var(--text);
    font-size: clamp(42px, 6vw, 68px);
    font-weight: 400;
    line-height: .98;
    letter-spacing: 0;
    text-transform: uppercase;
}

.auth-header h1::after {
    content: '';
    display: block;
    width: 60px;
    height: 1px;
    margin-top: 28px;
    background: var(--accent);
}

.auth-header p {
    margin: 26px 0 0;
    color: var(--text-2);
    font-size: 15px;
    line-height: 1.6;
}

.auth-alert {
    margin-top: 24px;
}

.auth-form {
    margin-top: 30px;
}

.auth-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.auth-field--wide {
    grid-column: 1 / -1;
}

.auth-fields :deep(.field input) {
    height: 52px;
}

.field label span {
    color: var(--text-3);
}

.auth-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.auth-primary {
    min-width: 210px;
    justify-content: center;
}

.auth-actions p {
    margin: 0;
    color: var(--text-3);
    font-size: 13px;
}

.auth-actions a {
    margin-left: 6px;
    color: var(--text);
    text-decoration: none;
    border-bottom: 1px solid var(--border-a);
}

input.has-error {
    border-color: var(--red);
    background: rgba(192, 49, 42, .04);
}

input.has-error:focus {
    outline-color: var(--red);
}

@media (max-width: 640px) {
    .auth-screen {
        place-items: start center;
        padding: 28px 16px;
    }

    .auth-card {
        padding: 30px 22px;
    }

    .auth-header h1 {
        font-size: 42px;
    }

    .auth-fields {
        grid-template-columns: 1fr;
    }

    .auth-field--wide {
        grid-column: auto;
    }

    .auth-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .auth-primary {
        width: 100%;
    }
}


.auth-card--login {
    width: min(700px, 100%);
}
</style>
