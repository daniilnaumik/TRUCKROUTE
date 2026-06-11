import React, { useCallback, useState } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, Alert, RefreshControl,
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getProviderPois, deletePoi, ProviderPoi } from '@/api/provider';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const STATUS_LABEL: Record<string, string> = {
    pending:  'На модерации',
    active:   'Активен',
    rejected: 'Отклонён',
};

const STATUS_COLOR: Record<string, string> = {
    pending:  colors.accentDark,
    active:   colors.green,
    rejected: colors.red,
};

const POI_TYPES: Record<string, string> = {
    fuel:    'АЗС',
    parking: 'Стоянка',
    motel:   'Мотель',
    hotel:   'Гостиница',
    food:    'Кафе',
    repair:  'СТО',
};

export default function ProviderPlacesScreen() {
    const nav = useNavigation<Nav>();
    const [pois, setPois]         = useState<ProviderPoi[]>([]);
    const [loading, setLoading]   = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    async function load(refresh = false) {
        if (refresh) setRefreshing(true); else setLoading(true);
        try { setPois(await getProviderPois()); } catch {}
        finally { setLoading(false); setRefreshing(false); }
    }

    useFocusEffect(useCallback(() => { load(); }, []));

    function handleDelete(poi: ProviderPoi) {
        Alert.alert('Удалить объект', `Удалить "${poi.name}"?`, [
            { text: 'Отмена', style: 'cancel' },
            {
                text: 'Удалить', style: 'destructive', onPress: async () => {
                    try {
                        await deletePoi(poi.id);
                        setPois(prev => prev.filter(p => p.id !== poi.id));
                    } catch {
                        Alert.alert('Ошибка', 'Не удалось удалить объект');
                    }
                },
            },
        ]);
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <View style={s.root}>
            <FlatList
                data={pois}
                keyExtractor={p => String(p.id)}
                contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 100 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.accent} />}
                ListEmptyComponent={
                    <View style={s.empty}>
                        <Text style={s.emptyTitle}>Нет объектов</Text>
                        <Text style={s.emptySub}>Добавьте свою АЗС, стоянку, кафе или СТО</Text>
                    </View>
                }
                renderItem={({ item }) => (
                    <View style={s.card}>
                        <View style={s.cardHeader}>
                            <View style={{ flex: 1 }}>
                                <Text style={s.cardTitle}>{item.name}</Text>
                                <Text style={s.cardMeta}>
                                    {POI_TYPES[item.type] ?? item.type}
                                    {item.brand ? ` · ${item.brand}` : ''}
                                </Text>
                            </View>
                            <View style={[s.statusBadge, { backgroundColor: STATUS_COLOR[item.status] }]}>
                                <Text style={s.statusText}>{STATUS_LABEL[item.status]}</Text>
                            </View>
                        </View>

                        <Text style={s.location} numberOfLines={1}>{item.location}</Text>

                        <View style={s.stats}>
                            <Text style={s.statText}>
                                {item.view_count} просмотров
                                {item.fuel_price ? ` · ${item.fuel_price} ₽/л` : ''}
                                {item.rating > 0 ? ` · ★ ${item.rating.toFixed(1)}` : ''}
                            </Text>
                        </View>

                        <View style={s.actions}>
                            <TouchableOpacity
                                style={s.editBtn}
                                onPress={() => nav.navigate('ProviderPlaceForm', { id: item.id })}
                            >
                                <Text style={s.editBtnText}>Редактировать</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={s.deleteBtn} onPress={() => handleDelete(item)}>
                                <Text style={s.deleteBtnText}>✕</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                )}
            />

            <TouchableOpacity style={s.fab} onPress={() => nav.navigate('ProviderPlaceForm', {})}>
                <Text style={s.fabText}>+ Добавить объект</Text>
            </TouchableOpacity>
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
    cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
    cardTitle:  { fontSize: 15, fontWeight: '700', color: colors.text },
    cardMeta:   { fontSize: 12, color: colors.text3, marginTop: 2 },
    statusBadge:{ paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm },
    statusText: { color: '#fff', fontSize: 10, fontWeight: '700' },
    location:   { fontSize: 12, color: colors.text2, marginTop: 6 },
    stats:      { marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: colors.border },
    statText:   { fontSize: 12, color: colors.text3 },
    actions:    { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md },
    editBtn:    { flex: 1, backgroundColor: colors.s2, borderRadius: radius.sm, paddingVertical: 8, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    editBtnText:{ fontSize: 12, color: colors.text2, fontWeight: '600' },
    deleteBtn:  { width: 36, backgroundColor: colors.s2, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.border },
    deleteBtnText: { color: colors.red, fontSize: 14 },
    fab:        { position: 'absolute', bottom: 24, left: spacing.md, right: spacing.md, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', ...shadow.sm },
    fabText:    { color: '#fff', fontWeight: '700', fontSize: 14 },
});
