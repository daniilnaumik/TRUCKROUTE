import client from './client';

export interface GeoPoint {
    lat: number;
    lng: number;
    label?: string;
}

export interface RoutePlan {
    id: number;
    title: string;
    origin: { label: string; point: GeoPoint | null };
    destination: { label: string; point: GeoPoint | null };
    via_points: GeoPoint[];
    start_time: string | null;
    arrival_time: string | null;
    distance_km: number;
    drive_time_minutes: number;
    stops_count: number;
    planning_mode: string;
    fuel: {
        start_l: number;
        needed_l: number;
        reserve_percent: number;
    };
    route: {
        polyline: [number, number][];
        provider: string;
    };
    stops: RouteStop[];
    recommendations_text: string;
    image_url: string | null;
}

export interface RouteStop {
    id: number;
    type: 'fuel' | 'rest' | 'overnight' | 'food' | 'АЗС' | 'Отдых' | 'Ночлег' | 'Кафе' | 'route_stop' | 'optional_stop' | string;
    order_index: number;
    distance_from_start_km: number;
    detour_km: number;
    eta_at: string | null;
    fuel_before_l: number | null;
    suggested_fuel_l: number | null;
    note: string | null;
    poi: {
        id: number;
        name: string;
        type: string;
        lat: number;
        lng: number;
        services: string;
        rating: number;
        brand?: string | null;
        location?: string | null;
        fuel_price?: number | null;
        has_truck_parking?: boolean;
    } | null;
}

export async function getRoutes(): Promise<RoutePlan[]> {
    const { data } = await client.get('/routes');
    return data.data ?? [];
}

export async function getRoute(id: number): Promise<RoutePlan> {
    const { data } = await client.get(`/routes/${id}`);
    return data.data ?? data;
}

export async function buildRoute(payload: object): Promise<RoutePlan> {
    const { data } = await client.post('/routes', payload);
    return data.data ?? data;
}

export async function deleteRoute(id: number): Promise<void> {
    await client.delete(`/routes/${id}`);
}
