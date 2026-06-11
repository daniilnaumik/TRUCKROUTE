import { ref } from 'vue';
import { loadYandexMaps } from './yandexMaps';

/**
 * Debounced geocoding через Яндекс JS API.
 * Возвращает до `limit` подсказок: { lat, lon, label }.
 * Не ходит на бэкенд — работает напрямую из браузера, использует JS API ключ
 * (загружен в spa.blade.php). Бэкенд /geo/geocode оставлен как fallback.
 */
export function useGeocode(delayMs = 400, limit = 6) {
    const suggestions = ref([]);
    const loading     = ref(false);
    const noResult    = ref(false);
    let timer = null;
    let reqSeq = 0;

    async function backendGeocode(query) {
        const url = new URL('/api/v1/geo/geocode', window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', String(limit));

        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return [];

        const data = await response.json();
        return (data.results ?? []).map((item) => ({
            lat: item.lat,
            lon: item.lon ?? item.lng,
            label: item.label,
        })).filter((item) => item.lat != null && item.lon != null);
    }

    function search(query) {
        clearTimeout(timer);
        suggestions.value = [];
        noResult.value    = false;
        if (!query || query.length < 3) return;

        const mySeq = ++reqSeq;
        timer = setTimeout(async () => {
            loading.value = true;
            try {
                const items = [];

                try {
                    const ymaps = await loadYandexMaps();
                    const res = await ymaps.geocode(query, { results: limit });
                    if (mySeq !== reqSeq) return; // stale response, ignore

                    res.geoObjects.each(obj => {
                        const [lat, lng] = obj.geometry.getCoordinates();
                        items.push({
                            lat,
                            lon: lng,
                            label: obj.getAddressLine() || obj.properties.get('name') || query,
                        });
                    });
                } catch (err) {
                    console.warn('ymaps geocode failed, falling back to backend:', err);
                }

                if (!items.length) {
                    items.push(...await backendGeocode(query));
                    if (mySeq !== reqSeq) return;
                }

                suggestions.value = items;
                noResult.value    = items.length === 0;
            } catch (err) {
                if (mySeq !== reqSeq) return;
                console.warn('ymaps geocode failed:', err);
                suggestions.value = [];
                noResult.value = true;
            } finally {
                if (mySeq === reqSeq) loading.value = false;
            }
        }, delayMs);
    }

    function clear() {
        suggestions.value = [];
        noResult.value    = false;
        clearTimeout(timer);
    }

    return { suggestions, loading, noResult, search, clear };
}
