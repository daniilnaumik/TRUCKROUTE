<template>
    <div style="position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;">
        <div class="auth-modal__backdrop" style="position:fixed;inset:0;background:var(--glass-backdrop);backdrop-filter:blur(8px);"></div>
        <div class="auth-modal__panel" role="main" style="position:relative;z-index:2;">
            <span class="badge">регистрация</span>
            <h2 style="margin-top:18px;">Создать аккаунт</h2>
            <p class="lead">Регистрация водителя — данные профиля можно дополнить позже.</p>

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
                    <label>Имя</label>
                    <input v-model="form.name" :class="{ 'has-error': errors.name }" type="text" autocomplete="name" required placeholder="Имя Фамилия" @input="clearError('name')">
                    <FieldError :error="errors.name" />
                </div>
                <div class="field">
                    <label>Email</label>
                    <input v-model="form.email" :class="{ 'has-error': errors.email }" type="email" autocomplete="email" required placeholder="email@example.com" @input="clearError('email')">
                    <FieldError :error="errors.email" />
                </div>
                <div class="field">
                    <label>Телефон <span style="color:var(--text-3)">(необязательно)</span></label>
                    <input v-model="form.phone" type="tel" autocomplete="tel" placeholder="+7 900 000-00-00">
                </div>
                <div class="field">
                    <label>Пароль</label>
                    <input v-model="form.password" :class="{ 'has-error': errors.password }" type="password" autocomplete="new-password" required placeholder="Минимум 8 символов" @input="clearError('password')">
                    <FieldError :error="errors.password" />
                    <p v-if="form.password && !errors.password" class="pw-strength" :data-strength="pwStrength">
                        Надёжность: <strong>{{ pwStrengthLabel }}</strong>
                    </p>
                </div>
                <div class="field">
                    <label>Подтвердите пароль</label>
                    <input v-model="form.password_confirmation" :class="{ 'has-error': errors.password_confirmation }" type="password" autocomplete="new-password" required placeholder="Повторите пароль" @input="clearError('password_confirmation')">
                    <FieldError :error="errors.password_confirmation" />
                </div>
                <div class="actions" style="margin-top:8px;">
                    <button type="submit" class="btn" :disabled="auth.loading">
                        {{ auth.loading ? 'Создаём...' : 'Зарегистрироваться' }}
                    </button>
                    <RouterLink :to="{ name: 'login' }" class="btn outline">Уже есть аккаунт</RouterLink>
                </div>
            </form>
        </div>
    </div>
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
input.has-error { border-color: var(--red); background: rgba(192,49,42,.04); }
input.has-error:focus { outline-color: var(--red); }

.pw-strength {
    font-size: 11px;
    color: var(--text-3);
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pw-strength::before {
    content: '';
    display: inline-block;
    height: 3px;
    border-radius: 2px;
    background: var(--border-mid);
    transition: width .2s, background .2s;
    flex-shrink: 0;
}
.pw-strength[data-strength="1"]::before { width: 20px; background: var(--red); }
.pw-strength[data-strength="2"]::before { width: 40px; background: var(--accent); }
.pw-strength[data-strength="3"]::before { width: 60px; background: var(--accent-2); }
.pw-strength[data-strength="4"]::before { width: 80px; background: var(--green); }
</style>
