import React, { useCallback, useState } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    FlatList, ActivityIndicator, Image,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { useAuthStore } from '@/store/auth';
import { useTripStore } from '@/store/trip';
import { getRoutes, RoutePlan } from '@/api/routes';
import { getEvents, RoadEvent } from '@/api/events';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function HomeScreen() {
    const auth     = useAuthStore();
    const tripSession = useTripStore(state => state.session);
    const loadCurrentTrip = useTripStore(state => state.loadCurrent);
    const nav      = useNavigation<Nav>();
    const role = auth.user?.role;
    const canUseRoutes = role !== 'fleet' && role !== 'provider';

    const [routes, setRoutes] = useState<RoutePlan[]>([]);
    const [events, setEvents] = useState<RoadEvent[]>([]);
    const [loading, setLoading] = useState(true);

    const load = useCallback(() => {
        setLoading(true);
        Promise.all([
            canUseRoutes ? getRoutes().then(r => setRoutes(r.slice(0, 3))).catch(() => {}) : Promise.resolve(setRoutes([])),
            getEvents({ status: 'feed', limit: 20 }).then(items => setEvents(selectUrgentEvents(items))).catch(() => {}),
        ]).finally(() => setLoading(false));

        loadCurrentTrip();
    }, [canUseRoutes, loadCurrentTrip]);

    useFocusEffect(load);

    return (
        <ScrollView style={s.root} contentContainerStyle={{ paddingBottom: 32 }}>
            {/* Active trip banner */}
            {tripSession?.status === 'active' && (
                <TouchableOpacity style={s.tripBanner} onPress={() => nav.navigate('Map', { routeId: tripSession.route_plan_id ?? undefined })}>
                    <View>
                        <Text style={s.tripBannerTitle}>● Поездка активна</Text>
                        <Text style={s.tripBannerSub}>Нажмите для открытия карты</Text>
                    </View>
                    <Text style={s.tripBannerArrow}>→</Text>
                </TouchableOpacity>
            )}

            {/* Hero */}
            <View style={s.hero}>
                <Text style={s.heroKicker}>информационная система для рейса</Text>
                <Text style={s.heroTitle}>TruckRoute</Text>
                <Text style={s.heroSub}>
                    {role === 'fleet'
                        ? 'Управляйте автопарком, водителями и выданными заданиями.'
                        : role === 'provider'
                            ? 'Управляйте объектами на трассе и заявками по ним.'
                        : 'Планируйте рейс с учётом топлива, параметров грузовика и дорожных событий.'}
                </Text>
                <TouchableOpacity
                    style={s.heroBtn}
                    onPress={() => role === 'fleet' ? nav.navigate('FleetList') : role === 'provider' ? nav.navigate('ProviderPlaces') : nav.navigate('RouteBuilder')}
                >
                    <Text style={s.heroBtnText}>{role === 'fleet' ? 'Открыть автопарк' : role === 'provider' ? 'Мои объекты' : 'Построить маршрут'}</Text>
                </TouchableOpacity>
            </View>

            {/* Recent routes */}
            {canUseRoutes && (
                <View style={s.section}>
                    <Text style={s.sectionTitle}>Последние маршруты</Text>
                    {loading
                        ? <ActivityIndicator color={colors.accent} style={{ marginTop: 16 }} />
                        : routes.length === 0
                            ? <Text style={s.empty}>Маршрутов нет — постройте первый</Text>
                            : <FlatList
                                data={routes}
                                keyExtractor={r => String(r.id)}
                                horizontal
                                showsHorizontalScrollIndicator={false}
                                contentContainerStyle={{ paddingLeft: spacing.md, gap: spacing.md }}
                                renderItem={({ item }) => (
                                    <TouchableOpacity style={s.routeCard} onPress={() => nav.navigate('RouteDetail', { id: item.id })}>
                                        <Text style={s.routeTitle} numberOfLines={1}>{item.title}</Text>
                                        <Text style={s.routeMeta}>{item.distance_km} км · {item.fuel.needed_l} л</Text>
                                    </TouchableOpacity>
                                )}
                            />
                    }
                </View>
            )}

            {/* Recent events */}
            <View style={s.section}>
                <Text style={s.sectionTitle}>Срочные события</Text>
                {events.length === 0 && !loading
                    ? <Text style={s.empty}>Сейчас нет срочных событий</Text>
                    : events.map(ev => (
                        <TouchableOpacity key={ev.id} style={s.eventCard} activeOpacity={0.85} onPress={() => nav.navigate('EventDetail', { id: ev.id })}>
                            <View style={[s.eventBadge, { backgroundColor: importanceColor(ev.importance) }]}>
                                <Text style={s.eventBadgeText}>{ev.importance}</Text>
                            </View>
                            <Text style={s.eventTitle} numberOfLines={1}>{ev.title}</Text>
                            <Text style={s.eventMeta} numberOfLines={1}>{ev.highway} · {ev.location}</Text>
                            <Text style={s.eventReason} numberOfLines={1}>{urgentReason(ev)}</Text>
                        </TouchableOpacity>
                    ))
                }
            </View>
        </ScrollView>
    );
}

