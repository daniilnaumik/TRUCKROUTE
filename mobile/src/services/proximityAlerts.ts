import * as Notifications from 'expo-notifications';
import { TripLocationResponse, TripProximityItem } from '@/api/trip';

const DEFAULT_RADIUS_KM = 2.5;

const shownKeys = new Set<string>();

export interface ProximityAlertPreferences {
    radiusKm?: number;
    preferredFuelBrand?: string;
}

export interface ProximityAlertData {
    type: 'proximity_alert';
    title: string;
    body: string;
    poi_id: number;
    poi_name: string;
    poi_type: string;
    rec_type: string;
    distance_km: number;
    eta_at?: string | null;
    lat?: number | null;
    lng?: number | null;
    brand?: string | null;
    rating?: number | null;
    services?: string | null;
    location?: string | null;
    fuel_price?: number | null;
    detour_km?: number | null;
    suggested_fuel_l?: number | null;
}

export function resetProximityAlerts() {
    shownKeys.clear();
}

export async function notifyNearbyRecommendations(
    response: TripLocationResponse | null | undefined,
    preferences: ProximityAlertPreferences = {},
): Promise<ProximityAlertData | null> {
    const alert = nearestAlert(response, preferences);
    if (!alert) return null;

    shownKeys.add(alertKey(alert));
    shownKeys.add(`poi:${alert.poi_id}`);

    await Notifications.scheduleNotificationAsync({
        content: {
            title: alert.title,
            body: alert.body,
            data: alert as unknown as Record<string, unknown>,
            sound: true,
        },
        trigger: null,
    }).catch(() => null);

    return alert;
}

function nearestAlert(
    response: TripLocationResponse | null | undefined,
    preferences: ProximityAlertPreferences,
): ProximityAlertData | null {
    if (!response) return null;

    const radiusKm = preferences.radiusKm ?? DEFAULT_RADIUS_KM;
    const items = [
        ...(response.upcoming ?? []),
        ...(response.system_suggestions ?? []),
    ]
        .map(normalizeItem)
        .filter((item): item is TripProximityItem => !!item)
        .filter(item => item.distance_km <= radiusKm)
        .filter(item => !item.is_rejected)
        .filter(item => !item.is_notified)
        .filter(item => matchesFuelBrand(item, preferences.preferredFuelBrand))
        .sort((a, b) => a.distance_km - b.distance_km);

    const item = items.find(candidate => (
        !shownKeys.has(itemKey(candidate))
        && !shownKeys.has(`poi:${candidate.service_object_id ?? candidate.id}`)
    ));
    return item ? toAlertData(item) : null;
}

function normalizeItem(item: TripProximityItem | null | undefined): TripProximityItem | null {
    if (!item?.name || item.distance_km == null) return null;

    const serviceObjectId = item.service_object_id ?? item.id;
    if (!serviceObjectId) return null;

    return {
        ...item,
        service_object_id: serviceObjectId,
        rec_type: normalizeType(item.rec_type ?? item.type),
        distance_km: Number(item.distance_km),
    };
}

function matchesFuelBrand(item: TripProximityItem, preferredBrand?: string) {
    if (!preferredBrand || preferredBrand === 'Любые') return true;
    if (normalizeType(item.rec_type ?? item.type) !== 'fuel') return true;

    const haystack = [item.brand, item.name].filter(Boolean).join(' ').toLowerCase();
    return haystack.includes(preferredBrand.toLowerCase());
}

function toAlertData(item: TripProximityItem): ProximityAlertData {
    const recType = normalizeType(item.rec_type ?? item.type);
    const label = typeLabel(recType, item.type);
    const title = `${label} рядом: ${item.distance_km.toFixed(1)} км`;
    const details = [
        item.services,
        item.location,
        item.rating != null ? `рейтинг ${item.rating}` : null,
        item.fuel_price != null ? `${item.fuel_price} ₽/л` : null,
        item.suggested_fuel_l != null ? `залить ~${item.suggested_fuel_l} л` : null,
    ].filter(Boolean).join(' · ');

    return {
        type: 'proximity_alert',
        title,
        body: `${item.name}${details ? ` — ${details}` : ''}`,
        poi_id: Number(item.service_object_id ?? item.id),
        poi_name: item.name,
        poi_type: item.type,
        rec_type: recType,
        distance_km: item.distance_km,
        eta_at: item.eta_at,
        lat: item.lat,
        lng: item.lng,
        brand: item.brand,
        rating: item.rating,
        services: item.services,
        location: item.location,
        fuel_price: item.fuel_price,
        detour_km: item.detour_km,
        suggested_fuel_l: item.suggested_fuel_l,
    };
}

function itemKey(item: TripProximityItem) {
    return `${item.recommendation_id ?? 'poi'}:${item.service_object_id ?? item.id}`;
}

function alertKey(alert: ProximityAlertData) {
    return `alert:${alert.poi_id}`;
}

function normalizeType(type?: string | null) {
    const value = (type ?? '').toLowerCase();
    if (value.includes('азс') || value.includes('fuel')) return 'fuel';
    if (value.includes('ноч') || value.includes('hotel') || value.includes('motel') || value.includes('overnight')) return 'overnight';
    if (value.includes('каф') || value.includes('food')) return 'food';
    if (value.includes('сто') || value.includes('repair')) return 'repair';
    if (value.includes('отдых') || value.includes('стоян') || value.includes('parking') || value.includes('rest')) return 'rest';
    return value || 'poi';
}

function typeLabel(normalizedType: string, rawType?: string | null) {
    if (normalizedType === 'fuel') return 'АЗС';
    if (normalizedType === 'overnight') return 'Ночлег';
    if (normalizedType === 'food') return 'Кафе';
    if (normalizedType === 'repair') return 'СТО';
    if (normalizedType === 'rest') return 'Остановка';
    return rawType || 'Объект';
}
