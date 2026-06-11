import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import {
    acceptAssignment,
    cancelAssignment,
    completeAssignment,
    FleetAssignment,
    getDriverAssignment,
    getFleetAssignment,
    rateAssignment,
    updateAssignment,
} from '@/api/fleet';
import { RootStackParamList } from '@/navigation';
import { useAuthStore } from '@/store/auth';
import { colors, radius, shadow, spacing } from '@/theme';
import { apiErrorMessage } from '@/utils/errors';
import { formatDate, statusColor, statusLabel } from './DriverAssignmentsScreen';

type Route = RouteProp<RootStackParamList, 'AssignmentDetail'>;
type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function AssignmentDetailScreen() {
    const nav = useNavigation<Nav>();
    const route = useRoute<Route>();
    const { id, fleetId } = route.params;
    const user = useAuthStore(state => state.user);
    const [assignment, setAssignment] = useState<FleetAssignment | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [ratingSaving, setRatingSaving] = useState(false);
    const [ratingDraft, setRatingDraft] = useState(0);
    const [ratingComment, setRatingComment] = useState('');

    async function load() {
        setLoading(true);
        try {
            const data = fleetId ? await getFleetAssignment(fleetId, id) : await getDriverAssignment(id);
            setAssignment(data);
            setRatingDraft(data.rating_stars ?? 0);
            setRatingComment(data.rating_comment ?? '');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { load(); }, [id, fleetId]);

    async function runAction(action: 'accept' | 'complete' | 'cancel') {
        if (!assignment || saving) return;

        setSaving(true);
        try {
            const fresh = action === 'accept'
                ? await acceptAssignment(assignment.id)
                : action === 'complete'
                    ? await completeAssignment(assignment.id)
                    : fleetId
                        ? await updateAssignment(fleetId, assignment.id, { status: 'cancelled' })
                        : await cancelAssignment(assignment.id);
            setAssignment(fresh);
        } catch (error: any) {
            Alert.alert('Ошибка', apiErrorMessage(error, 'Не удалось изменить задание'));
        } finally {
            setSaving(false);
        }
    }

    async function saveRating() {
        if (!assignment || !fleetId || ratingSaving) return;
        if (ratingDraft < 1 || ratingDraft > 5) {
            Alert.alert('Оценка', 'Выберите оценку от 1 до 5 звезд.');
            return;
        }

        setRatingSaving(true);
        try {
            const fresh = await rateAssignment(fleetId, assignment.id, {
                rating_stars: ratingDraft,
                rating_comment: ratingComment.trim() || null,
            });
            setAssignment(fresh);
            setRatingDraft(fresh.rating_stars ?? 0);
            setRatingComment(fresh.rating_comment ?? '');
            Alert.alert('Готово', 'Оценка сохранена.');
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось сохранить оценку.'));
        } finally {
            setRatingSaving(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    if (!assignment) {
        return <View style={s.center}><Text style={s.empty}>Задание не найдено</Text></View>;
    }

    const canAccept = !fleetId && assignment.status === 'issued';
    const canComplete = !fleetId && ['accepted', 'in_progress'].includes(assignment.status);
    const canCancel = assignment.status !== 'completed' && assignment.status !== 'cancelled';
    const canRate = !!fleetId
        && assignment.status === 'completed'
        && (user?.role === 'admin' || assignment.fleet?.owner?.id === user?.id);

    return (
        <ScrollView style={s.root} contentContainerStyle={s.content}>
            <View style={s.header}>
                <View style={[s.statusBadge, { backgroundColor: statusColor(assignment.status) }]}>
                    <Text style={s.statusText}>{statusLabel(assignment.status)}</Text>
                </View>
                <Text style={s.title}>{assignment.origin} → {assignment.destination}</Text>
            </View>

            <TouchableOpacity style={s.mapBtn} onPress={() => nav.navigate('AssignmentMap', { id: assignment.id, fleetId })}>
                <Text style={s.mapBtnText}>Показать путь на карте</Text>
            </TouchableOpacity>

            <View style={s.card}>
                <Info label="Откуда" value={assignment.origin} />
                <Info label="Куда" value={assignment.destination} />
                {!!assignment.planned_start_at && <Info label="Плановый старт" value={formatDate(assignment.planned_start_at)} />}
                <Info
                    label="Транспорт"
                    value={assignment.vehicle_source === 'fleet'
                        ? (assignment.vehicle?.title ?? 'Фура автопарка')
                        : 'Личная фура водителя'}
                />
                {!!assignment.completed_at && <Info label="Выполнено" value={formatDate(assignment.completed_at)} />}
                {!!assignment.comment && <Info label="Комментарий" value={assignment.comment} />}
            </View>

            {(assignment.rating_stars || canRate) && (
                <View style={s.card}>
                    <Text style={s.cardTitle}>Оценка выполнения</Text>
                    {assignment.rating_stars ? (
                        <>
                            <Stars value={assignment.rating_stars} />
                            {!!assignment.rating_comment && <Text style={s.ratingComment}>{assignment.rating_comment}</Text>}
                            {!!assignment.rated_at && <Text style={s.ratingMeta}>Оценено: {formatDate(assignment.rated_at)}</Text>}
                        </>
                    ) : (
                        <Text style={s.infoValue}>Оценка пока не выставлена.</Text>
                    )}

                    {canRate && (
                        <View style={s.rateBox}>
                            <Text style={s.infoLabel}>Поставить оценку</Text>
                            <View style={s.starRow}>
                                {[1, 2, 3, 4, 5].map(star => (
                                    <TouchableOpacity key={star} onPress={() => setRatingDraft(star)} style={s.starBtn}>
                                        <Text style={[s.star, star <= ratingDraft && s.starActive]}>★</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                            <TextInput
                                style={[s.input, { height: 72, textAlignVertical: 'top' }]}
                                value={ratingComment}
                                onChangeText={setRatingComment}
                                placeholder="Комментарий к выполнению"
                                placeholderTextColor={colors.text3}
                                multiline
                            />
                            <TouchableOpacity style={s.primaryBtn} onPress={saveRating} disabled={ratingSaving}>
                                {ratingSaving ? <ActivityIndicator color="#fff" /> : <Text style={s.primaryText}>Сохранить оценку</Text>}
                            </TouchableOpacity>
                        </View>
                    )}
                </View>
            )}

            {!!assignment.fleet && (
                <View style={s.card}>
                    <Text style={s.cardTitle}>Автопарк</Text>
                    <Info label="Название" value={assignment.fleet.name} />
                    {!!assignment.fleet.inn && <Info label="ИНН" value={assignment.fleet.inn} />}
                    {!!assignment.fleet.owner && <Info label="Владелец" value={assignment.fleet.owner.name} />}
                    {!!assignment.fleet.description && <Info label="Описание" value={assignment.fleet.description} />}
                </View>
            )}

            {(canAccept || canComplete || canCancel) && (
                <View style={s.actions}>
                    {canAccept && (
                        <TouchableOpacity style={s.primaryBtn} onPress={() => runAction('accept')} disabled={saving}>
                            <Text style={s.primaryText}>Принять задание</Text>
                        </TouchableOpacity>
                    )}
                    {canComplete && (
                        <TouchableOpacity style={s.primaryBtn} onPress={() => runAction('complete')} disabled={saving}>
                            <Text style={s.primaryText}>Завершить</Text>
                        </TouchableOpacity>
                    )}
                    {canCancel && (
                        <TouchableOpacity style={s.secondaryBtn} onPress={() => runAction('cancel')} disabled={saving}>
                            <Text style={s.secondaryText}>Отменить</Text>
                        </TouchableOpacity>
                    )}
                </View>
            )}
        </ScrollView>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <View style={s.infoRow}>
            <Text style={s.infoLabel}>{label}</Text>
            <Text style={s.infoValue}>{value}</Text>
        </View>
    );
}

function Stars({ value }: { value: number }) {
    return (
        <View style={s.starRow}>
            {[1, 2, 3, 4, 5].map(star => (
                <Text key={star} style={[s.star, star <= value && s.starActive]}>★</Text>
            ))}
            <Text style={s.ratingValue}>{value}/5</Text>
        </View>
    );
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg },
    content: { padding: spacing.md, gap: spacing.md, paddingBottom: 40 },
    empty: { color: colors.text3 },
    header: { gap: spacing.sm },
    statusBadge: { alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 4, borderRadius: radius.sm },
    statusText: { color: '#fff', fontSize: 10, fontWeight: '700', textTransform: 'uppercase' },
    title: { color: colors.text, fontSize: 24, fontWeight: '800', lineHeight: 30 },
    mapBtn: { alignItems: 'center', backgroundColor: colors.accentBg, borderRadius: radius.md, paddingVertical: 13, borderWidth: 1, borderColor: 'rgba(145,100,0,0.25)' },
    mapBtnText: { color: colors.accent, fontSize: 14, fontWeight: '700' },
    card: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    cardTitle: { color: colors.text, fontSize: 16, fontWeight: '700', marginBottom: spacing.sm },
    infoRow: { paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: colors.border },
    infoLabel: { color: colors.text3, fontSize: 12, marginBottom: 3 },
    infoValue: { color: colors.text, fontSize: 14, lineHeight: 20 },
    input: { backgroundColor: colors.bg, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 14, color: colors.text },
    rateBox: { gap: spacing.sm, marginTop: spacing.md },
    starRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    starBtn: { paddingVertical: 2, paddingRight: 4 },
    star: { color: colors.text3, fontSize: 24, lineHeight: 28 },
    starActive: { color: colors.accent },
    ratingValue: { color: colors.text2, fontSize: 13, marginLeft: spacing.sm, fontWeight: '700' },
    ratingComment: { color: colors.text2, fontSize: 13, lineHeight: 19, marginTop: spacing.sm },
    ratingMeta: { color: colors.text3, fontSize: 12, marginTop: spacing.sm },
    actions: { gap: spacing.sm },
    primaryBtn: { alignItems: 'center', backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14 },
    primaryText: { color: '#fff', fontSize: 14, fontWeight: '700' },
    secondaryBtn: { alignItems: 'center', backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, borderWidth: 1, borderColor: colors.border },
    secondaryText: { color: colors.red, fontSize: 14, fontWeight: '700' },
});
