import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Image,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getNews, NewsArticle } from '@/api/news';
import { getEvents, RoadEvent } from '@/api/events';
import { colors, radius, shadow, spacing } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;
type Mode = 'articles' | 'events';

export default function NewsScreen() {
    const nav = useNavigation<Nav>();
    const [mode, setMode] = useState<Mode>('articles');
    const [articles, setArticles] = useState<NewsArticle[]>([]);
    const [events, setEvents] = useState<RoadEvent[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true);
        else setLoading(true);

        try {
            const [nextArticles, nextEvents] = await Promise.all([
                getNews({ per_page: 50 }),
                getEvents({ status: 'feed', limit: 50 }),
            ]);
            setArticles(nextArticles);
            setEvents(nextEvents);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => { load(); }, []);

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <ScrollView
            style={s.root}
            contentContainerStyle={s.content}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
        >
            <View style={s.segment}>
                <TouchableOpacity style={[s.segmentBtn, mode === 'articles' && s.segmentBtnActive]} onPress={() => setMode('articles')}>
                    <Text style={[s.segmentText, mode === 'articles' && s.segmentTextActive]}>Статьи</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[s.segmentBtn, mode === 'events' && s.segmentBtnActive]} onPress={() => setMode('events')}>
                    <Text style={[s.segmentText, mode === 'events' && s.segmentTextActive]}>События</Text>
                </TouchableOpacity>
            </View>

            {mode === 'articles' && (
                <View style={s.list}>
                    {articles.length === 0 ? (
                        <Text style={s.empty}>Статей пока нет</Text>
                    ) : articles.map(article => (
                        <TouchableOpacity
                            key={article.id}
                            style={s.card}
                            activeOpacity={0.85}
                            onPress={() => nav.navigate('NewsDetail', { slug: article.slug })}
                        >
                            <MediaPreview url={article.image_url} />
                            <View style={s.cardBody}>
                                <TagRow tags={article.tags} />
                                <Text style={s.title}>{article.title}</Text>
                                {!!article.excerpt && <Text style={s.desc} numberOfLines={2}>{stripTags(article.excerpt)}</Text>}
                                <Text style={s.meta}>{article.author?.name ?? 'TruckRoute'} · {formatDate(article.published_at ?? article.created_at)}</Text>
                            </View>
                        </TouchableOpacity>
                    ))}
                </View>
            )}

            {mode === 'events' && (
                <View style={s.list}>
                    {events.length === 0 ? (
                        <Text style={s.empty}>Активных событий пока нет</Text>
                    ) : events.map(event => (
                        <TouchableOpacity
                            key={event.id}
                            style={s.card}
                            activeOpacity={0.85}
                            onPress={() => nav.navigate('EventDetail', { id: event.id })}
                        >
                            <MediaPreview url={event.image_url ?? event.image} />
                            <View style={s.cardBody}>
                                <View style={s.eventTop}>
                                    <View style={[s.badge, { backgroundColor: importanceColor(event.importance) }]}>
                                        <Text style={s.badgeText}>{event.type}</Text>
                                    </View>
                                    {!!event.highway && <Text style={s.highway}>{event.highway}</Text>}
                                </View>
                                <Text style={s.title}>{event.title}</Text>
                                <Text style={s.desc} numberOfLines={2}>{event.description}</Text>
                                <Text style={s.meta}>{event.location} · {formatDate(event.reported_at)}</Text>
                            </View>
                        </TouchableOpacity>
                    ))}
                </View>
            )}
        </ScrollView>
    );
}

function MediaPreview({ url }: { url?: string | null }) {
    if (!url) {
        return (
            <View style={s.noMedia}>
                <Text style={s.noMediaText}>Пользователь не приложил фото или видео</Text>
            </View>
        );
    }

    return <Image source={{ uri: url }} style={s.image} resizeMode="cover" />;
}

function TagRow({ tags }: { tags: string[] }) {
    if (!tags.length) return null;

    return (
        <View style={s.tags}>
            {tags.slice(0, 3).map(tag => (
                <View key={tag} style={s.tag}>
                    <Text style={s.tagText}>{tag}</Text>
                </View>
            ))}
        </View>
    );
}

function formatDate(value?: string | null) {
    if (!value) return 'только что';
    return new Date(value).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' });
}

function stripTags(value: string) {
    return value.replace(/<[^>]*>/g, '').trim();
}

function importanceColor(value: string) {
    if (value === 'important' || value === 'важно' || value === 'high') return colors.red;
    if (value === 'medium' || value === 'средне') return colors.accent;
    return colors.text3;
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
    content: { padding: spacing.md, paddingBottom: 32, gap: spacing.md },
    segment: { flexDirection: 'row', gap: spacing.sm, backgroundColor: colors.s1, borderRadius: radius.md, padding: 4, borderWidth: 1, borderColor: colors.border },
    segmentBtn: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: radius.sm },
    segmentBtnActive: { backgroundColor: colors.accent },
    segmentText: { color: colors.text2, fontSize: 13, fontWeight: '600' },
    segmentTextActive: { color: '#fff' },
    list: { gap: spacing.sm },
    empty: { color: colors.text3, textAlign: 'center', marginTop: 48 },
    card: { backgroundColor: colors.s1, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, overflow: 'hidden', ...shadow.sm },
    image: { width: '100%', height: 150, backgroundColor: colors.s2 },
    noMedia: { height: 150, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.s2 },
    noMediaText: { color: colors.text3, fontSize: 12 },
    cardBody: { padding: spacing.md, gap: 7 },
    tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
    tag: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    tagText: { color: colors.text3, fontSize: 10, textTransform: 'uppercase' },
    eventTop: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
    badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    badgeText: { color: '#fff', fontSize: 10, fontWeight: '700' },
    highway: { color: colors.text3, fontSize: 11, fontFamily: 'monospace' },
    title: { color: colors.text, fontSize: 16, fontWeight: '700' },
    desc: { color: colors.text2, fontSize: 13, lineHeight: 18 },
    meta: { color: colors.text3, fontSize: 11, marginTop: 2 },
});
