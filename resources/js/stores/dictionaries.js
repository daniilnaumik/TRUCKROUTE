import { defineStore } from 'pinia';
import axios from 'axios';

const FALLBACKS = {
    vehicle_types: [
        { value: 'Тягач + полуприцеп', label: 'Тягач + полуприцеп' },
        { value: 'Одиночка', label: 'Одиночка' },
        { value: 'Фургон', label: 'Фургон' },
        { value: 'Рефрижератор', label: 'Рефрижератор' },
        { value: 'Цистерна', label: 'Цистерна' },
    ],
    cargo_types: [
        { value: 'Обычный', label: 'Обычный' },
        { value: 'Скоропортящийся', label: 'Скоропортящийся' },
        { value: 'Опасный', label: 'Опасный (ADR)' },
        { value: 'Негабарит', label: 'Негабаритный' },
        { value: 'Рефриж', label: 'Температурный' },
    ],
    event_types: [
        { value: 'Контроль', label: 'Контроль' },
        { value: 'Очередь', label: 'Очередь' },
        { value: 'Ремонт', label: 'Ремонт' },
        { value: 'ДТП', label: 'ДТП' },
        { value: 'Погода', label: 'Погода' },
        { value: 'Затор', label: 'Затор' },
        { value: 'Перекрытие', label: 'Перекрытие' },
    ],
    poi_categories: [
        { value: 'АЗС', label: 'АЗС' },
        { value: 'Стоянка', label: 'Стоянка' },
        { value: 'Ночлег', label: 'Ночлег' },
        { value: 'СТО', label: 'СТО' },
        { value: 'Кафе', label: 'Кафе' },
        { value: 'Еда', label: 'Еда' },
    ],
    tags: [],
};

export const useDictionariesStore = defineStore('dictionaries', {
    state: () => ({
        items: {},
        loaded: false,
        loading: false,
    }),

    getters: {
        options: (state) => (dictionary) => {
            const values = state.items[dictionary];
            return values?.length ? values : (FALLBACKS[dictionary] ?? []);
        },
    },

    actions: {
        async load(force = false) {
            if (this.loading || (this.loaded && !force)) return;
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/dictionaries');
                this.items = data.data ?? {};
                this.loaded = true;
            } catch {
                this.items = {};
            } finally {
                this.loading = false;
            }
        },
    },
});
