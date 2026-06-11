import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, TextInput, Image } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import client, { mediaUrl } from '@/api/client';
import { colors, spacing, radius, shadow } from '@/theme';

interface Poi {
    id: number; name: string; type: string; highway: string | null;
    location: string; services: string; rating: number; fuel_price: number | null;
    image_url?: string | null; gallery?: string[];
}

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function PlacesScreen() {
    const nav = useNavigation<Nav>();
    const [all, setAll]       = useState<Poi[]>([]);
    const [query, setQuery]   = useState('');
    const [type, setType]     = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        client.get('/poi?per_page=100')
            .then(r => setAll((r.data.data ?? []).map(normalizePoi)))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const TYPES = ['', 'АЗС', 'Стоянка', 'Ночлег', 'Кафе', 'СТО'];

    const filtered = all.filter(p => {
        if (type && p.type !== type) return false;
        if (query) {
            const q = query.toLowerCase();
            return [p.name, p.services, p.location].join(' ').toLowerCase().includes(q);
        }
        return true;
    });

    if (loading) return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;

    return (
        <View style={s.root}>
            {/* Search */}
            <View style={s.searchWrap}>
                <TextInput
                    style={s.search}
                    value={query}
                    onChangeText={setQuery}
                    placeholder="Поиск объекта..."
                    placeholderTextColor={colors.text3}
                    clearButtonMode="while-editing"
                />
            </View>

            {/* Type filter */}
            <FlatList
                data={TYPES}
                keyExtractor={t => t}
                horizontal
                showsHorizontalScrollIndicator={false}
                style={s.filters}
                contentContainerStyle={{ paddingHorizontal: spacing.md, gap: spacing.sm }}
                renderItem={({ item }) => (
                    <TouchableOpacity
                        style={[s.chip, type === item && s.chipActive]}
                        onPress={() => setType(item)}
                    >
                        <Text style={[s.chipText, type === item && s.chipTextActive]}>
                            {item || 'Все'}
                        </Text>
                    </TouchableOpacity>
                )}
            />

            {/* List */}
            <FlatList
                data={filtered}
                keyExtractor={p => String(p.id)}
                contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, paddingBottom: 40 }}
                ListEmptyComponent={<Text style={s.empty}>Ничего не найдено</Text>}
                renderItem={({ item }) => (
                    <TouchableOpacity style={s.card} onPress={() => nav.navigate('PlaceDetail', { id: item.id })}>
                        {item.image_url ? (
                            <Image source={{ uri: item.image_url }} style={s.image} resizeMode="cover" />
                        ) : (
                            <View style={s.noImage}>
                                <Text style={s.noImageText}>Поставщик не добавил фото</Text>
                            </View>
                        )}
                        <View style={s.cardBody}>
                            <View style={s.cardTop}>
                            <View style={s.typeBadge}><Text style={s.typeBadgeText}>{item.type}</Text></View>
                            {item.highway && <Text style={s.highway}>{item.highway}</Text>}
                            <Text style={s.rating}>★ {item.rating}</Text>
                        </View>
                        <Text style={s.name}>{item.name}</Text>
                        <Text style={s.services} numberOfLines={1}>{item.services}</Text>
                        {item.fuel_price != null && (
                            <Text style={s.price}>{item.fuel_price} ₽/л</Text>
                        )}
                        </View>
                    </TouchableOpacity>
                )}
            />
        </View>
    );
}

function normalizePoi(raw: any): Poi {
    const gallery = Array.isArray(raw.gallery) ? raw.gallery.map(mediaUrl).filter(Boolean) as string[] : [];

    return {
        ...raw,
        image_url: mediaUrl(raw.image_url ?? raw.image ?? gallery[0] ?? null),
        gallery,
    };
}

const s = StyleSheet.create({
    root:     { flex: 1, backgroundColor: colors.bg },
    center:   { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    searchWrap: { paddingHorizontal: spacing.md, paddingTop: spacing.md },
    search:   { backgroundColor: colors.s1, borderRadius: radius.sm, paddingHorizontal: spacing.md, paddingVertical: 10, fontSize: 14, color: colors.text, borderWidth: 1, borderColor: colors.border },
    filters:  { marginTop: spacing.sm, minHeight: 54, maxHeight: 58 },
    chip:     { minHeight: 42, justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 8, borderRadius: radius.full, backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border },
    chipActive: { backgroundColor: colors.accent, borderColor: colors.accent },
    chipText: { fontSize: 14, lineHeight: 18, color: colors.text2 },
    chipTextActive: { color: '#fff', fontWeight: '600' },
    empty:    { color: colors.text3, textAlign: 'center', marginTop: 48 },
    card:     { backgroundColor: colors.s1, borderRadius: radius.md, overflow: 'hidden', borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    image:    { width: '100%', height: 150, backgroundColor: colors.s2 },
    noImage:  { height: 150, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.s2 },
    noImageText: { color: colors.text3, fontSize: 12 },
    cardBody: { padding: spacing.md },
    cardTop:  { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: 6 },
    typeBadge:{ backgroundColor: colors.accentBg, paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm, borderWidth: 1, borderColor: 'rgba(145,100,0,0.2)' },
    typeBadgeText: { fontSize: 10, color: colors.accent, fontWeight: '700', letterSpacing: 0.5 },
    highway:  { flex: 1, fontSize: 11, color: colors.text3, fontFamily: 'monospace' },
    rating:   { fontSize: 12, color: colors.accent, fontWeight: '600' },
    name:     { fontSize: 14, fontWeight: '700', color: colors.text },
    services: { fontSize: 12, color: colors.text2, marginTop: 3 },
    price:    { fontSize: 12, color: colors.accent, marginTop: 4, fontFamily: 'monospace' },
});
