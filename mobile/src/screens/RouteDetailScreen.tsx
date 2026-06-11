import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert } from 'react-native';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getRoute, RoutePlan, RouteStop } from '@/api/routes';
import { useTripStore } from '@/store/trip';
import { colors, spacing, radius, shadow } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'RouteDetail'>;

const STOP_COLOR: Record<string, string> = {
    fuel: '#c99b3a', rest: '#4a6caa', overnight: '#7a4a9e', food: colors.green,
};
const STOP_LABEL: Record<string, string> = {
    fuel: 'АЗС', rest: 'Отдых', overnight: 'Ночлег', food: 'Кафе',
};

function driveTime(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h ? `${h}ч ${m}м` : `${m}м`;
}
function formatTime(iso: string): string {
    const d = new Date(iso);
    return d.toLocaleDateString('ru', { day: '2-digit', month: '2-digit' }) + ' '
        + d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
}

function errorMessage(error: any, fallback: string) {
    const errors = error?.response?.data?.errors as Record<string, string[]> | undefined;
    return error?.response?.data?.message
        ?? Object.values(errors ?? {})[0]?.[0]
        ?? error?.message
        ?? fallback;
}

export default function RouteDetailScreen({ route: navRoute, navigation }: Props) {
    const [plan, setPlan] = useState<RoutePlan | null>(null);
    const [loading, setLoading] = useState(true);
    const trip = useTripStore();

    useEffect(() => {
        getRoute(navRoute.params.id).then(setPlan).catch(() => {}).finally(() => setLoading(false));
    }, [navRoute.params.id]);

    if (loading) return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    if (!plan)   return <View style={s.center}><Text style={s.err}>Маршрут не найден</Text></View>;

    return (
        <ScrollView style={s.root} contentContainerStyle={{ padding: spacing.md, paddingBottom: 40 }}>
            {/* Header stats */}
            <View style={s.hero}>
                <Text style={s.heroTitle} numberOfLines={2}>{plan.title}</Text>
                <Text style={s.heroRoute}>{plan.origin?.label} → {plan.destination?.label}</Text>
                <View style={s.stats}>
                    {[
                        { v: String(plan.distance_km), l: 'км' },
                        { v: driveTime(plan.drive_time_minutes), l: 'в пути' },
                        { v: `${plan.fuel.needed_l}`, l: 'л топлива' },
                        { v: String(plan.stops_count), l: 'остановок' },
                    ].map(s => (
                        <View key={s.l} style={{ alignItems: 'center' }}>
                            <Text style={st.statVal}>{s.v}</Text>
                            <Text style={st.statLabel}>{s.l}</Text>
                        </View>
                    ))}
                </View>
            </View>

            {/* Actions */}
            <View style={s.actions}>
                <TouchableOpacity style={s.mapBtn} onPress={() => navigation.navigate('Map', { routeId: plan.id })}>
                    <Text style={s.mapBtnText}>🗺 Открыть карту</Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={[s.tripBtn, trip.session?.status === 'active' && { backgroundColor: colors.red }]}
                    onPress={async () => {
                        try {
                            if (trip.session?.status === 'active') {
                                await trip.end();
                            } else {
                                await trip.start(plan.id, plan);
                                navigation.navigate('Map', { routeId: plan.id });
                            }
                        } catch (error: any) {
                            Alert.alert('Ошибка', errorMessage(error, 'Не удалось изменить состояние поездки.'));
                        }
                    }}
                >
                    <Text style={s.tripBtnText}>
                        {trip.session?.status === 'active' ? '■ Завершить' : '▶ Начать'}
                    </Text>
                </TouchableOpacity>
            </View>

            {/* Timeline */}
            <Text style={s.sectionTitle}>Маршрут</Text>
            <View style={s.timeline}>
                <TimelineItem label="Отправление" desc={plan.origin?.label ?? ''} color={colors.green}
                    meta={plan.start_time ? formatTime(plan.start_time) : undefined} />

                {plan.stops?.map((stop, i) => (
                    <TimelineItem
                        key={stop.id}
                        label={`${STOP_LABEL[stop.type] ?? stop.type} · ${stop.distance_from_start_km} км`}
                        desc={stop.poi?.name ?? stop.note ?? ''}
                        color={STOP_COLOR[stop.type] ?? colors.text3}
                        meta={stop.eta_at ? `ETA: ${formatTime(stop.eta_at)}` : undefined}
                        extra={stop.suggested_fuel_l ? `Залить ~${stop.suggested_fuel_l} л` : undefined}
                    />
                ))}

                <TimelineItem label="Прибытие" desc={plan.destination?.label ?? ''} color={colors.red}
                    meta={plan.arrival_time ? formatTime(plan.arrival_time) : undefined} />
            </View>

            {/* Recommendations */}
            {plan.recommendations_text && (
                <>
                    <Text style={s.sectionTitle}>Рекомендации</Text>
                    <View style={s.recCard}>
                        <Text style={s.recText}>{plan.recommendations_text}</Text>
                    </View>
                </>
            )}
        </ScrollView>
    );
}

