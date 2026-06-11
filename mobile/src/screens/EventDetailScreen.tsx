import React, { useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    Image,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { RouteProp, useRoute } from '@react-navigation/native';
import { RootStackParamList } from '@/navigation';
import { getEvent, reportEvent, RoadEvent, voteEvent } from '@/api/events';
import LocationMapModal from '@/components/LocationMapModal';
import { colors, radius, shadow, spacing } from '@/theme';

type Route = RouteProp<RootStackParamList, 'EventDetail'>;

export default function EventDetailScreen() {
    const route = useRoute<Route>();
    const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [event, setEvent] = useState<RoadEvent | null>(null);
    const [loading, setLoading] = useState(true);
    const [voting, setVoting] = useState(false);
    const [notice, setNotice] = useState('');
    const [mapOpen, setMapOpen] = useState(false);

    useEffect(() => {
        getEvent(route.params.id)
            .then(setEvent)
            .finally(() => setLoading(false));

        return () => {
            if (toastTimer.current) clearTimeout(toastTimer.current);
        };
    }, [route.params.id]);

    function showNotice(message: string) {
        setNotice(message);
        if (toastTimer.current) clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setNotice(''), 2800);
    }

    async function handleVote(vote: 1 | -1) {
        if (!event || voting) return;

        if (event.user_vote) {
            showNotice(event.user_vote > 0
                ? 'Вы уже подтвердили это событие. Жалоба после подтверждения недоступна.'
                : 'Вы уже пожаловались на это событие. Подтверждение после жалобы недоступно.');
            return;
        }

        setVoting(true);
        try {
            const fresh = vote > 0
                ? await voteEvent(event.id, 1)
                : await reportEvent(event.id);
            setEvent(fresh);
            showNotice(vote > 0 ? 'Голос учтён: событие подтверждено.' : 'Жалоба отправлена.');
        } catch (error: any) {
            showNotice(error?.response?.data?.message ?? 'Не удалось отправить действие.');
        } finally {
            setVoting(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    if (!event) {
        return <View style={s.center}><Text style={s.empty}>Событие не найдено</Text></View>;
    }

    const mainMedia = event.image_url ?? event.image ?? event.gallery?.[0] ?? null;
    const votedYes = event.user_vote === 1;
    const votedNo = event.user_vote === -1;
    const eventCoordinate = event.lat != null && event.lng != null
        ? { latitude: Number(event.lat), longitude: Number(event.lng) }
        : null;

    return (
        <View style={s.root}>
            <ScrollView contentContainerStyle={s.content}>
                <View style={s.header}>
                    <View style={s.badges}>
                        <View style={[s.badge, { backgroundColor: importanceColor(event.importance) }]}>
                            <Text style={s.badgeText}>{event.importance}</Text>
                        </View>
                        <View style={s.badgeMuted}>
                            <Text style={s.badgeMutedText}>{event.type}</Text>
                        </View>
                        {!!event.highway && (
                            <View style={s.badgeMuted}>
                                <Text style={s.badgeMutedText}>{event.highway}</Text>
                            </View>
                        )}
                    </View>
                    <Text style={s.title}>{event.title}</Text>
                    <Text style={s.meta}>{event.location} · {formatDate(event.reported_at)}</Text>
                </View>

                <MediaBlock url={mainMedia} />

                <View style={s.card}>
                    <InfoRow label="Описание" value={event.description || 'Описание не указано'} />
                    <InfoRow label="Задержка" value={`${event.delay_minutes} мин`} />
                    <InfoRow label="Доверие" value={`${event.confidence_score}/10`} />
                    {event.lat !== null && event.lng !== null && (
                        <InfoRow label="Координаты" value={`${Number(event.lat).toFixed(5)}, ${Number(event.lng).toFixed(5)}`} />
                    )}
                </View>

                {eventCoordinate && (
                    <TouchableOpacity style={s.mapCard} activeOpacity={0.9} onPress={() => setMapOpen(true)}>
                        <Text style={s.mapTitle}>Место события</Text>
                        <MapView
                            style={s.eventMap}
                            provider={PROVIDER_DEFAULT}
                            initialRegion={{
                                ...eventCoordinate,
                                latitudeDelta: 0.08,
                                longitudeDelta: 0.08,
                            }}
                            scrollEnabled={false}
                            zoomEnabled={false}
                            rotateEnabled={false}
                            pitchEnabled={false}
                        >
                            <Marker
                                coordinate={eventCoordinate}
                                title={event.title}
                                description={event.highway ?? event.location}
                                pinColor={importanceColor(event.importance)}
                            />
                        </MapView>
                    </TouchableOpacity>
                )}

                <View style={s.voteRow}>
                    <TouchableOpacity
                        style={[s.confirmBtn, votedYes && s.confirmBtnSelected, votedNo && s.voteBtnDisabled]}
                        onPress={() => handleVote(1)}
                        disabled={voting}
                    >
                        <Text style={s.confirmText}>{votedYes ? 'Подтверждено' : 'Подтвердить'}</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                        style={[s.reportBtn, votedNo && s.reportBtnSelected, votedYes && s.voteBtnDisabled]}
                        onPress={() => handleVote(-1)}
                        disabled={voting}
                    >
                        <Text style={[s.reportText, votedNo && s.reportTextSelected]}>{votedNo ? 'Жалоба отправлена' : 'Пожаловаться'}</Text>
                    </TouchableOpacity>
                </View>
            </ScrollView>

            {!!notice && (
                <View style={s.toast}>
                    <Text style={s.toastText}>{notice}</Text>
                </View>
            )}

            {eventCoordinate && (
                <LocationMapModal
                    visible={mapOpen}
                    title={event.title}
                    subtitle={event.highway ?? event.location}
                    point={{ lat: eventCoordinate.latitude, lng: eventCoordinate.longitude }}
                    markerTitle={event.title}
                    markerDescription={event.location}
                    markerColor={importanceColor(event.importance)}
                    onClose={() => setMapOpen(false)}
                />
            )}
        </View>
    );
}

function MediaBlock({ url }: { url?: string | null }) {
    const [loading, setLoading] = useState(!!url);

    useEffect(() => {
        if (url) Image.prefetch(url).catch(() => {});
    }, [url]);

    if (!url) {
        return (
            <View style={s.noMedia}>
                <Text style={s.noMediaText}>Пользователь не приложил фото или видео</Text>
            </View>
        );
    }

    return (
        <View style={s.mediaWrap}>
            <Image
                source={{ uri: url }}
                style={s.cover}
                resizeMode="cover"
                onLoadEnd={() => setLoading(false)}
            />
            {loading && (
                <View style={s.mediaLoader}>
                    <ActivityIndicator color={colors.accent} />
                </View>
            )}
        </View>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <View style={s.infoRow}>
            <Text style={s.infoLabel}>{label}</Text>
            <Text style={s.infoValue}>{value}</Text>
        </View>
    );
}

function formatDate(value?: string | null) {
    if (!value) return 'только что';
    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function importanceColor(value: string) {
    if (value === 'important' || value === 'важно' || value === 'high') return colors.red;
    if (value === 'medium' || value === 'средне') return colors.accent;
    return colors.text3;
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
    content: { padding: spacing.md, paddingBottom: 40, gap: spacing.md },
    empty: { color: colors.text3 },
    header: { gap: spacing.sm },
    badges: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
    badge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: radius.sm },
    badgeText: { color: '#fff', fontSize: 10, fontWeight: '700', textTransform: 'uppercase' },
    badgeMuted: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: radius.sm, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    badgeMutedText: { color: colors.text2, fontSize: 10, fontWeight: '700', textTransform: 'uppercase' },
    title: { color: colors.text, fontSize: 28, fontWeight: '800', lineHeight: 34 },
    meta: { color: colors.text3, fontSize: 12 },
    mediaWrap: { height: 230, borderRadius: radius.md, overflow: 'hidden', backgroundColor: colors.s2 },
    cover: { width: '100%', height: '100%' },
    mediaLoader: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.s2 },
    noMedia: { height: 190, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    noMediaText: { color: colors.text3, fontSize: 12 },
    card: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    mapCard: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    mapTitle: { color: colors.text, fontSize: 15, fontWeight: '700', marginBottom: spacing.sm },
    eventMap: { height: 210, borderRadius: radius.md, overflow: 'hidden' },
    infoRow: { paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.border },
    infoLabel: { color: colors.text3, fontSize: 12, marginBottom: 3 },
    infoValue: { color: colors.text, fontSize: 14, lineHeight: 20 },
    voteRow: { flexDirection: 'row', gap: spacing.sm },
    confirmBtn: { flex: 1, alignItems: 'center', paddingVertical: 13, borderRadius: radius.md, backgroundColor: colors.green },
    confirmBtnSelected: { borderWidth: 2, borderColor: '#fff' },
    confirmText: { color: '#fff', fontWeight: '700' },
    reportBtn: { flex: 1, alignItems: 'center', paddingVertical: 13, borderRadius: radius.md, backgroundColor: colors.s2, borderWidth: 1, borderColor: colors.border },
    reportBtnSelected: { backgroundColor: colors.red, borderColor: colors.red },
    reportText: { color: colors.red, fontWeight: '700' },
    reportTextSelected: { color: '#fff' },
    voteBtnDisabled: { opacity: 0.55 },
    toast: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, backgroundColor: colors.s2, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    toastText: { color: colors.text, fontSize: 13, lineHeight: 18, textAlign: 'center' },
});
