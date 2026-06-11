import React, { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { getDriverAssignments, FleetAssignment } from '@/api/fleet';
import { RootStackParamList } from '@/navigation';
import { colors, radius, shadow, spacing } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function DriverAssignmentsScreen() {
    const nav = useNavigation<Nav>();
    const [items, setItems] = useState<FleetAssignment[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true);
        else setLoading(true);

        try {
            setItems(await getDriverAssignments());
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useFocusEffect(useCallback(() => { load(); }, []));

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    const completedCount = items.filter(item => item.status === 'completed').length;
    const ratedItems = items.filter(item => item.rating_stars);
    const avgRating = ratedItems.length
        ? ratedItems.reduce((sum, item) => sum + (item.rating_stars ?? 0), 0) / ratedItems.length
        : null;

    return (
        <FlatList
            style={s.root}
            data={items}
            keyExtractor={item => String(item.id)}
            contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 32 }}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
            ListHeaderComponent={
                <View style={s.summary}>
                    <Text style={s.summaryTitle}>Мой результат</Text>
                    <View style={s.summaryRow}>
                        <View style={s.summaryItem}>
                            <Text style={s.summaryValue}>{completedCount}</Text>
                            <Text style={s.summaryLabel}>выполнено</Text>
                        </View>
                        <View style={s.summaryItem}>
                            <Text style={s.summaryValue}>{avgRating ? avgRating.toFixed(1) : '—'}</Text>
                            <Text style={s.summaryLabel}>рейтинг</Text>
                        </View>
                        <View style={s.summaryItem}>
                            <Text style={s.summaryValue}>{ratedItems.length}</Text>
                            <Text style={s.summaryLabel}>оценок</Text>
                        </View>
                    </View>
                </View>
            }
            ListEmptyComponent={
                <View style={s.empty}>
                    <Text style={s.emptyTitle}>Заданий пока нет</Text>
                    <Text style={s.emptySub}>Когда автопарк выдаст вам задание, оно появится здесь.</Text>
                </View>
            }
            renderItem={({ item }) => (
                <TouchableOpacity style={s.card} activeOpacity={0.85} onPress={() => nav.navigate('AssignmentDetail', { id: item.id })}>
                    <View style={s.cardHeader}>
                        <Text style={s.route} numberOfLines={1}>{item.origin} → {item.destination}</Text>
                        <View style={[s.statusBadge, { backgroundColor: statusColor(item.status) }]}>
                            <Text style={s.statusText}>{statusLabel(item.status)}</Text>
                        </View>
                    </View>
                    {!!item.fleet && <Text style={s.meta}>{item.fleet.name}</Text>}
                    {!!item.planned_start_at && <Text style={s.meta}>{formatDate(item.planned_start_at)}</Text>}
                    {!!item.completed_at && <Text style={s.meta}>Выполнено: {formatDate(item.completed_at)}</Text>}
                    {!!item.rating_stars && <Text style={s.rating}>Оценка: {'★'.repeat(item.rating_stars)} {item.rating_stars}/5</Text>}
                    {!!item.comment && <Text style={s.comment} numberOfLines={2}>{item.comment}</Text>}
                </TouchableOpacity>
            )}
        />
    );
}

export function statusLabel(status: string) {
    const map: Record<string, string> = {
        issued: 'Выдано',
        accepted: 'Принято',
        in_progress: 'В пути',
        completed: 'Выполнено',
        cancelled: 'Отменено',
    };
    return map[status] ?? status;
}

export function statusColor(status: string) {
    const map: Record<string, string> = {
        issued: colors.accentDark,
        accepted: colors.accent,
        in_progress: colors.green,
        completed: colors.text3,
        cancelled: colors.red,
    };
    return map[status] ?? colors.text3;
}

export function formatDate(value: string) {
    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
    summary: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm, marginBottom: spacing.sm },
    summaryTitle: { color: colors.text, fontSize: 16, fontWeight: '800', marginBottom: spacing.sm },
    summaryRow: { flexDirection: 'row' },
    summaryItem: { flex: 1 },
    summaryValue: { color: colors.accent, fontSize: 20, fontWeight: '800' },
    summaryLabel: { color: colors.text3, fontSize: 11, marginTop: 2 },
    empty: { alignItems: 'center', marginTop: 80, gap: spacing.sm },
    emptyTitle: { color: colors.text, fontSize: 16, fontWeight: '700' },
    emptySub: { color: colors.text3, fontSize: 13, textAlign: 'center' },
    card: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
    route: { flex: 1, color: colors.text, fontSize: 15, fontWeight: '700' },
    statusBadge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    statusText: { color: '#fff', fontSize: 10, fontWeight: '700' },
    meta: { color: colors.text3, fontSize: 12, marginTop: 5 },
    rating: { color: colors.accent, fontSize: 12, fontWeight: '700', marginTop: 5 },
    comment: { color: colors.text2, fontSize: 12, lineHeight: 17, marginTop: 6 },
});
