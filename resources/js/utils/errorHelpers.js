/**
 * Translate raw API / network errors into friendly Russian messages
 * with optional hint and action handler.
 *
 * Returns: { title, body?, hint?, fields? }
 *   - fields: Record<fieldName, firstErrorMessage>  (only for 422)
 */

const FIELD_LABELS = {
    email:                 'Email',
    password:              'Пароль',
    password_confirmation: 'Подтверждение пароля',
    name:                  'Имя',
    phone:                 'Телефон',
    title:                 'Название',
    type:                  'Тип',
    origin:                'Точка отправления',
    destination:           'Точка назначения',
    'via':                 'Транзитные точки',
    'via.0':               'Транзитная точка 1',
    'via.1':               'Транзитная точка 2',
    'via.2':               'Транзитная точка 3',
    start_time:            'Время отправления',
    start_fuel_l:          'Уровень топлива при старте',
    vehicle_id:            'Транспорт',
    vehicle:               'Транспорт',
    tank_capacity_l:       'Объём бака',
    consumption_l_per_100: 'Расход топлива',
    cruise_speed_kmh:      'Крейсерская скорость',
    curb_weight_t:         'Собственная масса ТС',
    cargo:                 'Груз',
    'cargo.weight_t':      'Вес груза',
    'cargo.flag':          'Тип груза',
    lat:                   'Широта',
    lng:                   'Долгота',
    q:                     'Поисковый запрос',
    location:              'Место',
    description:           'Описание',
    services:              'Услуги',
    current_password:      'Текущий пароль',
    role:                  'Роль',
    status:                'Статус',
    user_id:               'Пользователь',
    driver_user_id:        'Водитель',
    fleet_id:              'Автопарк',
    planned_start_at:      'Плановый старт',
    delay_minutes:         'Задержка',
    importance:            'Важность',
    highway:               'Трасса',
    km_marker:             'Километровая отметка',
    detour_km:             'Крюк от маршрута',
    fuel_price:            'Цена топлива',
    rating:                'Оценка',
    rating_stars:          'Оценка',
    rating_comment:        'Комментарий к оценке',
    inn:                   'ИНН',
    base_city:             'Город базы',
    address:               'Адрес',
    'contacts.email':      'Email объекта',
    'contacts.website':    'Сайт объекта',
    'truck_access.max_height_m': 'Максимальная высота',
    'truck_access.max_length_m': 'Максимальная длина',
    'truck_access.max_weight_t': 'Максимальная масса',
    'truck_access.parking_spaces': 'Количество парковочных мест',
};

/** Translate validation message to be more direct */
function softenMessage(msg) {
    if (!msg) return '';
    return msg
        .replace(/^The /i, '')
        .replace(/field is required\.?$/i, 'обязательно для заполнения')
        .replace(/must be a string\.?$/i, 'неверный формат')
        .replace(/must be a number\.?$/i, 'должно быть числом')
        .replace(/must be an integer\.?$/i, 'должно быть целым числом')
        .replace(/must be a valid email address\.?$/i, 'укажите корректный email')
        .replace(/must be a valid URL\.?$/i, 'укажите корректную ссылку')
        .replace(/must be at least (\d+)/i, 'минимум $1')
        .replace(/must not be greater than (\d+)/i, 'не больше $1');
}

function fieldLabel(field) {
    if (FIELD_LABELS[field]) return FIELD_LABELS[field];

    const normalized = field
        .replace(/\.\d+\./g, '.')
        .replace(/\.\d+$/g, '');

    if (FIELD_LABELS[normalized]) return FIELD_LABELS[normalized];
    if (/^price_details\.\d+\.name$/.test(field)) return 'Название цены';
    if (/^price_details\.\d+\.price$/.test(field)) return 'Цена';
    if (/^price_details\.\d+\.unit$/.test(field)) return 'Единица измерения';
    if (/^promotions\.\d+\.title$/.test(field)) return 'Название акции';
    if (/^promotions\.\d+\.valid_until$/.test(field)) return 'Срок действия акции';
    if (/^gallery\.\d+$/.test(field)) return 'Файл галереи';
    if (/^tags\.\d+$/.test(field)) return 'Тег';

    return field
        .replaceAll('_', ' ')
        .replaceAll('.', ' ')
        .replace(/^./, char => char.toUpperCase());
}

export function explainError(err) {
    // Network failure
    if (err?.message === 'Network Error' || err?.code === 'ERR_NETWORK') {
        return {
            title: 'Нет связи с сервером',
            body:  'Проверьте подключение к интернету.',
            hint:  'Если проблема повторится — попробуйте обновить страницу.',
        };
    }

    // Timeout
    if (err?.code === 'ECONNABORTED') {
        return {
            title: 'Сервер слишком долго отвечает',
            body:  'Запрос не успел завершиться.',
            hint:  'Попробуйте ещё раз через несколько секунд.',
        };
    }

    const res = err?.response;
    if (!res) {
        return { title: err?.message ?? 'Что-то пошло не так', body: '', hint: '' };
    }

    const data   = res.data ?? {};
    const status = res.status;
    const msg    = data.message;
    const errors = data.errors;

    // 401 — auth
    if (status === 401) {
        return {
            title: 'Нужно войти в аккаунт',
            body:  'Сессия истекла или вы вышли из системы.',
            hint:  'Войдите снова, чтобы продолжить.',
        };
    }

    // 403 — forbidden
    if (status === 403) {
        return {
            title: 'Доступ запрещён',
            body:  msg || 'У вашей роли нет прав на это действие.',
            hint:  'Если это ошибка — обратитесь к администратору.',
        };
    }

    // 404 — not found
    if (status === 404) {
        return {
            title: 'Не найдено',
            body:  msg || 'Запрашиваемый объект не существует или был удалён.',
        };
    }

    // 422 — validation
    if (status === 422 && errors) {
        const fields = {};
        const lines  = [];
        for (const [field, msgs] of Object.entries(errors)) {
            const label = fieldLabel(field);
            const first = softenMessage(Array.isArray(msgs) ? msgs[0] : msgs);
            fields[field] = first;
            lines.push(`${label}: ${first.toLowerCase()}`);
        }
        return {
            title:  'Проверьте введённые данные',
            body:   lines.slice(0, 3).join('. '),
            hint:   lines.length > 3 ? `И ещё ${lines.length - 3} полей...` : '',
            fields,
        };
    }

    // 429 — rate limit
    if (status === 429) {
        return {
            title: 'Слишком много запросов',
            body:  'Подождите немного перед следующей попыткой.',
        };
    }

    // 503 — provider unavailable (geo / OSRM)
    if (status === 503) {
        return {
            title: msg?.includes('Геокодер') ? 'Геокодер недоступен' :
                   msg?.includes('Роутинг')  ? 'Сервис маршрутов недоступен' :
                   'Сервис временно недоступен',
            body:  msg || 'Внешний сервис не отвечает.',
            hint:  'Попробуйте через минуту.',
        };
    }

    // 500+ — server error
    if (status >= 500) {
        return {
            title: 'Ошибка на сервере',
            body:  'Не удалось обработать запрос.',
            hint:  'Мы уже работаем над исправлением. Попробуйте позже.',
        };
    }

    // 4xx fallback
    return {
        title: msg || 'Не удалось выполнить операцию',
        body:  '',
    };
}

/** Convenience: directly push as error toast via ui store */
export function toastError(ui, err, override = {}) {
    const info = explainError(err);
    return ui.error({
        title:  override.title ?? info.title,
        body:   override.body  ?? info.body,
        hint:   override.hint  ?? info.hint,
        action: override.action,
    });
}