function TimelineItem({ label, desc, color, meta, extra }: {
    label: string; desc: string; color: string; meta?: string; extra?: string;
}) {
    return (
        <View style={tl.item}>
            <View style={[tl.dot, { backgroundColor: color }]} />
            <View style={tl.content}>
                <Text style={tl.label}>{label}</Text>
                <Text style={tl.desc} numberOfLines={2}>{desc}</Text>
                {meta  && <Text style={tl.meta}>{meta}</Text>}
                {extra && <Text style={tl.extra}>{extra}</Text>}
            </View>
        </View>
    );
}

const s = StyleSheet.create({
    root:   { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    err:    { color: colors.text3 },
    hero:   { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm, marginBottom: spacing.md },
    heroTitle: { fontSize: 17, fontWeight: '700', color: colors.text },
    heroRoute: { fontSize: 12, color: colors.text2, marginTop: 4 },
    stats:  { flexDirection: 'row', justifyContent: 'space-between', marginTop: spacing.md, paddingTop: spacing.md, borderTopWidth: 1, borderTopColor: colors.border },
    actions: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.lg },
    mapBtn: { flex: 1, backgroundColor: colors.s1, borderRadius: radius.sm, paddingVertical: 12, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    mapBtnText: { fontSize: 13, fontWeight: '600', color: colors.accent },
    tripBtn: { flex: 1, backgroundColor: colors.accent, borderRadius: radius.sm, paddingVertical: 12, alignItems: 'center' },
    tripBtnText: { fontSize: 13, fontWeight: '700', color: '#fff' },
    sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.text, marginBottom: spacing.md },
    timeline: { gap: 0 },
    recCard: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginTop: spacing.sm },
    recText: { fontSize: 13, color: colors.text2, lineHeight: 19 },
});

const st = StyleSheet.create({
    statVal:   { fontSize: 16, fontWeight: '700', color: colors.accent, fontFamily: 'monospace' },
    statLabel: { fontSize: 10, color: colors.text3, marginTop: 2 },
});

const tl = StyleSheet.create({
    item:    { flexDirection: 'row', gap: spacing.md, paddingBottom: spacing.md, marginLeft: spacing.sm },
    dot:     { width: 20, height: 20, borderRadius: 10, marginTop: 2, flexShrink: 0 },
    content: { flex: 1, paddingBottom: spacing.md, borderBottomWidth: 1, borderBottomColor: colors.border },
    label:   { fontSize: 12, color: colors.text3, fontWeight: '600', letterSpacing: 0.3 },
    desc:    { fontSize: 13, fontWeight: '600', color: colors.text, marginTop: 2 },
    meta:    { fontSize: 11, color: colors.accent, marginTop: 3, fontFamily: 'monospace' },
    extra:   { fontSize: 11, color: colors.green, marginTop: 2, fontFamily: 'monospace' },
});
