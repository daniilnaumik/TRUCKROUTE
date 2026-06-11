import client from './client';
import { GeoPoint } from './fleet';

export interface RouteGeometry {
    distance_m: number;
    distance_km: number;
    duration_s: number;
    duration_min: number;
    polyline: [number, number][];
    provider?: string | null;
}

export async function geocodeAddress(query: string): Promise<GeoPoint | null> {
    const { data } = await client.get('/geo/geocode', { params: { q: query, limit: 1 } });
    const result = data.results?.[0];

    if (!result) return null;

    return {
        lat: Number(result.lat),
        lng: Number(result.lng),
        label: result.label,
    };
}

export async function buildGeoRoute(from: GeoPoint, to: GeoPoint, via: GeoPoint[] = []): Promise<RouteGeometry> {
    const { data } = await client.post('/geo/route', { from, to, via });
    return data;
}
