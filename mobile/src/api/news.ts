import client, { mediaUrl } from './client';

export interface NewsArticle {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    image: string | null;
    image_url: string | null;
    gallery: string[];
    tags: string[];
    status: string;
    published_at: string | null;
    created_at: string | null;
    author?: {
        id: number;
        name: string;
    } | null;
}

function normalizeArticle(raw: any): NewsArticle {
    return {
        ...raw,
        excerpt: raw.excerpt ?? null,
        content: raw.content ?? null,
        image: mediaUrl(raw.image_url ?? raw.image),
        image_url: mediaUrl(raw.image_url ?? raw.image),
        gallery: Array.isArray(raw.gallery) ? raw.gallery.map(mediaUrl).filter(Boolean) as string[] : [],
        tags: Array.isArray(raw.tags) ? raw.tags : [],
        author: raw.author ?? null,
    };
}

export async function getNews(params?: { per_page?: number; page?: number }): Promise<NewsArticle[]> {
    const { data } = await client.get('/news', { params });
    return (data.data ?? []).map(normalizeArticle);
}

export async function getNewsArticle(slug: string): Promise<NewsArticle> {
    const { data } = await client.get(`/news/${slug}`);
    return normalizeArticle(data.data ?? data);
}
