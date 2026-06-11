import { computed, onUnmounted, ref, unref } from 'vue';
import axios from 'axios';

function responseData(response) {
    return response?.data?.data ?? response?.data ?? null;
}

function errorMessage(error, fallback) {
    return error?.response?.data?.message
        ?? Object.values(error?.response?.data?.errors ?? {})[0]?.[0]
        ?? error?.message
        ?? fallback;
}

function normalizeRoutePlanId(routePlanId) {
    const value = unref(routePlanId);
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : null;
}

export function useTripTracking(routePlanId) {
    const isTracking = ref(false);
    const isWatchingLocation = ref(false);
    const permissionDenied = ref(false);
    const locationError = ref('');
    const currentPos = ref(null);
    const activeSession = ref(null);
    const upcomingStops = ref([]);
    const systemSuggestions = ref([]);
    const showWaypointSheet = ref(false);

    let watchId = null;

    const activeRoutePlanId = computed(() => activeSession.value?.route_plan_id ?? null);
    const isCurrentRouteActive = computed(() => {
        const currentRouteId = normalizeRoutePlanId(routePlanId);
        return isTracking.value && (!currentRouteId || activeRoutePlanId.value === currentRouteId);
    });

    async function loadCurrent() {
        try {
            const response = await axios.get('/api/v1/trip/current', { silent: true });
            const session = responseData(response);
            activeSession.value = session;
            isTracking.value = session?.status === 'active';

            if (session?.last_lat != null && session?.last_lng != null) {
                currentPos.value = {
                    lat: Number(session.last_lat),
                    lng: Number(session.last_lng),
                    accuracy: null,
                };
            }

            if (isTracking.value) {
                startLocationWatch();
            }

            return session;
        } catch (error) {
            activeSession.value = null;
            isTracking.value = false;
            return null;
        }
    }

    async function startTracking() {
        const id = normalizeRoutePlanId(routePlanId);

        try {
            const response = await axios.post('/api/v1/trip/start', {
                route_plan_id: id,
            });

            activeSession.value = responseData(response);
            isTracking.value = true;
        } catch (error) {
            return {
                ok: false,
                error: errorMessage(error, 'Не удалось начать поездку.'),
            };
        }

        const watchResult = startLocationWatch();
        return { ok: true, ...watchResult };
    }

    function startLocationWatch() {
        if (!navigator.geolocation) {
            locationError.value = 'Геолокация не поддерживается браузером.';
            isWatchingLocation.value = false;
            return { tracking: false, warning: locationError.value };
        }

        if (watchId !== null) {
            isWatchingLocation.value = true;
            return { tracking: true };
        }

        permissionDenied.value = false;
        locationError.value = '';

        watchId = navigator.geolocation.watchPosition(
            onPosition,
            onError,
            {
                enableHighAccuracy: true,
                maximumAge: 30_000,
                timeout: 15_000,
            },
        );

        isWatchingLocation.value = true;
        return { tracking: true };
    }

    async function onPosition(position) {
        const { latitude: lat, longitude: lng, accuracy } = position.coords;
        currentPos.value = { lat, lng, accuracy };

        if (!isTracking.value) return;

        try {
            const { data } = await axios.post('/api/v1/trip/location', {
                lat,
                lng,
                accuracy_m: accuracy,
                notify: false,
            }, { silent: true });

            if (Array.isArray(data.upcoming)) {
                upcomingStops.value = data.upcoming.filter(item => !item.is_rejected);
            }
            if (Array.isArray(data.system_suggestions)) {
                systemSuggestions.value = data.system_suggestions;
            }
        } catch {
            // Browser tracking should not spam the UI while the driver is moving.
        }
    }

    function onError(error) {
        if (error.code === error.PERMISSION_DENIED) {
            permissionDenied.value = true;
            locationError.value = 'Геолокация отключена. Разрешите доступ в настройках браузера.';
        } else {
            locationError.value = error.message || 'Не удалось получить геопозицию.';
        }

        isWatchingLocation.value = false;
        console.warn('[TripTracking]', error.message);
    }

    function clearLocationWatch() {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        isWatchingLocation.value = false;
    }

    async function stopTracking() {
        clearLocationWatch();

        try {
            await axios.post('/api/v1/trip/end');
        } catch {
            // The session may already be closed on another device.
        }

        isTracking.value = false;
        activeSession.value = null;
        upcomingStops.value = [];
        systemSuggestions.value = [];
        showWaypointSheet.value = false;
    }

    onUnmounted(clearLocationWatch);

    return {
        isTracking,
        isWatchingLocation,
        permissionDenied,
        locationError,
        currentPos,
        activeSession,
        activeRoutePlanId,
        isCurrentRouteActive,
        upcomingStops,
        systemSuggestions,
        showWaypointSheet,
        loadCurrent,
        startTracking,
        startLocationWatch,
        stopTracking,
        clearLocationWatch,
    };
}
