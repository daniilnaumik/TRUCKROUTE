import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    Animated,
    Dimensions,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { colors, radius, shadow, spacing } from '@/theme';

export interface ProximityData {
    _key?: string;
    receivedAt?: number;
    title: string;
    body: string;
    poi_name: string;
    poi_type: string;
    distance_km: number;
    rec_type: string;
    eta_at?: string;
    lat?: number | null;
    lng?: number | null;
    poi_lat?: number | null;
    poi_lng?: number | null;
    brand?: string | null;
    rating?: number | null;
    services?: string | null;
    location?: string | null;
    fuel_price?: number | null;
    detour_km?: number | null;
    suggested_fuel_l?: number | null;
}

interface Props {
    data: ProximityData;
    initialExpanded?: boolean;
    onRead?: () => void;
    onDismiss: () => void;
    onShowOnMap: () => void;
    onAutoArchive: () => void;
}

const AUTO_ARCHIVE_MS = 5000;
const SCREEN = Dimensions.get('window');
const COMPACT_WIDTH = 236;
const EXPANDED_WIDTH = SCREEN.width - spacing.md * 2;
const EXPANDED_HEIGHT = Math.min(SCREEN.height * 0.48, 380);

const TYPE_COLORS: Record<string, string> = {
    fuel: colors.accent,
    'АЗС': colors.accent,
    rest: '#4a6caa',
    'Отдых': '#4a6caa',
    overnight: '#7a4a9e',
    'Ночлег': '#7a4a9e',
    food: colors.green,
    repair: colors.text3,
};

