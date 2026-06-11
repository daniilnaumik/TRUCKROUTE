import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Alert, SafeAreaView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_DEFAULT } from 'react-native-maps';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { buildGeoRoute, geocodeAddress, RouteGeometry } from '@/api/geo';
import { FleetAssignment, GeoPoint, getDriverAssignment, getFleetAssignment } from '@/api/fleet';
import { getRoute } from '@/api/routes';
import { RootStackParamList } from '@/navigation';
import { colors, radius, shadow, spacing } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'AssignmentMap'>;

function isPoint(value?: GeoPoint | null): value is GeoPoint {
    return typeof value?.lat === 'number' && typeof value?.lng === 'number';
}

function mapPoint(point: GeoPoint) {
    return { latitude: point.lat, longitude: point.lng };
}

function normalizeRawPolyline(points?: unknown[]): { latitude: number; longitude: number }[] {
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

function geometryFromCoordinates(
    coordinates: { latitude: number; longitude: number }[],
    base?: Partial<RouteGeometry>,
): RouteGeometry {
    return {
        distance_m: base?.distance_m ?? 0,
        distance_km: base?.distance_km ?? 0,
        duration_s: base?.duration_s ?? 0,
        duration_min: base?.duration_min ?? 0,
        provider: base?.provider ?? 'fallback',
        polyline: coordinates.map(point => [point.latitude, point.longitude]),
    };
}

function errorMessage(error: any, fallback: string) {
    return error?.response?.data?.message ?? error?.message ?? fallback;
}

export default function AssignmentMapScreen({ route, navigation }: Props) {
    const { id, fleetId } = route.params;
    const mapRef = useRef<MapView>(null);

    const [assignment, setAssignment] = useState<FleetAssignment | null>(null);
    const [from, setFrom] = useState<GeoPoint | null>(null);
    const [to, setTo] = useState<GeoPoint | null>(null);
    const [via, setVia] = useState<GeoPoint[]>([]);
    const [geometry, setGeometry] = useState<RouteGeometry | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let alive = true;

        async function load() {
            setLoading(true);
            try {
                const data = fleetId ? await getFleetAssignment(fleetId, id) : await getDriverAssignment(id);
                if (!alive) return;

                const originPoint = isPoint(data.origin_point) ? data.origin_point : await geocodeAddress(data.origin);
                const destinationPoint = isPoint(data.destination_point) ? data.destination_point : await geocodeAddress(data.destination);
                const viaPoints = (data.via_points ?? []).filter(isPoint);

                if (!originPoint || !destinationPoint) {
                    setAssignment(data);
                    setFrom(originPoint);
                    setTo(destinationPoint);
                    setVia(viaPoints);
                    Alert.alert('Маршрут не найден', 'Не удалось определить точки отправления и назначения.');
                    return;
                }

                setAssignment(data);
                setFrom(originPoint);
                setTo(destinationPoint);
                setVia(viaPoints);

                if (data.route_plan_id) {
                    try {
                        const plan = await getRoute(data.route_plan_id);
                        const savedPolyline = normalizeRawPolyline(plan.route?.polyline);

                        if (savedPolyline.length > 1) {
                            setGeometry(geometryFromCoordinates(savedPolyline, {
                                distance_m: Math.round((plan.distance_km ?? 0) * 1000),
                                distance_km: plan.distance_km ?? 0,
                                duration_s: (plan.drive_time_minutes ?? 0) * 60,
                                duration_min: plan.drive_time_minutes ?? 0,
                                provider: plan.route?.provider ?? 'saved',
                            }));
                            return;
                        }
                    } catch {
                        // If the saved route is unavailable, build a fresh geometry below.
                    }
                }

                try {
                    const routed = await buildGeoRoute(originPoint, destinationPoint, viaPoints);
                    const routedPolyline = normalizeRawPolyline(routed.polyline);
                    const fallbackPolyline = [originPoint, ...viaPoints, destinationPoint].map(mapPoint);
                    setGeometry(geometryFromCoordinates(routedPolyline.length > 1 ? routedPolyline : fallbackPolyline, routed));
                } catch (error: any) {
                    setGeometry(geometryFromCoordinates([originPoint, ...viaPoints, destinationPoint].map(mapPoint)));
                    Alert.alert('Путь показан приблизительно', errorMessage(error, 'Роутинг временно недоступен.'));
                }
            } catch (error: any) {
                Alert.alert('Ошибка', errorMessage(error, 'Не удалось открыть карту задания.'));
            } finally {
                if (alive) setLoading(false);
            }
        }

        load();

        return () => { alive = false; };
    }, [id, fleetId]);

    const polyline = useMemo(() => normalizeRawPolyline(geometry?.polyline), [geometry]);

    useEffect(() => {
        const coordinates = polyline.length > 0
            ? polyline
            : [from, ...via, to].filter(isPoint).map(mapPoint);

        if (coordinates.length < 2) return;

        const timer = setTimeout(() => {
            mapRef.current?.fitToCoordinates(coordinates, {
                edgePadding: { top: 120, right: 40, bottom: 140, left: 40 },
                animated: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [polyline, from, via, to]);

    const initialRegion = from
        ? { latitude: from.lat, longitude: from.lng, latitudeDelta: 4, longitudeDelta: 4 }
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
                            strokeColor="rgba(255,255,255,0.96)"
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

                {from && (
                    <Marker coordinate={mapPoint(from)} title="Откуда" description={assignment?.origin} pinColor={colors.green} />
                )}

                {via.map((point, index) => (
                    <Marker
                        key={`${point.lat}-${point.lng}-${index}`}
                        coordinate={mapPoint(point)}
                        title={`Точка ${index + 1}`}
                        description={point.label}
                        pinColor={colors.accent}
                    />
                ))}

                {to && (
                    <Marker coordinate={mapPoint(to)} title="Куда" description={assignment?.destination} pinColor={colors.red} />
                )}
            </MapView>

            <SafeAreaView style={s.topBar}>
                <TouchableOpacity style={s.backBtn} onPress={() => navigation.goBack()}>
                    <Text style={s.backText}>Назад</Text>
                </TouchableOpacity>
                <View style={s.info}>
                    <Text style={s.title} numberOfLines={1}>
                        {assignment ? `${assignment.origin} → ${assignment.destination}` : 'Маршрут задания'}
                    </Text>
                    <Text style={s.meta}>
                        {geometry?.distance_km ? `${geometry.distance_km} км · ${geometry.duration_min} мин` : 'Путь по заданию'}
                    </Text>
                </View>
            </SafeAreaView>

            {loading && (
                <View style={s.loading}>
                    <ActivityIndicator color={colors.accent} />
                    <Text style={s.loadingText}>Строим маршрут...</Text>
                </View>
            )}
        </View>
    );
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
        backgroundColor: 'rgba(244,243,239,0.94)',
        borderRadius: radius.full,
        paddingHorizontal: spacing.md,
        paddingVertical: 9,
        ...shadow.sm,
    },
    backText: { color: colors.accent, fontSize: 13, fontWeight: '700' },
    info: {
        flex: 1,
        backgroundColor: 'rgba(244,243,239,0.94)',
        borderRadius: radius.md,
        paddingHorizontal: spacing.md,
        paddingVertical: 9,
        ...shadow.sm,
    },
    title: { color: colors.text, fontSize: 13, fontWeight: '700' },
    meta: { color: colors.text3, fontSize: 11, marginTop: 2 },
    loading: {
        position: 'absolute',
        left: spacing.lg,
        right: spacing.lg,
        bottom: 34,
        borderRadius: radius.md,
        backgroundColor: 'rgba(244,243,239,0.96)',
        paddingVertical: spacing.md,
        alignItems: 'center',
        gap: spacing.sm,
        ...shadow.md,
    },
    loadingText: { color: colors.text2, fontSize: 13, fontWeight: '600' },
});
