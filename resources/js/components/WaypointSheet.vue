<template>
    <Teleport to="body">
        <Transition name="sheet">
            <div v-if="modelValue" class="ws-backdrop" @click.self="$emit('update:modelValue', false)">
                <div class="ws-sheet" role="dialog" aria-label="Ближайшие объекты">
                    <!-- Handle -->
                    <div class="ws-handle-row">
                        <div class="ws-handle"></div>
                        <button class="ws-close" @click="$emit('update:modelValue', false)" aria-label="Закрыть">✕</button>
                    </div>

                    <div class="ws-scroll">
                        <!-- Route stops -->
                        <template v-if="stops.length">
                            <p class="ws-section-label">По маршруту</p>
                            <div class="ws-cards">
                                <WaypointCard
                                    v-for="stop in stops"
                                    :key="stop.service_object_id"
                                    :stop="stop"
                                    @accept="onAccept(stop)"
                                    @reject="onReject(stop)"
                                />
                            </div>
                        </template>

                        <!-- System suggestions -->
                        <template v-if="suggestions.length">
                            <p class="ws-section-label ws-section-label--suggest">
                                ✦ Рекомендуем
                                <span class="ws-suggest-hint">Объекты рядом, не в маршруте</span>
                            </p>
                            <div class="ws-cards ws-cards--suggest">
                                <WaypointCard
                                    v-for="sug in suggestions"
                                    :key="sug.id"
                                    :stop="suggestToStop(sug)"
                                    :is-suggestion="true"
                                    @accept="onAcceptSuggestion(sug)"
                                    @reject="onRejectSuggestion(sug)"
                                />
                            </div>
                        </template>

                        <div v-if="!stops.length && !suggestions.length" class="ws-empty">
                            Ближайших объектов не найдено
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import axios from 'axios';
import WaypointCard from './WaypointCard.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    stops:       { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'accepted', 'rejected']);

function suggestToStop(sug) {
    return {
        service_object_id: sug.id,
        name:        sug.name,
        type:        sug.type,
        lat:         sug.lat,
        lng:         sug.lng,
        distance_km: null,
        eta_at:      null,
        detour_km:   sug.detour_km,
        services:    sug.services,
    };
}

async function onAccept(stop) {
    try {
        const { data } = await axios.post('/api/v1/trip/stop-decision', {
            service_object_id: stop.service_object_id,
            action: 'accepted',
        });
        emit('accepted', { stop, waypoint: data.waypoint });
    } catch { /* ignore */ }
}

async function onReject(stop) {
    try {
        await axios.post('/api/v1/trip/stop-decision', {
            service_object_id: stop.service_object_id,
            action: 'rejected',
        });
        emit('rejected', stop);
    } catch { /* ignore */ }
}

async function onAcceptSuggestion(sug) {
    try {
        const { data } = await axios.post('/api/v1/trip/stop-decision', {
            service_object_id: sug.id,
            action: 'accepted',
        });
        emit('accepted', { stop: suggestToStop(sug), waypoint: data.waypoint });
    } catch { /* ignore */ }
}

async function onRejectSuggestion(sug) {
    try {
        await axios.post('/api/v1/trip/stop-decision', {
            service_object_id: sug.id,
            action: 'rejected',
        });
        emit('rejected', suggestToStop(sug));
    } catch { /* ignore */ }
}
</script>

<style scoped>
.ws-backdrop {
    position: fixed;
    inset: 0;
    z-index: 400;
    background: var(--glass-backdrop);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: flex-end;
}

.ws-sheet {
    width: 100%;
    max-width: 560px;
    margin: 0 auto;
    background: var(--glass-modal);
    border: 1px solid var(--border-mid);
    border-bottom: none;
    border-radius: 16px 16px 0 0;
    box-shadow: var(--shadow-xl);
    max-height: 80dvh;
    display: flex;
    flex-direction: column;
}

.ws-handle-row {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 16px 0;
    position: relative;
}

.ws-handle {
    width: 40px;
    height: 4px;
    background: var(--border-mid);
    border-radius: 2px;
}

.ws-close {
    position: absolute;
    right: 16px;
    top: 8px;
    background: none;
    border: none;
    color: var(--text-3);
    font-size: 16px;
    cursor: pointer;
    padding: 4px 8px;
    min-height: auto;
    box-shadow: none;
    transition: color .15s;
}
.ws-close:hover { color: var(--text); transform: none; box-shadow: none; }

.ws-scroll {
    overflow-y: auto;
    padding: 16px;
    flex: 1;
}

.ws-section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-3);
    font-family: var(--font-m);
    margin: 0 0 10px;
}

.ws-section-label--suggest {
    color: var(--accent);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
}

.ws-suggest-hint {
    font-size: 10px;
    text-transform: none;
    letter-spacing: 0;
    color: var(--text-3);
    font-family: system-ui, sans-serif;
}

.ws-cards { display: flex; flex-direction: column; gap: 8px; }
.ws-cards--suggest { opacity: .92; }

.ws-empty {
    text-align: center;
    color: var(--text-3);
    font-size: 13px;
    padding: 32px 0;
}

/* Sheet transition */
.sheet-enter-active, .sheet-leave-active { transition: opacity .2s, transform .25s; }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
.sheet-enter-from .ws-sheet, .sheet-leave-to .ws-sheet { transform: translateY(100%); }
</style>
