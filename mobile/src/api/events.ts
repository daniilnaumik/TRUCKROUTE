import client, { mediaUrl } from './client';

export interface RoadEvent {
    id: number;
    title: string;
    type: string;
    highway: string | null;
    location: string;
    description: string;
    status: 'active' | 'checking' | 'closed';
    importance: string;
    delay_minutes: number;
    confidence_score: number;
    lat: number | null;
    lng: number | null;
    reported_at: string | null;
    image: string | null;
    image_url?: string | null;
    gallery?: string[];
    video_url?: string | null;
    expires_at?: string | null;
    created_by_user_id?: number | null;
    user_vote?: 1 | -1 | 0;
    votes?: {
        up: number;
        down: number;
    };
}

function normalizeEvent(raw: any): RoadEvent {
    const coordinates = raw.coordinates ?? {};

    return {
        ...raw,
        lat: raw.lat ?? coordinates.lat ?? null,
        lng: raw.lng ?? coordinates.lng ?? null,
        image: mediaUrl(raw.image ?? raw.image_url),
        image_url: mediaUrl(raw.image_url ?? raw.image),
        gallery: Array.isArray(raw.gallery) ? raw.gallery.map(mediaUrl).filter(Boolean) as string[] : [],
        video_url: mediaUrl(raw.video_url),
        delay_minutes: Number(raw.delay_minutes ?? 0),
        confidence_score: Number(raw.confidence_score ?? 0),
        user_vote: Number(raw.user_vote ?? 0) as 1 | -1 | 0,
        votes: raw.votes ?? { up: 0, down: 0 },
    };
}

export async function getEvents(params?: {
    status?: string;
    per_page?: number;
    limit?: number;
}): Promise<RoadEvent[]> {
    const query = {
        ...params,
        limit: params?.limit ?? params?.per_page,
    };
    const { data } = await client.get('/events', { params: query });
    return (data.data ?? []).map(normalizeEvent);
}

export async function getEvent(id: number): Promise<RoadEvent> {
    const { data } = await client.get(`/events/${id}`);
    return normalizeEvent(data.data ?? data);
}

export async function voteEvent(id: number, vote: 1 | -1): Promise<RoadEvent> {
    const { data } = await client.post(`/events/${id}/vote`, { vote });
    return normalizeEvent(data.data ?? data);
}

export async function reportEvent(id: number): Promise<RoadEvent> {
    const { data } = await client.post(`/events/${id}/report`);
    return normalizeEvent(data.data ?? data);
}
