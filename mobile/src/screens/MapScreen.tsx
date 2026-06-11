import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Alert,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import AppIcon from '@/components/AppIcon';
import MapView, { Marker, Polyline, PROVIDER_DEFAULT } from 'react-native-maps';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { useTripStore } from '@/store/trip';
import { useLocation } from '@/hooks/useLocation';
import { useNotifications } from '@/hooks/useNotifications';
import { getRoute, RoutePlan } from '@/api/routes';
import ProximitySheet, { ProximityData } from '@/components/ProximitySheet';
import { colors, spacing, radius, shadow } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'Map'>;
type NotificationItem = ProximityData & {
    _id: string;
    _key: string;
    receivedAt: number;
    read?: boolean;
    _openExpanded?: boolean;
};

const STOP_COLORS: Record<string, string> = {
    fuel: '#c99b3a',
    'АЗС': '#c99b3a',
    rest: '#4a6caa',
    'Отдых': '#4a6caa',
    overnight: '#7a4a9e',
    'Ночлег': '#7a4a9e',
    food: '#2d7a4f',
    'Кафе': '#2d7a4f',
    route_stop: '#5b5bd6',
    optional_stop: '#6a6762',
};

const STOP_LABELS: Record<string, string> = {
    fuel: 'АЗС',
    'АЗС': 'АЗС',
    rest: 'Отдых',
    'Отдых': 'Отдых',
    overnight: 'Ночлег',
    'Ночлег': 'Ночлег',
    food: 'Кафе',
    'Кафе': 'Кафе',
    route_stop: 'Точка маршрута',
    optional_stop: 'Рядом с маршрутом',
};

function errorMessage(error: any, fallback: string) {
    const errors = error?.response?.data?.errors as Record<string, string[]> | undefined;
    return error?.response?.data?.message
        ?? Object.values(errors ?? {})[0]?.[0]
        ?? error?.message
        ?? fallback;
}

function normalizeRawPolyline(points?: unknown): { latitude: number; longitude: number }[] {
    if (!Array.isArray(points)) return [];

    return points
        .map((point) => {
            if (Array.isArray(point)) {
                return { latitude: Number(point[0]), longitude: Number(point[1]) };
            }

            if (point && typeof point === 'object') {
                const p = point as Record<string, unknown>;
                return { latitude: Number(p.lat ?? p.latitude), longitude: Number(p.lng ?? p.longitude) };
            }

            return null;
        })
        .filter((point): point is { latitude: number; longitude: number } => (
            !!point
            && Number.isFinite(point.latitude)
            && Number.isFinite(point.longitude)
            && Math.abs(point.latitude) <= 90
            && Math.abs(point.longitude) <= 180
        ));
}

