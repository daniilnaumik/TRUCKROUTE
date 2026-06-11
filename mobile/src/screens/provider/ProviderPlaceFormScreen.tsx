import React, { useEffect, useState } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TextInput,
    TouchableOpacity, ActivityIndicator, Alert, Switch,
} from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getProviderPois, createPoi, updatePoi, PoiPayload } from '@/api/provider';
import client from '@/api/client';
import LocationMapModal, { LocationPoint } from '@/components/LocationMapModal';
import { colors, spacing, radius, shadow } from '@/theme';
import { apiErrorMessage, validateNumber } from '@/utils/errors';

type Nav   = NativeStackNavigationProp<RootStackParamList>;
type Route = RouteProp<RootStackParamList, 'ProviderPlaceForm'>;

const POI_TYPES = ['fuel', 'parking', 'motel', 'hotel', 'food', 'repair'];
const POI_TYPE_LABELS: Record<string, string> = {
    fuel: 'АЗС', parking: 'Стоянка', motel: 'Мотель',
    hotel: 'Гостиница', food: 'Кафе', repair: 'СТО',
};

export default function ProviderPlaceFormScreen() {
    const nav   = useNavigation<Nav>();
    const route = useRoute<Route>();
    const id    = route.params?.id;

    const [loading, setLoading] = useState(!!id);
    const [saving, setSaving]   = useState(false);

    const [name, setName]             = useState('');
    const [type, setType]             = useState('fuel');
    const [location, setLocation]     = useState('');
    const [lat, setLat]               = useState('');
    const [lng, setLng]               = useState('');
    const [description, setDescription] = useState('');
    const [services, setServices]     = useState('');
    const [fuelPrice, setFuelPrice]   = useState('');
    const [hasTruckParking, setHasTruckParking] = useState(false);
    const [brand, setBrand]           = useState('');
    const [highway, setHighway]       = useState('');
    const [kmMarker, setKmMarker]     = useState('');
    const [mapOpen, setMapOpen]       = useState(false);
    const [mapDraft, setMapDraft]     = useState<LocationPoint>({ lat: 53.9023, lng: 27.5619 });

    // Geocode address → coordinates
    const [addrQuery, setAddrQuery]   = useState('');
    const [addrSugg, setAddrSugg]     = useState<Array<{ label: string; lat: number; lng: number }>>([]);
    const [geoTimer, setGeoTimer]     = useState<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (!id) return;
        getProviderPois().then(list => {
            const poi = list.find(p => p.id === id);
            if (!poi) { Alert.alert('Ошибка', 'Объект не найден'); nav.goBack(); return; }
            setName(poi.name);
            setType(poi.type);
            setLocation(poi.location);
            setAddrQuery(poi.location);
            setLat(String(poi.lat));
            setLng(String(poi.lng));
            setDescription(poi.description ?? '');
            setServices(poi.services ?? '');
            setFuelPrice(poi.fuel_price ? String(poi.fuel_price) : '');
            setHasTruckParking(poi.has_truck_parking);
            setBrand(poi.brand ?? '');
            setHighway(poi.highway ?? '');
            setKmMarker(poi.km_marker ? String(poi.km_marker) : '');
        }).catch(() => nav.goBack()).finally(() => setLoading(false));
    }, [id]);

    function geocodeAddr(text: string) {
        if (geoTimer) clearTimeout(geoTimer);
        setAddrQuery(text);
        setLocation(text);
        if (text.length < 3) { setAddrSugg([]); return; }
        const t = setTimeout(async () => {
            try {
                const { data } = await client.get('/geo/geocode', { params: { q: text, limit: 5 } });
                setAddrSugg(data.results ?? []);
            } catch { setAddrSugg([]); }
        }, 500);
        setGeoTimer(t);
    }

    function currentPoint(): LocationPoint {
        const nextLat = parseFloat(lat);
        const nextLng = parseFloat(lng);
        if (Number.isFinite(nextLat) && Number.isFinite(nextLng)) {
            return { lat: nextLat, lng: nextLng };
        }
        return { lat: 53.9023, lng: 27.5619 };
    }

    function openMapPicker() {
        setMapDraft(currentPoint());
        setMapOpen(true);
    }

    async function confirmMapPoint() {
        setLat(String(mapDraft.lat));
        setLng(String(mapDraft.lng));
        try {
            const { data } = await client.get('/geo/reverse', {
                params: { lat: mapDraft.lat, lng: mapDraft.lng },
            });
            const label = data.label ?? `${mapDraft.lat.toFixed(5)}, ${mapDraft.lng.toFixed(5)}`;
            setLocation(label);
            setAddrQuery(label);
        } catch {
            const label = `${mapDraft.lat.toFixed(5)}, ${mapDraft.lng.toFixed(5)}`;
            setLocation(label);
            setAddrQuery(label);
        }
        setAddrSugg([]);
        setMapOpen(false);
    }

    async function save() {
        if (!name.trim()) { Alert.alert('Ошибка', 'Укажите название'); return; }
        if (!location.trim()) { Alert.alert('Ошибка', 'Укажите адрес или место объекта'); return; }
        const latitude = validateNumber(lat, 'Широта', { required: true, min: -90, max: 90 });
        if (!latitude.ok) { Alert.alert('Проверьте данные', latitude.message); return; }
        const longitude = validateNumber(lng, 'Долгота', { required: true, min: -180, max: 180 });
        if (!longitude.ok) { Alert.alert('Проверьте данные', longitude.message); return; }
        const price = validateNumber(fuelPrice, 'Цена топлива', { min: 0, max: 999 });
        if (!price.ok) { Alert.alert('Проверьте данные', price.message); return; }
        const marker = validateNumber(kmMarker, 'Километровая отметка', { integer: true, min: 0, max: 99999 });
        if (!marker.ok) { Alert.alert('Проверьте данные', marker.message); return; }

        const payload: PoiPayload = {
            name: name.trim(),
            type,
            lat: latitude.value!,
            lng: longitude.value!,
            location: location.trim(),
            description: description.trim() || undefined,
            services: services.trim() || undefined,
            fuel_price: price.value,
            has_truck_parking: hasTruckParking,
            brand: brand.trim() || undefined,
            highway: highway.trim() || undefined,
            km_marker: marker.value,
        };

        setSaving(true);
        try {
            if (id) { await updatePoi(id, payload); }
            else    { await createPoi(payload); }
            nav.goBack();
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось сохранить объект'));
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <>
        <ScrollView style={s.root} contentContainerStyle={{ padding: spacing.md, paddingBottom: 60, gap: spacing.md }}>
            <Field label="Название *">
                <TextInput style={s.input} value={name} onChangeText={setName} placeholder="АЗС Газпром нефть №18" placeholderTextColor={colors.text3} />
            </Field>

            <Field label="Тип объекта">
                <View style={s.chips}>
                    {POI_TYPES.map(t => (
                        <TouchableOpacity key={t} style={[s.chip, type === t && s.chipActive]} onPress={() => setType(t)}>
                            <Text style={[s.chipText, type === t && s.chipTextActive]}>{POI_TYPE_LABELS[t]}</Text>
                        </TouchableOpacity>
                    ))}
                </View>
            </Field>

            <Field label="Адрес *">
                <TextInput
                    style={s.input}
                    value={addrQuery}
                    onChangeText={geocodeAddr}
                    placeholder="Начните вводить адрес..."
                    placeholderTextColor={colors.text3}
                />
                {addrSugg.map((sg, i) => (
                    <TouchableOpacity key={i} style={s.sugg} onPress={() => {
                        setLocation(sg.label);
                        setAddrQuery(sg.label);
                        setLat(String(sg.lat));
                        setLng(String(sg.lng));
                        setAddrSugg([]);
                    }}>
                        <Text style={s.suggText}>{sg.label}</Text>
                    </TouchableOpacity>
                ))}
                {lat && lng && <Text style={[s.hint, { color: colors.green }]}>✓ Координаты: {parseFloat(lat).toFixed(5)}, {parseFloat(lng).toFixed(5)}</Text>}
            </Field>

                <TouchableOpacity style={s.mapPickBtn} onPress={openMapPicker}>
                    <Text style={s.mapPickBtnText}>Поставить метку на карте</Text>
                </TouchableOpacity>
                {lat && lng && Number.isFinite(parseFloat(lat)) && Number.isFinite(parseFloat(lng)) && (
                    <TouchableOpacity style={s.mapPreview} activeOpacity={0.9} onPress={openMapPicker}>
                        <MapView
                            style={s.map}
                            provider={PROVIDER_DEFAULT}
                            initialRegion={{
                                latitude: parseFloat(lat),
                                longitude: parseFloat(lng),
                                latitudeDelta: 0.06,
                                longitudeDelta: 0.06,
                            }}
                            scrollEnabled={false}
                            zoomEnabled={false}
                            rotateEnabled={false}
                            pitchEnabled={false}
                        >
                            <Marker
                                coordinate={{ latitude: parseFloat(lat), longitude: parseFloat(lng) }}
                                title={name || 'Объект'}
                                description={location}
                                pinColor={colors.accent}
                            />
                        </MapView>
                    </TouchableOpacity>
                )}
            <View style={s.row}>
                <Field label="Широта" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={lat} onChangeText={setLat} keyboardType="decimal-pad" placeholder="53.9023" placeholderTextColor={colors.text3} />
                </Field>
                <Field label="Долгота" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={lng} onChangeText={setLng} keyboardType="decimal-pad" placeholder="27.5619" placeholderTextColor={colors.text3} />
                </Field>
            </View>

            {type === 'fuel' && (
                <>
                    <View style={s.row}>
                        <Field label="Сеть / бренд" style={{ flex: 1 }}>
                            <TextInput style={s.input} value={brand} onChangeText={setBrand} placeholder="Лукойл, Газпром..." placeholderTextColor={colors.text3} />
                        </Field>
                        <Field label="Цена топлива ₽/л" style={{ flex: 1 }}>
                            <TextInput style={s.input} value={fuelPrice} onChangeText={setFuelPrice} keyboardType="decimal-pad" placeholder="62.5" placeholderTextColor={colors.text3} />
                        </Field>
                    </View>
                </>
            )}

            <View style={s.row}>
                <Field label="Трасса (А-1, М-4...)" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={highway} onChangeText={setHighway} placeholder="М-4" placeholderTextColor={colors.text3} />
                </Field>
                <Field label="Км-маркер" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={kmMarker} onChangeText={setKmMarker} keyboardType="decimal-pad" placeholder="320" placeholderTextColor={colors.text3} />
                </Field>
            </View>

            <View style={s.switchRow}>
                <Text style={s.switchLabel}>Стоянка для грузовиков</Text>
                <Switch
                    value={hasTruckParking}
                    onValueChange={setHasTruckParking}
                    trackColor={{ false: colors.border, true: colors.accent }}
                    thumbColor="#fff"
                />
            </View>

            <Field label="Услуги (через запятую)">
                <TextInput style={s.input} value={services} onChangeText={setServices} placeholder="Душ, кафе, шиномонтаж..." placeholderTextColor={colors.text3} />
            </Field>

            <Field label="Описание">
                <TextInput
                    style={[s.input, { height: 80, textAlignVertical: 'top' }]}
                    value={description}
                    onChangeText={setDescription}
                    placeholder="Дополнительная информация..."
                    placeholderTextColor={colors.text3}
                    multiline
                />
            </Field>

            <TouchableOpacity style={s.saveBtn} onPress={save} disabled={saving}>
                {saving
                    ? <ActivityIndicator color="#fff" />
                    : <Text style={s.saveBtnText}>{id ? 'Сохранить изменения' : 'Добавить объект'}</Text>
                }
            </TouchableOpacity>
        </ScrollView>
        <LocationMapModal
            visible={mapOpen}
            title="Местоположение объекта"
            subtitle={location || 'Поставьте точную метку'}
            point={mapDraft}
            editable
            markerTitle={name || 'Объект'}
            markerDescription={location}
            markerColor={colors.accent}
            onChange={setMapDraft}
            onClose={() => setMapOpen(false)}
            onConfirm={confirmMapPoint}
        />
        </>
    );
}