function selectUrgentEvents(items: RoadEvent[]) {
    return items
        .filter(isUrgentEvent)
        .sort((a, b) => urgencyScore(b) - urgencyScore(a))
        .slice(0, 3);
}

function isUrgentEvent(ev: RoadEvent) {
    return urgencyScore(ev) >= 3;
}

function urgencyScore(ev: RoadEvent) {
    let score = 0;
    if (['high', 'important', 'важно', 'высокий'].includes(String(ev.importance).toLowerCase())) score += 4;
    if ((ev.delay_minutes ?? 0) >= 30) score += 3;
    else if ((ev.delay_minutes ?? 0) >= 15) score += 1;
    if ((ev.confidence_score ?? 0) >= 6) score += 2;
    return score;
}

function urgentReason(ev: RoadEvent) {
    if ((ev.delay_minutes ?? 0) >= 30) return `Задержка около ${ev.delay_minutes} мин`;
    if (['high', 'important', 'важно', 'высокий'].includes(String(ev.importance).toLowerCase())) return 'Высокая важность для рейса';
    if ((ev.confidence_score ?? 0) >= 6) return `Подтверждено: ${ev.confidence_score}/10`;
    return 'Требует внимания';
}

function importanceColor(imp: string): string {
    const value = String(imp).toLowerCase();
    if (['high', 'important', 'важно', 'высокий'].includes(value)) return colors.red;
    if (['medium', 'средне', 'средний'].includes(value)) return colors.accent;
    return colors.text3;
}

const s = StyleSheet.create({
    root:       { flex: 1, backgroundColor: colors.bg },
    tripBanner: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: colors.green, paddingHorizontal: spacing.lg, paddingVertical: spacing.md, margin: spacing.md, borderRadius: radius.md },
    tripBannerTitle: { color: '#fff', fontWeight: '700', fontSize: 14 },
    tripBannerSub:   { color: 'rgba(255,255,255,0.8)', fontSize: 12, marginTop: 2 },
    tripBannerArrow: { color: '#fff', fontSize: 20 },
    hero:       { backgroundColor: colors.s1, margin: spacing.md, borderRadius: radius.md, padding: spacing.xl, ...shadow.sm },
    heroKicker: { fontSize: 11, color: colors.text3, letterSpacing: 1, textTransform: 'uppercase' },
    heroTitle:  { fontSize: 28, fontWeight: '700', color: colors.accent, marginTop: spacing.sm, letterSpacing: 2 },
    heroSub:    { fontSize: 14, color: colors.text2, marginTop: spacing.sm, lineHeight: 20 },
    heroBtn:    { backgroundColor: colors.accent, borderRadius: radius.sm, paddingVertical: 12, paddingHorizontal: spacing.lg, marginTop: spacing.lg, alignSelf: 'flex-start' },
    heroBtnText:{ color: '#fff', fontWeight: '600', fontSize: 14 },
    section:    { marginHorizontal: spacing.md, marginTop: spacing.lg },
    sectionTitle: { fontSize: 17, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
    empty:      { fontSize: 13, color: colors.text3, marginTop: spacing.sm },
    routeCard:  { width: 180, backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    routeTitle: { fontSize: 13, fontWeight: '600', color: colors.text },
    routeMeta:  { fontSize: 11, color: colors.text3, marginTop: 4, fontFamily: 'monospace' },
    eventCard:  { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm, ...shadow.sm },
    eventBadge: { borderRadius: radius.sm, paddingHorizontal: 8, paddingVertical: 3, alignSelf: 'flex-start', marginBottom: 6 },
    eventBadgeText: { fontSize: 10, color: '#fff', fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
    eventTitle: { fontSize: 13, fontWeight: '600', color: colors.text },
    eventMeta:  { fontSize: 11, color: colors.text3, marginTop: 3 },
    eventReason: { fontSize: 11, color: colors.accent, marginTop: 4 },
});
