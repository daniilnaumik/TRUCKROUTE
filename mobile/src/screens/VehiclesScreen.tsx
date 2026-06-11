import React, { useEffect, useState, useCallback } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, Alert, RefreshControl,
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getVehicles, deleteVehicle, activateVehicle, Vehicle } from '@/api/vehicles';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const VEHICLE_TYPE_LABELS: Record<string, string> = {
    'тягач': 'Тягач+п/п',
    'одиночка': 'Одиночка',
    'фургон': 'Фургон',
    'реф': 'Рефрижератор',
    'цистерна': 'Цистерна',
};

export default function VehiclesScreen() {
    const nav = useNavigation<Nav>();
    const [vehicles, setVehicles] = useState<Vehicle[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true); else setLoading(true);
        try {
            setVehicles(await getVehicles());
        } catch {
            // ignore
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useFocusEffect(useCallback(() => { load(); }, []));

    function handleDelete(v: Vehicle) {
        Alert.alert('Удалить транспорт', `Удалить "${v.title}"?`, [
            { text: 'Отмена', style: 'cancel' },
            {
                text: 'Удалить', style: 'destructive', onPress: async () => {
                    try {
                        await deleteVehicle(v.id);
                        setVehicles(prev => prev.filter(x => x.id !== v.id));
                    } catch {
                        Alert.alert('Ошибка', 'Не удалось удалить транспорт');
                    }
                },
            },
        ]);
    }

    async function handleActivate(v: Vehicle) {
        try {
            await activateVehicle(v.id);
            setVehicles(prev => prev.map(x => ({ ...x, is_active: x.id === v.id })));
        } catch {
            Alert.alert('Ошибка', 'Не удалось активировать транспорт');
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <View style={s.root}>
            <FlatList
                data={vehicles}
                keyExtractor={v => String(v.id)}
                contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 100 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
                ListEmptyComponent={
                    <View style={s.empty}>
                        <Text style={s.emptyTitle}>Транспорт не добавлен</Text>
                        <Text style={s.emptySub}>Добавьте свою машину для расчёта маршрутов</Text>
                    </View>
                }
                renderItem={({ item }) => (
                    <View style={[s.card, item.is_active && s.cardActive]}>
                        <View style={s.cardHeader}>
                            <View style={{ flex: 1 }}>
                                <Text style={s.title}>{item.title}</Text>
                                <Text style={s.meta}>
                                    {VEHICLE_TYPE_LABELS[item.type] ?? item.type}
                                    {item.model ? ` · ${item.model}` : ''}
                                </Text>
                            </View>
                            {item.is_active && (
                                <View style={s.activeBadge}>
                                    <Text style={s.activeBadgeText}>АКТИВЕН</Text>
                                </View>
                            )}
                        </View>

                        <View style={s.stats}>
                            <Stat label="Бак" value={`${item.tank_capacity_l} л`} />
                            <Stat label="Расход" value={`${item.consumption_l_per_100} л/100`} />
                            <Stat label="Скорость" value={`${item.cruise_speed_kmh} км/ч`} />
                        </View>

                        <View style={s.actions}>
                            {!item.is_active && (
                                <TouchableOpacity style={s.btnActivate} onPress={() => handleActivate(item)}>
                                    <Text style={s.btnActivateText}>Сделать активным</Text>
                                </TouchableOpacity>
                            )}
                            <TouchableOpacity
                                style={s.btnEdit}
                                onPress={() => nav.navigate('VehicleForm', { id: item.id })}
                            >
                                <Text style={s.btnEditText}>Изменить</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={s.btnDelete} onPress={() => handleDelete(item)}>
                                <Text style={s.btnDeleteText}>✕</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                )}
            />

            <TouchableOpacity style={s.fab} onPress={() => nav.navigate('VehicleForm', {})}>
                <Text style={s.fabText}>+ Добавить транспорт</Text>
            </TouchableOpacity>
        </View>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <View style={{ alignItems: 'center' }}>
            <Text style={{ fontSize: 13, fontWeight: '700', color: colors.accent, fontFamily: 'monospace' }}>{value}</Text>
            <Text style={{ fontSize: 10, color: colors.text3, marginTop: 2 }}>{label}</Text>
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
    cardActive: { borderColor: colors.accent },
    cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
    title:      { fontSize: 15, fontWeight: '700', color: colors.text },
    meta:       { fontSize: 12, color: colors.text3, marginTop: 2 },
    activeBadge:     { backgroundColor: colors.accent, paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    activeBadgeText: { color: '#fff', fontSize: 10, fontWeight: '700', letterSpacing: 0.5 },
    stats:      { flexDirection: 'row', justifyContent: 'space-around', marginTop: spacing.md, paddingTop: spacing.md, borderTopWidth: 1, borderTopColor: colors.border },
    actions:    { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md },
    btnActivate:     { flex: 1, backgroundColor: colors.accentBg, borderRadius: radius.sm, paddingVertical: 8, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(145,100,0,0.3)' },
    btnActivateText: { fontSize: 12, color: colors.accent, fontWeight: '600' },
    btnEdit:         { flex: 1, backgroundColor: colors.s2, borderRadius: radius.sm, paddingVertical: 8, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    btnEditText:     { fontSize: 12, color: colors.text2, fontWeight: '600' },
    btnDelete:       { width: 36, backgroundColor: colors.s2, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.border },
    btnDeleteText:   { fontSize: 14, color: colors.red },
    fab:        { position: 'absolute', bottom: 24, left: spacing.md, right: spacing.md, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', ...shadow.sm },
    fabText:    { color: '#fff', fontWeight: '700', fontSize: 14 },
});
