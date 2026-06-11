import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl } from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getRoutes, RoutePlan } from '@/api/routes';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

function driveTime(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h ? `${h}ч ${m}м` : `${m}м`;
}

export default function RoutesScreen() {
    const nav = useNavigation<Nav>();
    const [routes, setRoutes] = useState<RoutePlan[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true); else setLoading(true);
        try {
            setRoutes(await getRoutes());
        } catch { /* ignore */ } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useFocusEffect(useCallback(() => { load(); }, []));

    if (loading) return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;

    return (
        <View style={{ flex: 1 }}>
        <FlatList
            data={routes}
            keyExtractor={r => String(r.id)}
            style={s.root}
            contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 40 }}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
            ListEmptyComponent={
                <View style={s.empty}>
                    <Text style={s.emptyTitle}>Маршрутов нет</Text>
                    <Text style={s.emptySub}>Нажмите «+» чтобы построить первый маршрут</Text>
                </View>
            }
            renderItem={({ item }) => (
                <TouchableOpacity style={s.card} onPress={() => nav.navigate('RouteDetail', { id: item.id })}>
                    <View style={s.cardHeader}>
                        <Text style={s.cardTitle} numberOfLines={1}>{item.title}</Text>
                        <View style={[s.modeBadge, { backgroundColor: item.planning_mode === 'Безопасный' ? colors.green : colors.accent }]}>
                            <Text style={s.modeBadgeText}>{item.planning_mode}</Text>
                        </View>
                    </View>
                    <Text style={s.route} numberOfLines={1}>
                        {item.origin?.label} → {item.destination?.label}
                    </Text>
                    <View style={s.stats}>
                        <Stat label="км" value={String(item.distance_km)} />
                        <Stat label="топливо" value={`${item.fuel.needed_l} л`} />
                        <Stat label="в пути" value={driveTime(item.drive_time_minutes)} />
                        <Stat label="остановок" value={String(item.stops_count)} />
                    </View>
                    <TouchableOpacity
                        style={s.mapBtn}
                        onPress={() => nav.navigate('Map', { routeId: item.id })}
                    >
                        <Text style={s.mapBtnText}>▶ Открыть карту</Text>
                    </TouchableOpacity>
                </TouchableOpacity>
            )}
        />
        <TouchableOpacity style={s.fab} onPress={() => nav.navigate('RouteBuilder')}>
            <Text style={s.fabText}>+ Новый маршрут</Text>
        </TouchableOpacity>
        </View>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <View style={{ alignItems: 'center' }}>
            <Text style={{ fontSize: 15, fontWeight: '700', color: colors.accent, fontFamily: 'monospace' }}>{value}</Text>
            <Text style={{ fontSize: 10, color: colors.text3, marginTop: 1 }}>{label}</Text>
        </View>
    );
}

const s = StyleSheet.create({
    root:     { flex: 1, backgroundColor: colors.bg },
    center:   { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    empty:    { alignItems: 'center', marginTop: 80, gap: spacing.sm },
    emptyTitle: { fontSize: 16, fontWeight: '600', color: colors.text },
    emptySub: { fontSize: 13, color: colors.text3, textAlign: 'center' },
    card:     { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm, marginBottom: 4 },
    cardTitle:  { flex: 1, fontSize: 15, fontWeight: '700', color: colors.text },
    modeBadge:  { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    modeBadgeText: { color: '#fff', fontSize: 10, fontWeight: '700' },
    route:    { fontSize: 12, color: colors.text2 },
    stats:    { flexDirection: 'row', justifyContent: 'space-between', marginTop: spacing.md, paddingTop: spacing.md, borderTopWidth: 1, borderTopColor: colors.border },
    mapBtn:   { backgroundColor: colors.accent, borderRadius: radius.sm, paddingVertical: 10, alignItems: 'center', marginTop: spacing.md },
    mapBtnText: { color: '#fff', fontWeight: '600', fontSize: 13 },
    fab:      { position: 'absolute', bottom: 24, left: spacing.md, right: spacing.md, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', ...shadow.sm },
    fabText:  { color: '#fff', fontWeight: '700', fontSize: 14 },
});
