import { API_BASE } from '@/api/client';

const FIELD_LABELS: Record<string, string> = {
    title: 'Название',
    name: 'Название',
    email: 'Email',
    phone: 'Телефон',
    password: 'Пароль',
    password_confirmation: 'Подтверждение пароля',
    user_id: 'ID пользователя',
    driver_user_id: 'Водитель',
    origin: 'Точка отправления',
    destination: 'Точка назначения',
    planned_start_at: 'Плановый старт',
    start_time: 'Время отправления',
    start_fuel_l: 'Топливо при старте',
    tank_capacity_l: 'Объём бака',
    consumption_l_per_100: 'Расход топлива',
    cruise_speed_kmh: 'Крейсерская скорость',
    curb_weight_t: 'Масса транспорта',
    'cargo.weight_t': 'Вес груза',
    lat: 'Широта',
    lng: 'Долгота',
    location: 'Адрес',
    fuel_price: 'Цена топлива',
    km_marker: 'Километровая отметка',
    inn: 'ИНН',
};

function labelFor(field: string) {
    return FIELD_LABELS[field]
        ?? field.replaceAll('_', ' ').replaceAll('.', ' ').replace(/^./, value => value.toUpperCase());
}

export function apiErrorMessage(error: any, fallback: string) {
    if (!error?.response) {
        return `Нет подключения к API: ${API_BASE}. Проверьте доступность сервера и подключение телефона.`;
    }

    const errors = error.response?.data?.errors as Record<string, string[] | string> | undefined;
    if (errors) {
        const lines = Object.entries(errors).slice(0, 3).map(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            return `${labelFor(field)}: ${message}`;
        });
        if (lines.length) return lines.join('\n');
    }

    return error.response?.data?.message ?? fallback;
}

interface NumberOptions {
    required?: boolean;
    integer?: boolean;
    min?: number;
    max?: number;
}

export function validateNumber(value: string, label: string, options: NumberOptions = {}) {
    const normalized = value.trim().replace(',', '.');

    if (!normalized) {
        return options.required
            ? { ok: false as const, message: `${label}: заполните поле.` }
            : { ok: true as const, value: undefined };
    }

    const number = Number(normalized);
    if (!Number.isFinite(number)) {
        return { ok: false as const, message: `${label}: введите корректное число.` };
    }
    if (options.integer && !Number.isInteger(number)) {
        return { ok: false as const, message: `${label}: введите целое число.` };
    }
    if (options.min !== undefined && number < options.min) {
        return { ok: false as const, message: `${label}: значение должно быть не меньше ${options.min}.` };
    }
    if (options.max !== undefined && number > options.max) {
        return { ok: false as const, message: `${label}: значение должно быть не больше ${options.max}.` };
    }

    return { ok: true as const, value: number };
}