export default function ProximitySheet({
    data,
    initialExpanded = false,
    onRead,
    onDismiss,
    onShowOnMap,
    onAutoArchive,
}: Props) {
    const [expanded, setExpanded] = useState(false);
    const progress = useRef(new Animated.Value(0)).current;
    const fly = useRef(new Animated.Value(0)).current;
    const appear = useRef(new Animated.Value(0)).current;

    const color = TYPE_COLORS[data.rec_type] ?? colors.accent;
    const label = typeLabel(data.rec_type);
    const details = useMemo(() => [
        data.brand ? `Бренд: ${data.brand}` : null,
        data.rating != null ? `Рейтинг: ${data.rating}` : null,
        data.fuel_price != null ? `Топливо: ${data.fuel_price} ₽/л` : null,
        data.detour_km != null ? `Крюк: ${data.detour_km} км` : null,
        data.suggested_fuel_l != null ? `Залить: ~${data.suggested_fuel_l} л` : null,
        data.eta_at ? `ETA: ${new Date(data.eta_at).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' })}` : null,
    ].filter((item): item is string => Boolean(item)), [data]);

    useEffect(() => {
        setExpanded(false);
        progress.setValue(0);
        fly.setValue(0);
        appear.setValue(0);

        Animated.timing(appear, {
            toValue: 1,
            duration: 220,
            useNativeDriver: false,
        }).start();

        if (initialExpanded) {
            const openTimer = setTimeout(() => expand(), 80);
            return () => clearTimeout(openTimer);
        }
    }, [data._key]);

    useEffect(() => {
        if (expanded) return;

        const timer = setTimeout(() => {
            Animated.parallel([
                Animated.timing(fly, {
                    toValue: 1,
                    duration: 520,
                    useNativeDriver: false,
                }),
                Animated.timing(appear, {
                    toValue: 0,
                    duration: 520,
                    useNativeDriver: false,
                }),
            ]).start(onAutoArchive);
        }, AUTO_ARCHIVE_MS);

        return () => clearTimeout(timer);
    }, [expanded, data._key, onAutoArchive]);

    function expand() {
        progress.stopAnimation();
        fly.stopAnimation();
        onRead?.();
        setExpanded(true);
        Animated.spring(progress, {
            toValue: 1,
            friction: 9,
            tension: 75,
            useNativeDriver: false,
        }).start();
    }

    function collapse() {
        progress.stopAnimation();
        setExpanded(false);
        Animated.spring(progress, {
            toValue: 0,
            friction: 9,
            tension: 75,
            useNativeDriver: false,
        }).start();
    }

    const cardStyle = {
        top: progress.interpolate({ inputRange: [0, 1], outputRange: [136, 82] }),
        width: progress.interpolate({ inputRange: [0, 1], outputRange: [COMPACT_WIDTH, EXPANDED_WIDTH] }),
        height: progress.interpolate({ inputRange: [0, 1], outputRange: [124, EXPANDED_HEIGHT] }),
        opacity: appear,
        transform: [
            { translateY: Animated.add(appear.interpolate({ inputRange: [0, 1], outputRange: [-10, 0] }), fly.interpolate({ inputRange: [0, 1], outputRange: [0, -72] })) },
            { translateX: fly.interpolate({ inputRange: [0, 1], outputRange: [0, 110] }) },
            { scale: fly.interpolate({ inputRange: [0, 1], outputRange: [1, 0.22] }) },
        ],
    };

    return (
        <Animated.View style={[s.wrap, cardStyle]}>
            <View style={s.card}>
                <Animated.View style={[s.compactContent, { opacity: progress.interpolate({ inputRange: [0, 0.45], outputRange: [1, 0] }) }]}>
                    <View style={s.compactTap}>
                        <View style={s.compactTop}>
                            <View style={[s.badge, { backgroundColor: color }]}>
                                <Text style={s.badgeText}>{label}</Text>
                            </View>
                            <TouchableOpacity
                                style={s.closeMini}
                                hitSlop={{ top: 10, right: 10, bottom: 10, left: 10 }}
                                onPress={onDismiss}
                            >
                                <Text style={s.closeMiniText}>×</Text>
                            </TouchableOpacity>
                        </View>
                        <TouchableOpacity style={s.compactBodyTap} activeOpacity={0.9} onPress={expand}>
                            <Text style={s.compactTitle} numberOfLines={1}>{data.title}</Text>
                            <Text style={s.compactName} numberOfLines={1}>{data.poi_name}</Text>
                            <Text style={s.compactHint}>Нажмите, чтобы раскрыть</Text>
                        </TouchableOpacity>
                    </View>
                </Animated.View>

                <Animated.View
                    pointerEvents={expanded ? 'auto' : 'none'}
                    style={[s.expandedContent, { opacity: progress.interpolate({ inputRange: [0.35, 1], outputRange: [0, 1] }) }]}
                >
                    <View style={s.header}>
                        <View style={[s.badge, { backgroundColor: color }]}>
                            <Text style={s.badgeText}>{label}</Text>
                        </View>
                        <View style={s.headerActions}>
                            <TouchableOpacity style={s.headerBtn} onPress={collapse}>
                                <Text style={s.headerBtnText}>Свернуть</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                                style={s.closeBtn}
                                hitSlop={{ top: 8, right: 8, bottom: 8, left: 8 }}
                                onPress={onDismiss}
                            >
                                <Text style={s.closeBtnText}>×</Text>
                            </TouchableOpacity>
                        </View>
                    </View>

                    <ScrollView style={s.scroll} contentContainerStyle={s.scrollContent} showsVerticalScrollIndicator={false}>
                        <Text style={s.title}>{data.title}</Text>
                        <Text style={s.poiName}>{data.poi_name}</Text>
                        <Text style={s.body}>{data.body}</Text>
                        {!!data.location && <Text style={s.infoLine}>{data.location}</Text>}
                        {!!data.services && <Text style={s.infoLine}>{data.services}</Text>}

                        {details.length > 0 && (
                            <View style={s.details}>
                                {details.map(detail => (
                                    <Text key={detail} style={s.detailText}>{detail}</Text>
                                ))}
                            </View>
                        )}
                    </ScrollView>

                    <View style={s.actions}>
                        <TouchableOpacity style={[s.primaryBtn, { backgroundColor: color }]} onPress={onShowOnMap}>
                            <Text style={s.primaryText}>Показать на карте</Text>
                        </TouchableOpacity>
                        <TouchableOpacity style={s.secondaryBtn} onPress={onDismiss}>
                            <Text style={s.secondaryText}>Игнорировать</Text>
                        </TouchableOpacity>
                    </View>
                </Animated.View>
            </View>
        </Animated.View>
    );
}

