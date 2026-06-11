import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert, Image } from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import client, { mediaUrl } from '@/api/client';
import { useAuthStore } from '@/store/auth';
import LocationMapModal from '@/components/LocationMapModal';
import { colors, spacing, radius, shadow } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'PlaceDetail'>;

export default function PlaceDetailScreen({ route: navRoute }: Props) {
    const { isAuthenticated } = useAuthStore();
    const [poi, setPoi]       = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [fav, setFav]         = useState(false);
    const [mapOpen, setMapOpen] = useState(false);

    useEffect(() => {
        client.get(`/poi/${navRoute.params.id}`)
            .then(r => {
                setPoi(normalizePoi(r.data.data ?? r.data));
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [navRoute.params.id]);

    async function toggleFav() {
        if (!isAuthenticated()) {
            Alert.alert('Требуется вход', 'Войдите в аккаунт для добавления в избранное');
            return;
        }
        try {
            if (fav) {
                await client.delete(`/favorites/${poi.id}`);
                setFav(false);
            } else {
                await client.post(`/favorites/${poi.id}`);
                setFav(true);
            }
        } catch { /* ignore */ }
    }

    if (loading) return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    if (!poi)    return <View style={s.center}><Text style={s.err}>Объект не найден</Text></View>;

    const point = poi.lat != null && poi.lng != null
        ? { lat: Number(poi.lat), lng: Number(poi.lng) }
        : null;

    return (
        <ScrollView style={s.root} contentContainerStyle={{ padding: spacing.md, paddingBottom: 40 }}>
            {poi.image_url ? (
                <Image source={{ uri: poi.image_url }} style={s.cover} resizeMode="cover" />
            ) : (
                <View style={s.noImage}>
                    <Text style={s.noImageText}>Поставщик не добавил фото</Text>
                </View>
            )}

            {/* Header */}
            <View style={s.header}>
                <View style={s.typeBadge}>
                    <Text style={s.typeBadgeText}>{poi.type}</Text>
                </View>
                <TouchableOpacity style={s.favBtn} onPress={toggleFav}>
                    <Text style={[s.favBtnText, fav && { color: colors.accent }]}>{fav ? '♥' : '♡'}</Text>
                </TouchableOpacity>
            </View>

            <Text style={s.name}>{poi.name}</Text>
            <Text style={s.location}>{poi.location}</Text>

            {point && (
                <TouchableOpacity style={s.mapCard} activeOpacity={0.9} onPress={() => setMapOpen(true)}>
                    <MapView
                        style={s.map}
                        provider={PROVIDER_DEFAULT}
                        initialRegion={{
                            latitude: point.lat,
                            longitude: point.lng,
                            latitudeDelta: 0.06,
                            longitudeDelta: 0.06,
                        }}
                        scrollEnabled={false}
                        zoomEnabled={false}
                        rotateEnabled={false}
                        pitchEnabled={false}
                    >
                        <Marker
                            coordinate={{ latitude: point.lat, longitude: point.lng }}
                            title={poi.name}
                            description={poi.location}
                            pinColor={colors.accent}
                        />
                    </MapView>
                    <Text style={s.mapHint}>Нажмите, чтобы открыть карту</Text>
                </TouchableOpacity>
            )}

            {/* Stats */}
            <View style={s.stats}>
                <StatItem label="Рейтинг" value={`★ ${poi.rating}`} />
                {poi.detour_km > 0 && <StatItem label="Крюк" value={`${poi.detour_km} км`} />}
                {poi.fuel_price && <StatItem label="Цена" value={`${poi.fuel_price} ₽/л`} />}
                {poi.has_truck_parking && <StatItem label="Грузовики" value="✓" color={colors.green} />}
            </View>

            {/* Services */}
            <Text style={s.sectionTitle}>Услуги</Text>
            <View style={s.card}>
                <Text style={s.services}>{poi.services || 'Не указаны'}</Text>
            </View>

            {/* Description */}
            {poi.description && (
                <>
                    <Text style={s.sectionTitle}>Описание</Text>
                    <View style={s.card}>
                        <Text style={s.desc}>{poi.description}</Text>
                    </View>
                </>
            )}
            {point && (
                <LocationMapModal
                    visible={mapOpen}
                    title={poi.name}
                    subtitle={poi.location}
                    point={point}
                    markerTitle={poi.name}
                    markerDescription={poi.location}
                    markerColor={colors.accent}
                    onClose={() => setMapOpen(false)}
                />
            )}
        </ScrollView>
    );
}

function normalizePoi(raw: any) {
    const gallery = Array.isArray(raw.gallery) ? raw.gallery.map(mediaUrl).filter(Boolean) : [];

    return {
        ...raw,
        lat: raw.lat ?? raw.coordinates?.lat ?? null,
        lng: raw.lng ?? raw.coordinates?.lng ?? null,
        image_url: mediaUrl(raw.image_url ?? raw.image ?? gallery[0] ?? null),
        gallery,
        video_url: mediaUrl(raw.video_url),
    };
}

function StatItem({ label, value, color }: { label: string; value: string; color?: string }) {
    return (
        <View style={{ alignItems: 'center' }}>
            <Text style={{ fontSize: 16, fontWeight: '700', color: color ?? colors.accent, fontFamily: 'monospace' }}>{value}</Text>
            <Text style={{ fontSize: 10, color: colors.text3, marginTop: 2 }}>{label}</Text>
        </View>
    );
}

const s = StyleSheet.create({
    root:     { flex: 1, backgroundColor: colors.bg },
    center:   { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    err:      { color: colors.text3 },
    cover:    { height: 210, borderRadius: radius.md, marginBottom: spacing.md, backgroundColor: colors.s2 },
    noImage:  { height: 170, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, marginBottom: spacing.md, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    noImageText: { color: colors.text3, fontSize: 12 },
    mapCard:  { height: 190, borderRadius: radius.md, overflow: 'hidden', borderWidth: 1, borderColor: colors.border, marginBottom: spacing.md, backgroundColor: colors.s2, ...shadow.sm },
    map:      { flex: 1 },
    mapHint:  { position: 'absolute', left: 10, right: 10, bottom: 10, borderRadius: radius.sm, paddingVertical: 7, paddingHorizontal: 10, backgroundColor: 'rgba(0,0,0,0.55)', color: '#fff', fontSize: 12, textAlign: 'center', overflow: 'hidden' },
    header:   { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: spacing.sm },
    typeBadge:{ backgroundColor: colors.accentBg, borderWidth: 1, borderColor: 'rgba(145,100,0,0.25)', borderRadius: radius.sm, paddingHorizontal: 10, paddingVertical: 4 },
    typeBadgeText: { fontSize: 12, color: colors.accent, fontWeight: '700', letterSpacing: 0.5 },
    favBtn:   { padding: spacing.sm },
    favBtnText: { fontSize: 24, color: colors.text3 },
    name:     { fontSize: 22, fontWeight: '700', color: colors.text },
    location: { fontSize: 13, color: colors.text2, marginTop: 4, marginBottom: spacing.md },
    stats:    { flexDirection: 'row', justifyContent: 'space-around', backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.lg, ...shadow.sm },
    sectionTitle: { fontSize: 15, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
    card:     { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.lg },
    services: { fontSize: 13, color: colors.text2, lineHeight: 19 },
    desc:     { fontSize: 13, color: colors.text2, lineHeight: 19 },
});
