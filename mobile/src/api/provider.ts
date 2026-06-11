import client from './client';

export interface ProviderPoi {
    id: number;
    name: string;
    type: string;
    highway: string | null;
    km_marker: number | null;
    brand: string | null;
    lat: number;
    lng: number;
    location: string;
    description: string | null;
    services: string | null;
    fuel_price: number | null;
    has_truck_parking: boolean;
    detour_km: number | null;
    status: 'pending' | 'active' | 'rejected';
    verified: boolean;
    rating: number;
    view_count: number;
}

export interface PoiPayload {
    name: string;
    type: string;
    lat: number;
    lng: number;
    location: string;
    description?: string;
    services?: string;
    fuel_price?: number;
    has_truck_parking?: boolean;
    highway?: string;
    km_marker?: number;
    brand?: string;
    detour_km?: number;
}

const TYPE_TO_API: Record<string, string> = {
    fuel: 'АЗС',
    parking: 'Стоянка',
    motel: 'Ночлег',
    hotel: 'Ночлег',
    food: 'Еда',
    repair: 'СТО',
};

const TYPE_FROM_API: Record<string, string> = {
    АЗС: 'fuel',
    Стоянка: 'parking',
    Ночлег: 'motel',
    Еда: 'food',
    СТО: 'repair',
    fuel: 'fuel',
    parking: 'parking',
    motel: 'motel',
    hotel: 'hotel',
    food: 'food',
    repair: 'repair',
};

const STATUS_FROM_API: Record<string, ProviderPoi['status']> = {
    moderation: 'pending',
    pending: 'pending',
    active: 'active',
    rejected: 'rejected',
};

function normalizePoi(raw: any): ProviderPoi {
    const coordinates = raw.coordinates ?? {};

    return {
        ...raw,
        type: TYPE_FROM_API[raw.type] ?? raw.type,
        lat: Number(raw.lat ?? coordinates.lat ?? 0),
        lng: Number(raw.lng ?? coordinates.lng ?? 0),
        services: Array.isArray(raw.services) ? raw.services.join(', ') : (raw.services ?? null),
        status: STATUS_FROM_API[raw.status] ?? 'pending',
        fuel_price: raw.fuel_price !== null && raw.fuel_price !== undefined ? Number(raw.fuel_price) : null,
        detour_km: raw.detour_km !== null && raw.detour_km !== undefined ? Number(raw.detour_km) : null,
        rating: raw.rating !== null && raw.rating !== undefined ? Number(raw.rating) : 0,
        view_count: Number(raw.view_count ?? 0),
        has_truck_parking: Boolean(raw.has_truck_parking),
        verified: Boolean(raw.verified),
    };
}

function serializePoiPayload(payload: Partial<PoiPayload>) {
    return {
        ...payload,
        type: payload.type ? (TYPE_TO_API[payload.type] ?? payload.type) : payload.type,
        km_marker: payload.km_marker !== undefined ? Math.round(Number(payload.km_marker)) : undefined,
    };
}

export async function getProviderPois(): Promise<ProviderPoi[]> {
    const { data } = await client.get('/provider/poi');
    return (data.data ?? []).map(normalizePoi);
}

export async function createPoi(payload: PoiPayload): Promise<ProviderPoi> {
    const { data } = await client.post('/provider/poi', serializePoiPayload(payload));
    return normalizePoi(data.data ?? data);
}

export async function updatePoi(id: number, payload: Partial<PoiPayload>): Promise<ProviderPoi> {
    const { data } = await client.put(`/provider/poi/${id}`, serializePoiPayload(payload));
    return normalizePoi(data.data ?? data);
}

export async function deletePoi(id: number): Promise<void> {
    await client.delete(`/provider/poi/${id}`);
}

export async function getPoiStats(id: number): Promise<{ views: number; favorites: number }> {
    const { data } = await client.get(`/provider/poi/${id}/stats`);
    return data.data ?? data;
}
