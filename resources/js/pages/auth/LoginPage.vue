<template>
    <div class="auth-page">
        <div class="auth-modal is-open" style="position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;">
            <div class="auth-modal__backdrop" style="position:fixed;inset:0;background:var(--glass-backdrop);backdrop-filter:blur(8px);"></div>
            <div class="auth-modal__panel" role="main" style="position:relative;z-index:2;">
                <span class="badge">вход в аккаунт</span>
                <h2 style="margin-top:18px;">Войдите в TruckRoute</h2>
                <p class="lead">Доступ к маршрутам, профилю и уведомлениям.</p>

                <AlertCard
                    v-if="alert"
                    :type="alert.type"
                    :title="alert.title"
                    :body="alert.body"
                    :hint="alert.hint"
                    :dismissible="true"
                    style="margin-top:20px;"
                    @close="alert = null"
                />

                <form class="form-grid" style="margin-top:24px;" @submit.prevent="submit" novalidate>
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
                            placeholder="••••••••"
                            @input="clearError('password')"
                        >
                        <FieldError :error="errors.password" />
                    </div>
                    <div class="actions" style="margin-top:8px;">
                        <button type="submit" class="btn" :disabled="auth.loading">
                            {{ auth.loading ? 'Входим...' : 'Войти' }}
                        </button>
                        <RouterLink :to="{ name: 'register' }" class="btn outline">Создать аккаунт</RouterLink>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
input.has-error {
    border-color: var(--red);
    background: rgba(192,49,42,.04);
}
input.has-error:focus { outline-color: var(--red); }
</style>