function typeLabel(type: string) {
    if (type === 'fuel' || type === 'АЗС') return 'АЗС';
    if (type === 'overnight' || type === 'Ночлег') return 'Ночлег';
    if (type === 'food') return 'Кафе';
    if (type === 'repair') return 'СТО';
    return 'Остановка';
}

const s = StyleSheet.create({
    wrap: {
        position: 'absolute',
        right: spacing.md,
        zIndex: 42,
    },
    card: {
        flex: 1,
        backgroundColor: 'rgba(244,243,239,0.98)',
        borderRadius: radius.lg,
        borderWidth: 1,
        borderColor: colors.border,
        overflow: 'hidden',
        ...shadow.md,
    },
    compactContent: {
        ...StyleSheet.absoluteFillObject,
    },
    compactTap: {
        flex: 1,
        padding: spacing.md,
    },
    compactBodyTap: {
        flex: 1,
    },
    compactTop: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 6,
    },
    compactTitle: { fontSize: 14, fontWeight: '800', color: colors.text },
    compactName: { fontSize: 12, fontWeight: '700', color: colors.accent, marginTop: 3 },
    compactHint: { fontSize: 11, color: colors.text3, marginTop: 6 },
    closeMini: {
        width: 24,
        height: 24,
        borderRadius: 12,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: 'rgba(0,0,0,0.08)',
    },
    closeMiniText: { fontSize: 18, color: colors.text2, lineHeight: 20 },
    expandedContent: {
        flex: 1,
        padding: spacing.md,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: spacing.sm,
    },
    headerActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    headerBtn: {
        borderRadius: radius.full,
        paddingHorizontal: 10,
        paddingVertical: 6,
        backgroundColor: colors.s1,
        borderWidth: 1,
        borderColor: colors.border,
    },
    headerBtnText: { fontSize: 12, color: colors.text2, fontWeight: '700' },
    closeBtn: {
        width: 30,
        height: 30,
        borderRadius: 15,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: colors.s1,
        borderWidth: 1,
        borderColor: colors.border,
    },
    closeBtnText: { fontSize: 20, color: colors.text2, lineHeight: 22 },
    badge: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: radius.sm,
        alignSelf: 'flex-start',
    },
    badgeText: { color: '#fff', fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
    scroll: { flex: 1 },
    scrollContent: { paddingBottom: spacing.sm },
    title: { fontSize: 18, fontWeight: '800', color: colors.text },
    poiName: { fontSize: 15, fontWeight: '700', color: colors.accent, marginTop: 4 },
    body: { fontSize: 13, color: colors.text2, marginTop: 6, lineHeight: 18 },
    infoLine: { fontSize: 12, color: colors.text2, marginTop: 5, lineHeight: 17 },
    details: { marginTop: spacing.sm, gap: 5 },
    detailText: { fontSize: 12, color: colors.text, fontWeight: '700' },
    actions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md },
    primaryBtn: {
        flex: 1.25,
        borderRadius: radius.sm,
        paddingVertical: 12,
        alignItems: 'center',
    },
    primaryText: { fontSize: 14, fontWeight: '800', color: '#fff' },
    secondaryBtn: {
        flex: 1,
        borderRadius: radius.sm,
        paddingVertical: 12,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: colors.borderMid,
        backgroundColor: colors.s1,
    },
    secondaryText: { fontSize: 14, fontWeight: '700', color: colors.text2 },
});
