import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Image, ScrollView, StyleSheet, Text, View } from 'react-native';
import { RouteProp, useRoute } from '@react-navigation/native';
import { RootStackParamList } from '@/navigation';
import { getNewsArticle, NewsArticle } from '@/api/news';
import { colors, radius, spacing } from '@/theme';

type Route = RouteProp<RootStackParamList, 'NewsDetail'>;

export default function NewsDetailScreen() {
    const route = useRoute<Route>();
    const [article, setArticle] = useState<NewsArticle | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        getNewsArticle(route.params.slug)
            .then(setArticle)
            .finally(() => setLoading(false));
    }, [route.params.slug]);

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    if (!article) {
        return <View style={s.center}><Text style={s.empty}>Статья не найдена</Text></View>;
    }

    return (
        <ScrollView style={s.root} contentContainerStyle={s.content}>
            <View>
                <TagRow tags={article.tags} />
                <Text style={s.title}>{article.title}</Text>
                <Text style={s.meta}>{article.author?.name ?? 'TruckRoute'} · {formatDate(article.published_at ?? article.created_at)}</Text>
            </View>

            <MediaBlock url={article.image_url} />

            {!!article.excerpt && <Text style={s.lead}>{stripTags(article.excerpt)}</Text>}
            {!!article.content && <Text style={s.body}>{stripTags(article.content)}</Text>}

            {article.gallery.length > 0 && (
                <View style={s.gallery}>
                    {article.gallery.map((url, index) => (
                        <Image key={`${url}-${index}`} source={{ uri: url }} style={s.galleryImage} resizeMode="cover" />
                    ))}
                </View>
            )}
        </ScrollView>
    );
}

function MediaBlock({ url }: { url?: string | null }) {
    if (!url) {
        return (
            <View style={s.noMedia}>
                <Text style={s.noMediaText}>Пользователь не приложил фото или видео</Text>
            </View>
        );
    }

    return <Image source={{ uri: url }} style={s.cover} resizeMode="cover" />;
}

function TagRow({ tags }: { tags: string[] }) {
    if (!tags.length) return null;

    return (
        <View style={s.tags}>
            {tags.map(tag => (
                <View key={tag} style={s.tag}>
                    <Text style={s.tagText}>{tag}</Text>
                </View>
            ))}
        </View>
    );
}

function formatDate(value?: string | null) {
    if (!value) return 'только что';
    return new Date(value).toLocaleDateString('ru-RU', { day: '2-digit', month: 'long', year: 'numeric' });
}

function stripTags(value: string) {
    return value
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n\n')
        .replace(/<[^>]*>/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
    content: { padding: spacing.md, paddingBottom: 40, gap: spacing.md },
    empty: { color: colors.text3 },
    tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: spacing.sm },
    tag: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    tagText: { color: colors.text3, fontSize: 10, textTransform: 'uppercase' },
    title: { color: colors.text, fontSize: 28, fontWeight: '800', lineHeight: 34 },
    meta: { color: colors.text3, fontSize: 12, marginTop: spacing.sm },
    cover: { width: '100%', height: 230, borderRadius: radius.md, backgroundColor: colors.s2 },
    noMedia: { height: 190, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    noMediaText: { color: colors.text3, fontSize: 12 },
    lead: { color: colors.text2, fontSize: 15, lineHeight: 22 },
    body: { color: colors.text, fontSize: 15, lineHeight: 23 },
    gallery: { gap: spacing.sm },
    galleryImage: { width: '100%', height: 190, borderRadius: radius.md, backgroundColor: colors.s2 },
});
