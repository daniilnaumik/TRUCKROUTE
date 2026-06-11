import React, { useCallback, useState } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    ActivityIndicator, Alert, RefreshControl, TextInput, Modal,
} from 'react-native';
import { useNavigation, useRoute, useFocusEffect, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import {
    getFleetDrivers, getFleetAssignments, attachDriver, detachDriver,
    createAssignment, FleetDriver, FleetAssignment, getFleet, Fleet, updateFleet,
} from '@/api/fleet';
import { useAuthStore } from '@/store/auth';
import { colors, spacing, radius, shadow } from '@/theme';
import { apiErrorMessage, validateNumber } from '@/utils/errors';

type Route = RouteProp<RootStackParamList, 'FleetDetail'>;
type Nav = NativeStackNavigationProp<RootStackParamList>;

const STATUS_LABEL: Record<string, string> = {
    issued: 'Выдано', accepted: 'Принято', in_progress: 'В пути',
    completed: 'Выполнено', cancelled: 'Отменено',
};
const STATUS_COLOR: Record<string, string> = {
    issued: colors.accentDark, accepted: colors.accent, in_progress: colors.green,
    completed: colors.text3, cancelled: colors.red,
};

export default function FleetDetailScreen() {
    const nav = useNavigation<Nav>();
    const route   = useRoute<Route>();
    const fleetId = route.params.id;
    const user = useAuthStore(state => state.user);

    const [fleet, setFleet]               = useState<Fleet | null>(null);
    const [drivers, setDrivers]           = useState<FleetDriver[]>([]);
    const [assignments, setAssignments]   = useState<FleetAssignment[]>([]);
    const [loading, setLoading]           = useState(true);
    const [refreshing, setRefreshing]     = useState(false);

    // Add driver modal
    const [showDriverModal, setShowDriverModal] = useState(false);
    const [driverUserId, setDriverUserId]       = useState('');
    const [addingDriver, setAddingDriver]       = useState(false);

    // Create assignment modal
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [assignDriverId, setAssignDriverId]   = useState('');
    const [assignOrigin, setAssignOrigin]       = useState('');
    const [assignDest, setAssignDest]           = useState('');
    const [assignDate, setAssignDate]           = useState('');
    const [assignComment, setAssignComment]     = useState('');
    const [creatingAssign, setCreatingAssign]   = useState(false);

    // Edit fleet modal
    const [showEditModal, setShowEditModal] = useState(false);
    const [editName, setEditName]           = useState('');
    const [editInn, setEditInn]             = useState('');
    const [editDesc, setEditDesc]           = useState('');
    const [savingFleet, setSavingFleet]     = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true); else setLoading(true);
        try {
            const [fleetData, d, a] = await Promise.all([
                getFleet(fleetId),
                getFleetDrivers(fleetId),
                getFleetAssignments(fleetId),
            ]);
            setFleet(fleetData);
            setDrivers(d);
            setAssignments(a);
        } catch (error: any) {
            Alert.alert('Ошибка', apiErrorMessage(error, 'Не удалось загрузить автопарк'));
        }
        finally { setLoading(false); setRefreshing(false); }
    }

    useFocusEffect(useCallback(() => { load(); }, [fleetId]));

    function openEditFleet() {
        if (!fleet) return;
        setEditName(fleet.name);
        setEditInn(fleet.inn ?? '');
        setEditDesc(fleet.description ?? '');
        setShowEditModal(true);
    }

    async function handleUpdateFleet() {
        if (!editName.trim()) {
            Alert.alert('Ошибка', 'Укажите название автопарка');
            return;
        }

        setSavingFleet(true);
        try {
            const fresh = await updateFleet(fleetId, {
                name: editName.trim(),
                inn: editInn.trim() || null,
                description: editDesc.trim() || null,
            });
            setFleet(fresh);
            setShowEditModal(false);
            load(true);
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось обновить автопарк'));
        } finally {
            setSavingFleet(false);
        }
    }

    async function handleAddDriver() {
        const parsedId = validateNumber(driverUserId, 'ID пользователя', { required: true, integer: true, min: 1 });
        if (!parsedId.ok) { Alert.alert('Проверьте данные', parsedId.message); return; }
        const uid = parsedId.value!;
        if (fleet?.owner_id && uid === fleet.owner_id) {
            Alert.alert('Ошибка', 'Владельца автопарка нельзя добавить в этот же автопарк как водителя.');
            return;
        }
        setAddingDriver(true);
        try {
            await attachDriver(fleetId, uid);
            setShowDriverModal(false);
            setDriverUserId('');
            load();
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось добавить водителя. Проверьте ID пользователя.'));
        } finally {
            setAddingDriver(false);
        }
    }

    function handleRemoveDriver(d: FleetDriver) {
        Alert.alert('Убрать водителя', `Убрать ${d.name} из автопарка?`, [
            { text: 'Отмена', style: 'cancel' },
            {
                text: 'Убрать', style: 'destructive', onPress: async () => {
                    try { await detachDriver(fleetId, d.id); load(); }
                    catch (error: any) {
                        Alert.alert('Ошибка', apiErrorMessage(error, 'Не удалось убрать водителя'));
                    }
                },
            },
        ]);
    }

    async function handleCreateAssignment() {
        const parsedDriver = validateNumber(assignDriverId, 'Водитель', { required: true, integer: true, min: 1 });
        if (!assignOrigin || !assignDest) { Alert.alert('Ошибка', 'Укажите маршрут'); return; }
        if (!parsedDriver.ok) { Alert.alert('Проверьте данные', parsedDriver.message); return; }
        if (assignDate && Number.isNaN(Date.parse(assignDate))) {
            Alert.alert('Проверьте данные', 'Плановый старт: укажите дату и время в формате ГГГГ-ММ-ДДTЧЧ:ММ.');
            return;
        }
        const driverId = parsedDriver.value!;
        setCreatingAssign(true);
        try {
            await createAssignment(fleetId, {
                driver_user_id: driverId,
                origin: assignOrigin,
                destination: assignDest,
                planned_start_at: assignDate || undefined,
                comment: assignComment || undefined,
            });
            setShowAssignModal(false);
            setAssignDriverId(''); setAssignOrigin(''); setAssignDest(''); setAssignDate(''); setAssignComment('');
            load();
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось создать задание'));
        } finally {
            setCreatingAssign(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    const canManage = user?.role === 'admin' || fleet?.is_owner;

    return (
        <ScrollView
            style={s.root}
            contentContainerStyle={{ padding: spacing.md, gap: spacing.lg, paddingBottom: 40 }}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
        >
            {fleet && (
                <View style={s.fleetCard}>
                    <View style={s.fleetCardHeader}>
                        <Text style={s.fleetName}>{fleet.name}</Text>
                        {canManage && (
                            <TouchableOpacity style={s.editBtn} onPress={openEditFleet}>
                                <Text style={s.editBtnText}>Редактировать</Text>
                            </TouchableOpacity>
                        )}
                    </View>
                    {!!fleet.owner && <Text style={s.fleetMeta}>Владелец: {fleet.owner.name}</Text>}
                    {!!fleet.inn && <Text style={s.fleetMeta}>ИНН: {fleet.inn}</Text>}
                    {!!fleet.description && <Text style={s.fleetDesc}>{fleet.description}</Text>}
                    <View style={s.statsRow}>
                        <Stat label="водителей" value={fleet.drivers_count ?? drivers.length} />
                        <Stat label="заданий" value={fleet.assignments_count ?? assignments.length} />
                        <Stat label="выполнено" value={fleet.completed_assignments_count ?? assignments.filter(a => a.status === 'completed').length} />
                    </View>
                </View>
            )}

            {/* Drivers section */}
            <View>
                <View style={s.sectionHeader}>
                    <Text style={s.sectionTitle}>Водители ({drivers.length})</Text>
                    {canManage && (
                        <TouchableOpacity style={s.addBtn} onPress={() => setShowDriverModal(true)}>
                            <Text style={s.addBtnText}>+ Добавить</Text>
                        </TouchableOpacity>
                    )}
                </View>
                {drivers.length === 0
                    ? <Text style={s.empty}>Водителей нет — добавьте по ID аккаунта</Text>
                    : drivers.map(d => (
                        <View key={d.id} style={s.driverCard}>
                            <View style={s.driverAvatar}>
                                <Text style={s.driverAvatarText}>{d.name[0].toUpperCase()}</Text>
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={s.driverName}>{d.name}</Text>
                                <Text style={s.driverStats}>
                                    Выполнено: {d.completed_assignments_count ?? 0}
                                    {d.rating_avg ? ` · рейтинг ${d.rating_avg}/5 (${d.rating_count ?? 0})` : ' · рейтинга пока нет'}
                                </Text>
                            </View>
                            {canManage && (
                                <TouchableOpacity onPress={() => handleRemoveDriver(d)}>
                                    <Text style={{ color: colors.red, fontSize: 18, padding: 4 }}>✕</Text>
                                </TouchableOpacity>
                            )}
                        </View>
                    ))
                }
            </View>

            {/* Assignments section */}
            <View>
                <View style={s.sectionHeader}>
                    <Text style={s.sectionTitle}>Задания ({assignments.length})</Text>
                    {canManage && (
                        <TouchableOpacity style={s.addBtn} onPress={() => setShowAssignModal(true)}>
                            <Text style={s.addBtnText}>+ Создать</Text>
                        </TouchableOpacity>
                    )}
                </View>
                {assignments.length === 0
                    ? <Text style={s.empty}>Заданий нет</Text>
                    : assignments.map(a => (
                        <TouchableOpacity
                            key={a.id}
                            style={s.assignCard}
                            activeOpacity={0.85}
                            onPress={() => nav.navigate('AssignmentDetail', canManage ? { id: a.id, fleetId } : { id: a.id })}
                        >
                            <View style={s.assignHeader}>
                                <Text style={s.assignRoute} numberOfLines={1}>
                                    {a.origin} → {a.destination}
                                </Text>
                                <View style={[s.statusBadge, { backgroundColor: STATUS_COLOR[a.status] }]}>
                                    <Text style={s.statusText}>{STATUS_LABEL[a.status]}</Text>
                                </View>
                            </View>
                            {a.driver && <Text style={s.assignDriver}>Водитель: {a.driver.name}</Text>}
                            {a.planned_start_at && (
                                <Text style={s.assignDate}>
                                    Старт: {new Date(a.planned_start_at).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                </Text>
                            )}
                            {a.comment && <Text style={s.assignComment} numberOfLines={2}>{a.comment}</Text>}
                            {!!a.completed_at && <Text style={s.assignDate}>Выполнено: {new Date(a.completed_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</Text>}
                            {!!a.rating_stars && <Text style={s.assignRating}>Оценка: {'★'.repeat(a.rating_stars)} {a.rating_stars}/5</Text>}
                        </TouchableOpacity>
                    ))
                }
            </View>

            {/* Edit fleet modal */}
            <Modal visible={showEditModal} transparent animationType="slide" onRequestClose={() => setShowEditModal(false)}>
                <View style={s.overlay}>
                    <View style={s.modal}>
                        <Text style={s.modalTitle}>Редактировать автопарк</Text>

                        <Text style={s.fieldLabel}>Название *</Text>
                        <TextInput style={s.input} value={editName} onChangeText={setEditName} placeholder="Название автопарка" placeholderTextColor={colors.text3} />

                        <Text style={s.fieldLabel}>ИНН</Text>
                        <TextInput style={s.input} value={editInn} onChangeText={setEditInn} placeholder="7700000000" placeholderTextColor={colors.text3} keyboardType="numeric" />

                        <Text style={s.fieldLabel}>Описание</Text>
                        <TextInput
                            style={[s.input, { height: 86, textAlignVertical: 'top' }]}
                            value={editDesc}
                            onChangeText={setEditDesc}
                            placeholder="Информация о компании, маршрутах, условиях работы..."
                            placeholderTextColor={colors.text3}
                            multiline
                        />

                        <View style={{ flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md }}>
                            <TouchableOpacity style={s.cancelBtn} onPress={() => setShowEditModal(false)}>
                                <Text style={s.cancelBtnText}>Отмена</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={s.createBtn} onPress={handleUpdateFleet} disabled={savingFleet}>
                                {savingFleet ? <ActivityIndicator color="#fff" /> : <Text style={s.createBtnText}>Сохранить</Text>}
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Add driver modal */}
            <Modal visible={showDriverModal} transparent animationType="slide" onRequestClose={() => setShowDriverModal(false)}>
                <View style={s.overlay}>
                    <View style={s.modal}>
                        <Text style={s.modalTitle}>Добавить водителя</Text>
                        <Text style={s.modalHint}>Водитель должен сообщить вам свой ID аккаунта (виден в разделе Профиль)</Text>
                        <TextInput
                            style={s.input}
                            value={driverUserId}
                            onChangeText={setDriverUserId}
                            placeholder="ID пользователя"
                            placeholderTextColor={colors.text3}
                            keyboardType="numeric"
                        />
                        <View style={{ flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md }}>
                            <TouchableOpacity style={s.cancelBtn} onPress={() => setShowDriverModal(false)}>
                                <Text style={s.cancelBtnText}>Отмена</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={s.createBtn} onPress={handleAddDriver} disabled={addingDriver}>
                                {addingDriver ? <ActivityIndicator color="#fff" /> : <Text style={s.createBtnText}>Добавить</Text>}
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Create assignment modal */}
            <Modal visible={showAssignModal} transparent animationType="slide" onRequestClose={() => setShowAssignModal(false)}>
                <View style={s.overlay}>
                    <ScrollView>
                        <View style={[s.modal, { marginTop: 100 }]}>
                            <Text style={s.modalTitle}>Новое задание</Text>

                            <Text style={s.fieldLabel}>Водитель *</Text>
                            <View style={s.chips}>
                                {drivers.map(d => (
                                    <TouchableOpacity
                                        key={d.id}
                                        style={[s.chip, assignDriverId === String(d.id) && s.chipActive]}
                                        onPress={() => setAssignDriverId(String(d.id))}
                                    >
                                        <Text style={[s.chipText, assignDriverId === String(d.id) && s.chipTextActive]}>{d.name}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>

                            <Text style={s.fieldLabel}>Откуда *</Text>
                            <TextInput style={s.input} value={assignOrigin} onChangeText={setAssignOrigin} placeholder="Москва" placeholderTextColor={colors.text3} />

                            <Text style={s.fieldLabel}>Куда *</Text>
                            <TextInput style={s.input} value={assignDest} onChangeText={setAssignDest} placeholder="Санкт-Петербург" placeholderTextColor={colors.text3} />

                            <Text style={s.fieldLabel}>Дата старта (ГГГГ-ММ-ДДTЧЧ:ММ)</Text>
                            <TextInput style={s.input} value={assignDate} onChangeText={setAssignDate} placeholder="2026-06-01T08:00" placeholderTextColor={colors.text3} />

                            <Text style={s.fieldLabel}>Комментарий</Text>
                            <TextInput
                                style={[s.input, { height: 72, textAlignVertical: 'top' }]}
                                value={assignComment}
                                onChangeText={setAssignComment}
                                placeholder="Особые указания..."
                                placeholderTextColor={colors.text3}
                                multiline
                            />

                            <View style={{ flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md }}>
                                <TouchableOpacity style={s.cancelBtn} onPress={() => setShowAssignModal(false)}>
                                    <Text style={s.cancelBtnText}>Отмена</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.createBtn} onPress={handleCreateAssignment} disabled={creatingAssign}>
                                    {creatingAssign ? <ActivityIndicator color="#fff" /> : <Text style={s.createBtnText}>Создать</Text>}
                                </TouchableOpacity>
                            </View>
                        </View>
                    </ScrollView>
                </View>
            </Modal>
        </ScrollView>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <View style={s.stat}>
            <Text style={s.statValue}>{value}</Text>
            <Text style={s.statLabel}>{label}</Text>
        </View>
    );
}

const s = StyleSheet.create({
    root:   { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    empty:  { fontSize: 13, color: colors.text3, marginTop: spacing.sm },

    fleetCard: { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    fleetCardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: spacing.sm },
    fleetName: { fontSize: 17, color: colors.text, fontWeight: '800' },
    fleetMeta: { fontSize: 12, color: colors.text3, marginTop: 5 },
    fleetDesc: { fontSize: 13, color: colors.text2, lineHeight: 18, marginTop: 8 },
    editBtn: { borderWidth: 1, borderColor: colors.border, borderRadius: radius.sm, paddingHorizontal: 10, paddingVertical: 6, backgroundColor: colors.s2 },
    editBtnText: { color: colors.text2, fontSize: 12, fontWeight: '700' },
    statsRow: { flexDirection: 'row', marginTop: spacing.md, borderTopWidth: 1, borderTopColor: colors.border, paddingTop: spacing.md },
    stat: { flex: 1 },
    statValue: { color: colors.accent, fontSize: 18, fontWeight: '800' },
    statLabel: { color: colors.text3, fontSize: 11, marginTop: 2 },

    sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: spacing.sm },
    sectionTitle:  { fontSize: 17, fontWeight: '700', color: colors.text },
    addBtn:        { backgroundColor: colors.accentBg, paddingHorizontal: spacing.md, paddingVertical: 6, borderRadius: radius.sm, borderWidth: 1, borderColor: 'rgba(145,100,0,0.3)' },
    addBtnText:    { fontSize: 12, color: colors.accent, fontWeight: '600' },

    driverCard:       { flexDirection: 'row', alignItems: 'center', gap: spacing.md, backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm, ...shadow.sm },
    driverAvatar:     { width: 40, height: 40, borderRadius: 20, backgroundColor: colors.accent, justifyContent: 'center', alignItems: 'center' },
    driverAvatarText: { color: '#fff', fontWeight: '700', fontSize: 16 },
    driverName:       { fontSize: 14, fontWeight: '600', color: colors.text },
    driverStats:      { fontSize: 12, color: colors.text2, marginTop: 4 },

    assignCard:   { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm, ...shadow.sm },
    assignHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: spacing.sm },
    assignRoute:  { flex: 1, fontSize: 14, fontWeight: '600', color: colors.text },
    statusBadge:  { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    statusText:   { color: '#fff', fontSize: 10, fontWeight: '700' },
    assignDriver: { fontSize: 12, color: colors.text2, marginTop: 4 },
    assignDate:   { fontSize: 12, color: colors.text3, marginTop: 2, fontFamily: 'monospace' },
    assignComment:{ fontSize: 12, color: colors.text3, marginTop: 4 },
    assignRating: { fontSize: 12, color: colors.accent, fontWeight: '700', marginTop: 4 },

    overlay:    { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
    modal:      { backgroundColor: colors.bg, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg, padding: spacing.xl, gap: spacing.sm },
    modalTitle: { fontSize: 18, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
    modalHint:  { fontSize: 12, color: colors.text3, marginBottom: spacing.sm },
    fieldLabel: { fontSize: 12, color: colors.text3, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
    input:      { backgroundColor: colors.s1, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 14, color: colors.text },
    chips:      { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.sm },
    chip:       { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radius.full, backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border },
    chipActive:     { backgroundColor: colors.accentBg, borderColor: colors.accent },
    chipText:       { fontSize: 13, color: colors.text2 },
    chipTextActive: { color: colors.accent, fontWeight: '600' },
    cancelBtn:  { flex: 1, backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    cancelBtnText: { fontSize: 14, color: colors.text2, fontWeight: '600' },
    createBtn:  { flex: 2, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center' },
    createBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
