import React, { useCallback, useState } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, Alert, RefreshControl, TextInput, Modal,
    Keyboard, KeyboardAvoidingView, Platform, TouchableWithoutFeedback,
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getFleets, createFleet, Fleet } from '@/api/fleet';
import { useAuthStore } from '@/store/auth';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function FleetScreen() {
    const nav = useNavigation<Nav>();
    const user = useAuthStore(state => state.user);
    const canManageFleets = user?.role === 'fleet' || user?.role === 'admin';
    const [fleets, setFleets]         = useState<Fleet[]>([]);
    const [loading, setLoading]       = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [creating, setCreating]     = useState(false);
    const [showModal, setShowModal]   = useState(false);
    const [newName, setNewName]       = useState('');
    const [newInn, setNewInn]         = useState('');
    const [newDesc, setNewDesc]       = useState('');

    async function load(refresh = false) {
        if (refresh) setRefreshing(true); else setLoading(true);
        try { setFleets(await getFleets()); } catch {}
        finally { setLoading(false); setRefreshing(false); }
    }

    useFocusEffect(useCallback(() => { load(); }, []));

    async function handleCreate() {
        if (!newName.trim()) { Alert.alert('Ошибка', 'Укажите название автопарка'); return; }
        setCreating(true);
        try {
            const fleet = await createFleet({ name: newName.trim(), inn: newInn.trim() || undefined, description: newDesc.trim() || undefined });
            setFleets(prev => [fleet, ...prev]);
            setShowModal(false);
            setNewName(''); setNewInn(''); setNewDesc('');
        } catch {
            Alert.alert('Ошибка', 'Не удалось создать автопарк');
        } finally {
            setCreating(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <View style={s.root}>
            <FlatList
                data={fleets}
                keyExtractor={f => String(f.id)}
                contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 100 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
                ListEmptyComponent={
                    <View style={s.empty}>
                        <Text style={s.emptyTitle}>{canManageFleets ? 'Автопарков нет' : 'Вы пока не добавлены в автопарк'}</Text>
                        <Text style={s.emptySub}>
                            {canManageFleets
                                ? 'Создайте автопарк и добавьте водителей'
                                : 'Когда владелец автопарка добавит вас как сотрудника, он появится здесь.'}
                        </Text>
                    </View>
                }
                renderItem={({ item }) => (
                    <TouchableOpacity
                        style={s.card}
                        onPress={() => nav.navigate('FleetDetail', { id: item.id })}
                    >
                        <Text style={s.cardTitle}>{item.name}</Text>
                        {item.owner && <Text style={s.cardOwner}>Владелец: {item.owner.name}</Text>}
                        {item.inn && <Text style={s.cardInn}>ИНН: {item.inn}</Text>}
                        {item.description && <Text style={s.cardDesc} numberOfLines={2}>{item.description}</Text>}
                        <View style={s.cardStats}>
                            <Text style={s.cardStat}>Водители: {item.drivers_count ?? 0}</Text>
                            <Text style={s.cardStat}>Задания: {item.assignments_count ?? 0}</Text>
                            <Text style={s.cardStat}>Выполнено: {item.completed_assignments_count ?? 0}</Text>
                        </View>
                        <Text style={s.cardArrow}>{item.is_owner ? 'Водители и задания →' : 'Информация и задания →'}</Text>
                    </TouchableOpacity>
                )}
            />

            {canManageFleets && (
                <TouchableOpacity style={s.fab} onPress={() => setShowModal(true)}>
                    <Text style={s.fabText}>+ Создать автопарк</Text>
                </TouchableOpacity>
            )}

            <Modal visible={showModal} transparent animationType="slide" onRequestClose={() => setShowModal(false)}>
                <View style={s.overlay}>
                    <KeyboardAvoidingView style={s.modalKeyboard} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
                        <TouchableWithoutFeedback onPress={Keyboard.dismiss} accessible={false}>
                            <View style={s.modal}>
                                <Text style={s.modalTitle}>Новый автопарк</Text>

                        <Text style={s.fieldLabel}>Название *</Text>
                        <TextInput style={s.input} value={newName} onChangeText={setNewName} placeholder="Логистическая компания" placeholderTextColor={colors.text3} />

                        <Text style={s.fieldLabel}>ИНН</Text>
                        <TextInput style={s.input} value={newInn} onChangeText={setNewInn} placeholder="7700000000" placeholderTextColor={colors.text3} keyboardType="numeric" />

                        <Text style={s.fieldLabel}>Описание</Text>
                        <TextInput
                            style={[s.input, { height: 72, textAlignVertical: 'top' }]}
                            value={newDesc}
                            onChangeText={setNewDesc}
                            placeholder="О компании..."
                            placeholderTextColor={colors.text3}
                            multiline
                        />

                                <View style={{ flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md }}>
                                    <TouchableOpacity style={s.cancelBtn} onPress={() => setShowModal(false)}>
                                        <Text style={s.cancelBtnText}>Отмена</Text>
                                    </TouchableOpacity>
                                    <TouchableOpacity style={s.createBtn} onPress={handleCreate} disabled={creating}>
                                        {creating
                                            ? <ActivityIndicator color="#fff" />
                                            : <Text style={s.createBtnText}>Создать</Text>
                                        }
                                    </TouchableOpacity>
                                </View>
                            </View>
                        </TouchableWithoutFeedback>
                    </KeyboardAvoidingView>
                </View>
            </Modal>
        </View>
    );
}

const s = StyleSheet.create({
    root:       { flex: 1, backgroundColor: colors.bg },
    center:     { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    empty:      { alignItems: 'center', marginTop: 80, gap: spacing.sm },
    emptyTitle: { fontSize: 16, fontWeight: '600', color: colors.text },
    emptySub:   { fontSize: 13, color: colors.text3, textAlign: 'center' },
    card:       { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    cardTitle:  { fontSize: 15, fontWeight: '700', color: colors.text },
    cardOwner:  { fontSize: 12, color: colors.text2, marginTop: 5 },
    cardInn:    { fontSize: 12, color: colors.text3, marginTop: 4, fontFamily: 'monospace' },
    cardDesc:   { fontSize: 13, color: colors.text2, marginTop: 6 },
    cardStats:  { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginTop: spacing.sm },
    cardStat:   { fontSize: 11, color: colors.text3, backgroundColor: colors.s2, borderRadius: radius.sm, paddingHorizontal: 8, paddingVertical: 4 },
    cardArrow:  { fontSize: 12, color: colors.accent, marginTop: spacing.sm, fontWeight: '600' },
    fab:        { position: 'absolute', bottom: 24, left: spacing.md, right: spacing.md, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', ...shadow.sm },
    fabText:    { color: '#fff', fontWeight: '700', fontSize: 14 },
    overlay:    { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
    modalKeyboard: { width: '100%' },
    modal:      { backgroundColor: colors.bg, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg, padding: spacing.xl, gap: spacing.sm },
    modalTitle: { fontSize: 18, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
    fieldLabel: { fontSize: 12, color: colors.text3, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
    input:      { backgroundColor: colors.s1, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 14, color: colors.text },
    cancelBtn:  { flex: 1, backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    cancelBtnText: { fontSize: 14, color: colors.text2, fontWeight: '600' },
    createBtn:  { flex: 2, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center' },
    createBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
