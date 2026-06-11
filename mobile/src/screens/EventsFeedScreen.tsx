import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { getEvents, reportEvent, RoadEvent, voteEvent } from '@/api/events';
import { RootStackParamList } from '@/navigation';
import { colors, radius, shadow, spacing } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function EventsFeedScreen() {
    const nav = useNavigation<Nav>();
    const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [events, setEvents] = useState<RoadEvent[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [notice, setNotice] = useState('');

    async function load(refresh = false) {
        if (refresh) setRefreshing(true);
        else setLoading(true);

        try {
            const data = await getEvents({ status: 'feed', limit: 50 });
            setEvents(data);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => {
        load();
        return () => {
            if (toastTimer.current) clearTimeout(toastTimer.current);
        };
    }, []);

    function showNotice(message: string) {
        setNotice(message);
        if (toastTimer.current) clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setNotice(''), 2600);
    }

    async function handleVote(event: RoadEvent, vote: 1 | -1) {
        if (event.user_vote) {
            showNotice(event.user_vote > 0
                ? 'Вы уже подтвердили это событие.'
                : 'Вы уже пожаловались на это событие.');
            return;
        }

        try {
            const fresh = vote > 0 ? await voteEvent(event.id, 1) : await reportEvent(event.id);
            setEvents(prev => prev.map(item => item.id === event.id ? fresh : item));
            showNotice(vote > 0 ? 'Событие подтверждено.' : 'Жалоба отправлена.');
        } catch (error: any) {
            showNotice(error?.response?.data?.message ?? 'Не удалось отправить действие.');
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <View style={s.root}>
            <FlatList
                data={events}
                keyExtractor={event => String(event.id)}
                contentContainerStyle={{ padding: spacing.md, gap: spacing.sm }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
                ListEmptyComponent={<Text style={s.empty}>Активных событий нет</Text>}
                renderItem={({ item }) => (
                    <TouchableOpacity style={s.card} activeOpacity={0.85} onPress={() => nav.navigate('EventDetail', { id: item.id })}>
                        <View style={s.cardTop}>
                            <View style={[s.badge, { backgroundColor: importanceColor(item.importance) }]}>
                                <Text style={s.badgeText}>{item.type}</Text>
                            </View>
                            {!!item.highway && <Text style={s.highway}>{item.highway}</Text>}
                            <Text style={s.delay}>+{item.delay_minutes} мин</Text>
                        </View>

                        <Text style={s.title}>{item.title}</Text>
                        <Text style={s.location} numberOfLines={1}>{item.location}</Text>
                        <Text style={s.desc} numberOfLines={2}>{item.description}</Text>

                        <View style={s.footer}>
                            <Text style={s.confidence}>доверие {item.confidence_score}/10</Text>
                            <View style={s.votes}>
                                <TouchableOpacity
                                    style={[s.voteBtn, item.user_vote === -1 && s.voteBtnDisabled]}
                                    onPress={(e) => { e.stopPropagation(); handleVote(item, 1); }}
                                >
                                    <Text style={s.voteBtnText}>{item.user_vote === 1 ? 'Учтено' : '✓ Да'}</Text>
                                </TouchableOpacity>
                                <TouchableOpacity
                                    style={[s.voteBtn, item.user_vote === 1 && s.voteBtnDisabled]}
                                    onPress={(e) => { e.stopPropagation(); handleVote(item, -1); }}
                                >
                                    <Text style={[s.voteBtnText, { color: colors.red }]}>{item.user_vote === -1 ? 'Жалоба' : '✕ Нет'}</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    </TouchableOpacity>
                )}
            />

            {!!notice && (
                <View style={s.toast}>
                    <Text style={s.toastText}>{notice}</Text>
                </View>
            )}
        </View>
    );
}

function importanceColor(value: string) {
    if (value === 'important' || value === 'важно' || value === 'high') return colors.red;
    if (value === 'medium' || value === 'средне') return colors.accent;
    return colors.text3;
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    empty: { color: colors.text3, textAlign: 'center', marginTop: 48 },
    card: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    cardTop: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: spacing.sm },
    badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    badgeText: { color: '#fff', fontSize: 10, fontWeight: '700', letterSpacing: 0.5 },
    highway: { fontSize: 11, color: colors.text3, fontFamily: 'monospace', flex: 1 },
    delay: { fontSize: 11, color: colors.red, fontFamily: 'monospace' },
    title: { fontSize: 14, fontWeight: '700', color: colors.text },
    location: { fontSize: 12, color: colors.text2, marginTop: 2 },
    desc: { fontSize: 12, color: colors.text2, marginTop: 4, lineHeight: 17 },
    footer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: spacing.sm },
    confidence: { fontSize: 11, color: colors.text3 },
    votes: { flexDirection: 'row', gap: spacing.sm },
    voteBtn: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: radius.sm, borderWidth: 1, borderColor: colors.border },
    voteBtnDisabled: { opacity: 0.55 },
    voteBtnText: { fontSize: 12, color: colors.green, fontWeight: '600' },
    toast: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, backgroundColor: colors.s2, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    toastText: { color: colors.text, fontSize: 13, textAlign: 'center' },
});
