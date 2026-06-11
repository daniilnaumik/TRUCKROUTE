import client from './client';

export interface TripSession {
    id: number;
    status: 'active' | 'paused' | 'ended';
    route_plan_id: number | null;
    started_at: string;
    last_lat: number | null;
    last_lng: number | null;
    last_location_at: string | null;
}

export interface TripProximityItem {
    id?: number;
    recommendation_id?: number;
    service_object_id?: number;
    name: string;
    type: string;
    rec_type?: string;
    lat?: number | null;
    lng?: number | null;
    distance_km: number;
    eta_at?: string | null;
    detour_km?: number | null;
    fuel_before_l?: number | null;
    suggested_fuel_l?: number | null;
    services?: string | null;
    location?: string | null;
    brand?: string | null;
    rating?: number | null;
    fuel_price?: number | null;
    has_truck_parking?: boolean;
    is_notified?: boolean;
    is_rejected?: boolean;
}

export interface TripLocationResponse {
    message: string;
    upcoming: TripProximityItem[];
    system_suggestions: TripProximityItem[];
    has_proximity_alert: boolean;
}

export async function startTrip(routePlanId?: number): Promise<TripSession> {
    const { data } = await client.post('/trip/start', { route_plan_id: routePlanId ?? null });
    return data.data;
}

export async function updateLocation(
    lat: number,
    lng: number,
    meta: { accuracy_m?: number | null; speed_kmh?: number | null } = {},
): Promise<TripLocationResponse> {
    const { data } = await client.post('/trip/location', { lat, lng, ...meta });
    return data;
}

export async function endTrip(): Promise<void> {
    await client.post('/trip/end');
}

export async function currentTrip(): Promise<TripSession | null> {
    const { data } = await client.get('/trip/current');
    return data.data ?? null;
}
