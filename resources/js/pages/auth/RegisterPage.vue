<template>
    <main class="auth-screen">
        <section class="auth-card" role="main" aria-labelledby="register-title">
            <header class="auth-header">
                <span class="badge">Регистрация</span>
                <h1 id="register-title">Создать аккаунт</h1>
                <p>Создайте профиль водителя. Остальные данные можно заполнить позже в настройках.</p>
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
                        <label for="register-name">Имя</label>
                        <input id="register-name" v-model="form.name" :class="{ 'has-error': errors.name }" type="text" autocomplete="name" required placeholder="Имя Фамилия" @input="clearError('name')">
                        <FieldError :error="errors.name" />
                    </div>

                    <div class="field">
                        <label for="register-email">Email</label>
                        <input id="register-email" v-model="form.email" :class="{ 'has-error': errors.email }" type="email" autocomplete="email" required placeholder="email@example.com" @input="clearError('email')">
                        <FieldError :error="errors.email" />
                    </div>

                    <div class="field">
                        <label for="register-phone">Телефон <span>(необязательно)</span></label>
                        <input id="register-phone" v-model="form.phone" type="tel" autocomplete="tel" placeholder="+375 29 000-00-00">
                    </div>

                    <div class="field">
                        <label for="register-password">Пароль</label>
                        <input id="register-password" v-model="form.password" :class="{ 'has-error': errors.password }" type="password" autocomplete="new-password" required placeholder="Минимум 8 символов" @input="clearError('password')">
                        <FieldError :error="errors.password" />
                        <p v-if="form.password && !errors.password" class="pw-strength" :data-strength="pwStrength">
                            Надёжность: <strong>{{ pwStrengthLabel }}</strong>
                        </p>
                    </div>

                    <div class="field auth-field--wide">
                        <label for="register-password-confirmation">Подтвердите пароль</label>
                        <input id="register-password-confirmation" v-model="form.password_confirmation" :class="{ 'has-error': errors.password_confirmation }" type="password" autocomplete="new-password" required placeholder="Повторите пароль" @input="clearError('password_confirmation')">
                        <FieldError :error="errors.password_confirmation" />
                    </div>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn auth-primary" :disabled="auth.loading">
                        {{ auth.loading ? 'Создаём...' : 'Зарегистрироваться' }}
                    </button>
                    <p>
                        Уже есть аккаунт?
                        <RouterLink :to="{ name: 'login' }">Войти</RouterLink>
                    </p>
                </div>
            </form>
        </section>
    </main>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useUiStore }   from '@/stores/ui';
import AlertCard from '@/components/AlertCard.vue';
import FieldError from '@/components/FieldError.vue';

const auth   = useAuthStore();
const ui     = useUiStore();
const router = useRouter();

const form   = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
const errors = ref({});
const alert  = ref(null);

const pwStrength = computed(() => {
    const p = form.value.password;
    if (!p) return 0;
    let s = 0;
    if (p.length >= 8)  s++;
    if (p.length >= 12) s++;
    if (/[A-ZА-Я]/.test(p) && /[a-zа-я]/.test(p)) s++;
    if (/\d/.test(p))   s++;
    if (/[^A-Za-zА-Яа-я0-9]/.test(p)) s++;
    return Math.min(s, 4);
});
const pwStrengthLabel = computed(() => ['—', 'слабый', 'средний', 'хороший', 'отличный'][pwStrength.value]);

function clearError(field) {
    if (errors.value[field]) errors.value = { ...errors.value, [field]: '' };
    alert.value = null;
}

function validate() {
    const e = {};
    if (!form.value.name.trim()) e.name = 'Укажите имя';
    if (!form.value.email)       e.email = 'Введите email';
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.value.email)) e.email = 'Похоже, email некорректен';
    if (!form.value.password)    e.password = 'Введите пароль';
    else if (form.value.password.length < 8) e.password = 'Минимум 8 символов';
    if (form.value.password !== form.value.password_confirmation) {
        e.password_confirmation = 'Пароли не совпадают';
    }
    errors.value = e;
    return Object.keys(e).length === 0;
}

async function submit() {
    alert.value = null;
    if (!validate()) {
        alert.value = {
            type:  'warning',
            title: 'Проверьте форму',
            body:  'Некоторые поля заполнены неверно — мы подсветили их ниже.',
        };
        return;
    }

    const res = await auth.register(form.value);
    if (res.ok) {
        ui.success({ title: 'Аккаунт создан', body: 'Добро пожаловать в TruckRoute!' });
        router.push({ name: 'home' });
        return;
    }

    // Map backend validation errors onto fields
    if (res.errors) {
        const mapped = {};
        for (const [k, v] of Object.entries(res.errors)) {
            mapped[k] = Array.isArray(v) ? v[0] : v;
        }
        errors.value = mapped;
    }

    alert.value = {
        type:  'error',
        title: 'Не удалось зарегистрироваться',
        body:  res.message || 'Проверьте данные и попробуйте снова.',
        hint:  res.errors?.email ? 'Возможно, такой email уже зарегистрирован.' : '',
    };
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


.pw-strength {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 7px;
    color: var(--text-3);
    font-size: 11px;
}

.pw-strength::before {
    content: '';
    display: inline-block;
    height: 3px;
    border-radius: 2px;
    background: var(--border-mid);
    flex-shrink: 0;
}

.pw-strength[data-strength="1"]::before { width: 20px; background: var(--red); }
.pw-strength[data-strength="2"]::before { width: 40px; background: var(--accent); }
.pw-strength[data-strength="3"]::before { width: 60px; background: var(--accent-2); }
.pw-strength[data-strength="4"]::before { width: 80px; background: var(--green); }
</style>
