<template>
    <div class="geo-input" ref="wrap">
        <input
            :id="inputId"
            v-model="text"
            type="text"
            :placeholder="placeholder"
            autocomplete="off"
            :class="{ 'has-value': !!modelValue }"
            @input="onInput"
            @focus="showDrop = suggestions.length > 0"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="selectHighlighted"
            @keydown.esc="close"
        >
        <button
            v-if="modelValue"
            type="button"
            class="geo-input__clear"
            tabindex="-1"
            aria-label="Очистить"
            @click="clearValue"
        >×</button>
        <ul
            v-if="showDrop && (suggestions.length || noResult || loading)"
            class="geo-input__dropdown"
            role="listbox"
        >
            <li v-if="loading" style="color:var(--text-3);cursor:default;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .7s linear infinite"><circle cx="12" cy="12" r="10" stroke-dasharray="28" stroke-dashoffset="10"/></svg>
                Поиск...
            </li>
            <li v-else-if="noResult && !suggestions.length" style="color:var(--text-3);cursor:default;">
                Адрес не найден
            </li>
            <li
                v-for="(s, i) in suggestions"
                :key="i"
                :class="{ highlighted: i === highlighted }"
                role="option"
                :aria-selected="i === highlighted"
                @mousedown.prevent="select(s)"
                @mouseover="highlighted = i"
            >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.5"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ s.label ?? s.display_name ?? s.name }}
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useGeocode } from '@/composables/useGeocode';

const props = defineProps({
    modelValue: { type: Object, default: null },   // { lat, lng, label }
    placeholder: { type: String, default: 'Введите адрес или город' },
    inputId: { type: String, default: undefined },
});
const emit = defineEmits(['update:modelValue']);

const { suggestions, loading, noResult, search, clear } = useGeocode(400);
const wrap        = ref(null);
const text        = ref(props.modelValue?.label ?? '');
const showDrop    = ref(false);
const highlighted = ref(-1);

watch(() => props.modelValue, (v) => {
    text.value = v?.label ?? '';
});

function onInput() {
    emit('update:modelValue', null);   // clear selection until user picks from dropdown
    showDrop.value = true;
    highlighted.value = -1;
    search(text.value);
}

function select(s) {
    const point = { lat: parseFloat(s.lat), lng: parseFloat(s.lon ?? s.lng), label: s.label ?? s.display_name ?? s.name };
    emit('update:modelValue', point);
    text.value  = point.label;
    showDrop.value = false;
    clear();
}

function clearValue() {
    text.value = '';
    emit('update:modelValue', null);
    clear();
    showDrop.value = false;
}

function close() { showDrop.value = false; }

function moveDown() {
    if (highlighted.value < suggestions.value.length - 1) highlighted.value++;
}
function moveUp() {
    if (highlighted.value > 0) highlighted.value--;
}
function selectHighlighted() {
    if (highlighted.value >= 0 && suggestions.value[highlighted.value]) {
        select(suggestions.value[highlighted.value]);
    }
}

// Close on outside click
function onOutside(e) {
    if (wrap.value && !wrap.value.contains(e.target)) showDrop.value = false;
}
onMounted(()   => document.addEventListener('click', onOutside));
onUnmounted(() => document.removeEventListener('click', onOutside));
</script>

<style>
.geo-input {
    position: relative;
    width: 100%;
}
.geo-input input { width: 100%; padding-right: 28px; }
.geo-input__clear {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-3);
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    min-height: auto;
    box-shadow: none;
    line-height: 1;
}
.geo-input__clear:hover { color: var(--text); transform: translateY(-50%); box-shadow: none; }
.geo-input__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 300;
    background: var(--glass-modal);
    border: 1px solid var(--border-mid);
    border-radius: 6px;
    box-shadow: var(--shadow-md);
    backdrop-filter: blur(20px);
    list-style: none;
    margin: 0;
    padding: 4px 0;
    max-height: 220px;
    overflow-y: auto;
}
.geo-input__dropdown li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--text);
    cursor: pointer;
    transition: background .1s;
}
.geo-input__dropdown li:hover,
.geo-input__dropdown li.highlighted { background: var(--s2); }
</style>