function Field({ label, children, style }: { label: string; children: React.ReactNode; style?: object }) {
    return (
        <View style={[{ gap: 6 }, style]}>
            <Text style={f.label}>{label}</Text>
            {children}
        </View>
    );
}

const f = StyleSheet.create({
    label: { fontSize: 12, color: colors.text3, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
});

const s = StyleSheet.create({
    root:   { flex: 1, backgroundColor: colors.bg },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg },
    row:    { flexDirection: 'row', gap: spacing.md },
    input:  { backgroundColor: colors.s1, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 14, color: colors.text },
    hint:   { fontSize: 11, marginTop: 4 },
    chips:  { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
    chip:   { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radius.full, backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border },
    chipActive:     { backgroundColor: colors.accentBg, borderColor: colors.accent },
    chipText:       { fontSize: 13, color: colors.text2 },
    chipTextActive: { color: colors.accent, fontWeight: '600' },
    sugg:     { backgroundColor: colors.s2, borderRadius: radius.sm, paddingHorizontal: spacing.md, paddingVertical: 10, borderWidth: 1, borderColor: colors.border },
    suggText: { fontSize: 13, color: colors.text },
    mapPickBtn: { borderRadius: radius.md, paddingVertical: 11, alignItems: 'center', borderWidth: 1, borderColor: colors.accent, backgroundColor: colors.accentBg },
    mapPickBtnText: { color: colors.accent, fontWeight: '700', fontSize: 13 },
    mapPreview: { height: 170, borderRadius: radius.md, overflow: 'hidden', borderWidth: 1, borderColor: colors.border, backgroundColor: colors.s2, ...shadow.sm },
    map: { flex: 1 },
    switchRow:  { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border },
    switchLabel:{ fontSize: 14, color: colors.text, fontWeight: '500' },
    saveBtn:     { backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', marginTop: spacing.sm, ...shadow.sm },
    saveBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