export default function MapScreen({ route: navRoute, navigation }: Props) {
    const routeId = navRoute.params?.routeId;
    const preferredFuelBrand = navRoute.params?.preferredFuelBrand;
    const trip = useTripStore();
    const location = useLocation();
    const mapRef = useRef<MapView>(null);
    const visibleNotificationRef = useRef<NotificationItem | null>(null);

    const [plan, setPlan] = useState<RoutePlan | null>(null);
    const [visibleNotification, setVisibleNotification] = useState<NotificationItem | null>(null);
    const [notificationQueue, setNotificationQueue] = useState<NotificationItem[]>([]);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [highlightedPoi, setHighlightedPoi] = useState<any>(null);
    const unreadCount = notifications.filter(item => !item.read).length;

    const onProximityAlert = useCallback((data: any) => {
        const next = normalizeNotification(data);
        setNotifications(prev => [next, ...prev].slice(0, 30));

        if (visibleNotificationRef.current) {
            setNotificationQueue(prev => [...prev, next]);
        } else {
            setVisibleNotification(next);
        }
    }, []);

    useNotifications(onProximityAlert);

    useEffect(() => {
        visibleNotificationRef.current = visibleNotification;
    }, [visibleNotification]);

    useEffect(() => {
        let alive = true;

        async function loadPlan() {
            if (routeId) {
                getRoute(routeId).then(next => {
                    if (alive) setPlan(next);
                }).catch(() => {});
                return;
            }

            if (trip.activePlan) {
                setPlan(trip.activePlan);
                return;
            }

            if (trip.session?.route_plan_id) {
                getRoute(trip.session.route_plan_id).then(next => {
                    if (alive) setPlan(next);
                }).catch(() => {});
                return;
            }

            setPlan(null);
        }

        loadPlan();

        return () => { alive = false; };
    }, [routeId, trip.activePlan, trip.session?.route_plan_id]);

    useEffect(() => {
        (async () => {
            if (trip.session?.status === 'active') {
                await location.startTracking(undefined, { preferredFuelBrand }).catch(() => null);
            }
        })();

        return () => {
            location.stopTracking();
        };
    }, []);

    function proximityCoordinate(data: any) {
        const lat = Number(data?.lat ?? data?.poi_lat);
        const lng = Number(data?.lng ?? data?.poi_lng);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }

        return { latitude: lat, longitude: lng };
    }

    const showNextNotification = useCallback(() => {
        setVisibleNotification(null);
        setNotificationQueue(prev => {
            const [next, ...rest] = prev;
            if (next) {
                setTimeout(() => setVisibleNotification(next), 180);
            }
            return rest;
        });
    }, []);

    function markNotificationRead(id?: string) {
        if (!id) return;

        setNotifications(prev => prev.map(item => (
            item._id === id ? { ...item, read: true } : item
        )));
        setNotificationQueue(prev => prev.map(item => (
            item._id === id ? { ...item, read: true } : item
        )));
        setVisibleNotification(prev => (
            prev?._id === id ? { ...prev, read: true } : prev
        ));
    }

    function deleteNotification(id: string) {
        setNotifications(prev => prev.filter(item => item._id !== id));
        setNotificationQueue(prev => prev.filter(item => item._id !== id));

        if (visibleNotificationRef.current?._id === id) {
            showNextNotification();
        }
    }

    function handleShowProximityOnMap(data: any = visibleNotification) {
        const coordinate = proximityCoordinate(data);
        if (!coordinate) {
            Alert.alert('Нет координат', 'У этого объекта не удалось определить точку на карте.');
            return;
        }

        markNotificationRead(data?._id);
        setHighlightedPoi({ ...data, coordinate });
        setNotificationsOpen(false);
        mapRef.current?.animateToRegion({
            ...coordinate,
            latitudeDelta: 0.04,
            longitudeDelta: 0.04,
        }, 550);
        showNextNotification();
    }

    function openNotificationFromHistory(item: NotificationItem) {
        markNotificationRead(item._id);
        setNotificationQueue(prev => prev.filter(notification => notification._id !== item._id));
        setNotificationsOpen(false);
        setVisibleNotification({
            ...item,
            read: true,
            _key: `${item._id}:open:${Date.now()}`,
            _openExpanded: true,
        });
    }

    async function handleStartTrip() {
        if (!plan) return;

        try {
            await trip.start(plan.id, plan);
        } catch (error: any) {
            Alert.alert('Ошибка', errorMessage(error, 'Не удалось начать поездку.'));
            return;
        }

        const tracking = await location.startTracking(undefined, { preferredFuelBrand });
        if (tracking.background) {
            Alert.alert(
                'Поездка начата',
                'Фоновое слежение активировано. Вы будете получать уведомления о приближении к точкам.',
            );
        } else {
            Alert.alert(
                'Поездка начата',
                tracking.message ?? 'Фоновое слежение сейчас недоступно, но поездка активна.',
            );
        }
    }

    async function handleEndTrip() {
        try {
            await trip.end();
            await location.stopTracking();
            navigation.goBack();
        } catch (error: any) {
            Alert.alert('Ошибка', errorMessage(error, 'Не удалось завершить поездку.'));
        }
    }

    const polyline = useMemo(() => normalizeRawPolyline(plan?.route?.polyline), [plan?.route?.polyline]);
    const stops = plan?.stops ?? [];

    useEffect(() => {
        if (polyline.length < 2) return;

        const timer = setTimeout(() => {
            mapRef.current?.fitToCoordinates(polyline, {
                edgePadding: { top: 120, right: 48, bottom: 140, left: 48 },
                animated: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [polyline]);

    const initialRegion = polyline.length > 0
        ? {
            latitude: polyline[Math.floor(polyline.length / 2)].latitude,
            longitude: polyline[Math.floor(polyline.length / 2)].longitude,
            latitudeDelta: 5,
            longitudeDelta: 5,
        }
        : { latitude: 53.9023, longitude: 27.5619, latitudeDelta: 8, longitudeDelta: 8 };

    return (
        <View style={s.root}>
            <MapView
                ref={mapRef}
                style={s.map}
                provider={PROVIDER_DEFAULT}
                initialRegion={initialRegion}
                showsUserLocation
                showsCompass
            >
                {polyline.length > 1 && (
                    <>
                        <Polyline
                            coordinates={polyline}
                            strokeColor="rgba(255,255,255,0.95)"
                            strokeWidth={9}
                            lineCap="round"
                            lineJoin="round"
                            zIndex={10}
                        />
                        <Polyline
                            coordinates={polyline}
                            strokeColor={colors.accent}
                            strokeWidth={5}
                            lineCap="round"
                            lineJoin="round"
                            zIndex={11}
                        />
                    </>
                )}

                {plan?.origin?.point && (
                    <Marker
                        coordinate={{ latitude: plan.origin.point.lat, longitude: plan.origin.point.lng }}
                        title="Откуда"
                        description={plan.origin.label}
                        pinColor={colors.green}
                    />
                )}

                {stops.map((stop) => stop.poi && (
                    <Marker
                        key={stop.id}
                        coordinate={{ latitude: stop.poi.lat, longitude: stop.poi.lng }}
                        title={STOP_LABELS[stop.type] ?? stop.type}
                        description={stop.poi.name}
                        pinColor={STOP_COLORS[stop.type] ?? colors.text3}
                    />
                ))}

                {(plan?.via_points ?? []).map((point, index) => (
                    <Marker
                        key={`via-${index}-${point.lat}-${point.lng}`}
                        coordinate={{ latitude: point.lat, longitude: point.lng }}
                        title={`Транзит ${index + 1}`}
                        description={point.label}
                        pinColor="#5b5bd6"
                    />
                ))}

                {highlightedPoi && (
                    <Marker
                        coordinate={highlightedPoi.coordinate}
                        title={highlightedPoi.poi_name ?? highlightedPoi.name ?? 'Объект'}
                        description={highlightedPoi.location ?? highlightedPoi.services ?? highlightedPoi.body}
                        pinColor={colors.accent}
                    />
                )}

                {plan?.destination?.point && (
                    <Marker
                        coordinate={{ latitude: plan.destination.point.lat, longitude: plan.destination.point.lng }}
                        title="Куда"
                        description={plan.destination.label}
                        pinColor={colors.red}
                    />
                )}
            </MapView>

            <SafeAreaView style={s.topBar}>
                <TouchableOpacity style={s.backBtn} onPress={() => navigation.goBack()}>
                    <Text style={s.backBtnText}>Назад</Text>
                </TouchableOpacity>
                {plan && (
                    <View style={s.routeInfo}>
                        <Text style={s.routeName} numberOfLines={1}>{plan.title}</Text>
                        <Text style={s.routeMeta}>{plan.distance_km} км · {plan.stops_count} остановок</Text>
                    </View>
                )}
            </SafeAreaView>

            <View style={s.notificationCenter}>
                <TouchableOpacity
                    style={s.notificationBtn}
                    activeOpacity={0.85}
                    onPress={() => setNotificationsOpen(prev => !prev)}
                >
                    <AppIcon name="notifications-outline" size={21} color={colors.accent} />
                    {unreadCount > 0 && (
                        <View style={s.notificationBadge}>
                            <Text style={s.notificationBadgeText}>{Math.min(99, unreadCount)}</Text>
                        </View>
                    )}
                </TouchableOpacity>

                {notificationsOpen && (
                    <View style={s.notificationPanel}>
                        <View style={s.notificationPanelHeader}>
                            <Text style={s.notificationPanelTitle}>Уведомления</Text>
                            <TouchableOpacity
                                style={s.notificationPanelCloseBtn}
                                hitSlop={{ top: 10, right: 10, bottom: 10, left: 10 }}
                                onPress={() => setNotificationsOpen(false)}
                            >
                                <Text style={s.notificationPanelClose}>×</Text>
                            </TouchableOpacity>
                        </View>
                        {notifications.length === 0 ? (
                            <Text style={s.notificationEmpty}>Пока нет рекомендаций рядом</Text>
                        ) : (
                            <ScrollView style={s.notificationList} showsVerticalScrollIndicator={false}>
                                {notifications.map(item => (
                                    <View
                                        key={item._id}
                                        style={[s.notificationItem, !item.read && s.notificationItemUnread]}
                                    >
                                        <TouchableOpacity
                                            style={s.notificationItemBody}
                                            activeOpacity={0.85}
                                            onPress={() => openNotificationFromHistory(item)}
                                        >
                                            <View style={s.notificationTitleRow}>
                                                {!item.read && <View style={s.notificationUnreadDot} />}
                                                <Text style={s.notificationItemTitle} numberOfLines={1}>{item.title}</Text>
                                            </View>
                                            <Text style={s.notificationItemName} numberOfLines={1}>{item.poi_name}</Text>
                                            <Text style={s.notificationItemMeta}>{formatNotificationTime(item.receivedAt)}</Text>
                                        </TouchableOpacity>
                                        <TouchableOpacity
                                            style={s.notificationDeleteBtn}
                                            hitSlop={{ top: 10, right: 10, bottom: 10, left: 10 }}
                                            onPress={() => deleteNotification(item._id)}
                                        >
                                            <Text style={s.notificationDeleteText}>×</Text>
                                        </TouchableOpacity>
                                    </View>
                                ))}
                            </ScrollView>
                        )}
                    </View>
                )}
            </View>

            <View style={s.bottomBar}>
                {trip.session?.status === 'active' ? (
                    <TouchableOpacity style={[s.btn, { backgroundColor: colors.red }]} onPress={handleEndTrip}>
                        <Text style={s.btnText}>Завершить поездку</Text>
                    </TouchableOpacity>
                ) : (
                    <TouchableOpacity style={s.btn} onPress={handleStartTrip}>
                        <Text style={s.btnText}>Начать поездку</Text>
                    </TouchableOpacity>
                )}
            </View>

            {visibleNotification && !notificationsOpen && (
                <ProximitySheet
                    data={visibleNotification}
                    initialExpanded={visibleNotification._openExpanded}
                    onRead={() => markNotificationRead(visibleNotification._id)}
                    onAutoArchive={showNextNotification}
                    onShowOnMap={() => handleShowProximityOnMap(visibleNotification)}
                    onDismiss={showNextNotification}
                />
            )}
        </View>
    );
}

function normalizeNotification(data: any): NotificationItem {
    const receivedAt = Date.now();
    const stablePart = data?.poi_id ?? data?.service_object_id ?? data?.poi_name ?? data?.title ?? 'notice';

    return {
        ...data,
        _id: `${stablePart}-${receivedAt}`,
        _key: `${stablePart}-${receivedAt}`,
        receivedAt,
        read: false,
    };
}

function formatNotificationTime(value: number) {
    return new Date(value).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
}

const s = StyleSheet.create({
    root: { flex: 1 },
    map: { flex: 1 },
    topBar: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        padding: spacing.md,
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
    },
    backBtn: {
        backgroundColor: 'rgba(244,243,239,0.92)',
        paddingHorizontal: spacing.md,
        paddingVertical: 8,
        borderRadius: radius.full,
        ...shadow.sm,
    },
    backBtnText: { fontSize: 13, fontWeight: '600', color: colors.accent },
    routeInfo: {
        flex: 1,
        marginRight: 54,
        backgroundColor: 'rgba(244,243,239,0.92)',
        paddingHorizontal: spacing.md,
        paddingVertical: 8,
        borderRadius: radius.md,
        ...shadow.sm,
    },
    routeName: { fontSize: 13, fontWeight: '600', color: colors.text },
    routeMeta: { fontSize: 11, color: colors.text3, marginTop: 2 },
    notificationCenter: {
        position: 'absolute',
        top: 88,
        right: spacing.md,
        zIndex: 30,
        alignItems: 'flex-end',
    },
    notificationBtn: {
        width: 44,
        height: 44,
        borderRadius: 22,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: 'rgba(244,243,239,0.96)',
        borderWidth: 1,
        borderColor: colors.border,
        ...shadow.sm,
    },
    notificationBadge: {
        position: 'absolute',
        top: -4,
        right: -4,
        minWidth: 18,
        height: 18,
        borderRadius: 9,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: colors.red,
        paddingHorizontal: 4,
        borderWidth: 1,
        borderColor: '#fff',
    },
    notificationBadgeText: { color: '#fff', fontSize: 10, fontWeight: '800' },
    notificationPanel: {
        width: 286,
        maxHeight: 292,
        marginTop: spacing.sm,
        borderRadius: radius.md,
        backgroundColor: 'rgba(244,243,239,0.98)',
        borderWidth: 1,
        borderColor: colors.border,
        padding: spacing.sm,
        ...shadow.md,
    },
    notificationPanelHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: 4,
        paddingBottom: 6,
    },
    notificationPanelTitle: { fontSize: 13, fontWeight: '800', color: colors.text },
    notificationPanelCloseBtn: {
        width: 28,
        height: 28,
        borderRadius: 14,
        alignItems: 'center',
        justifyContent: 'center',
    },
    notificationPanelClose: { fontSize: 22, lineHeight: 24, color: colors.text2 },
    notificationEmpty: { color: colors.text3, fontSize: 12, padding: spacing.md, textAlign: 'center' },
    notificationList: { maxHeight: 238 },
    notificationItem: {
        borderRadius: radius.sm,
        backgroundColor: colors.s1,
        borderWidth: 1,
        borderColor: colors.border,
        flexDirection: 'row',
        alignItems: 'flex-start',
        marginBottom: 7,
        overflow: 'hidden',
    },
    notificationItemUnread: {
        borderColor: colors.accent,
        backgroundColor: 'rgba(201,155,58,0.09)',
    },
    notificationItemBody: {
        flex: 1,
        paddingHorizontal: spacing.sm,
        paddingVertical: 9,
    },
    notificationTitleRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
    },
    notificationUnreadDot: {
        width: 7,
        height: 7,
        borderRadius: 3.5,
        backgroundColor: colors.red,
    },
    notificationItemTitle: { flex: 1, fontSize: 12, fontWeight: '800', color: colors.text },
    notificationItemName: { fontSize: 12, color: colors.accent, fontWeight: '700', marginTop: 2 },
    notificationItemMeta: { fontSize: 10, color: colors.text3, marginTop: 3, fontFamily: 'monospace' },
    notificationDeleteBtn: {
        width: 34,
        minHeight: 44,
        alignItems: 'center',
        justifyContent: 'center',
    },
    notificationDeleteText: { fontSize: 20, lineHeight: 22, color: colors.text3 },
    bottomBar: { position: 'absolute', bottom: 32, left: spacing.lg, right: spacing.lg },
    btn: {
        backgroundColor: colors.accent,
        borderRadius: radius.md,
        paddingVertical: 16,
        alignItems: 'center',
        ...shadow.md,
    },
    btnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
