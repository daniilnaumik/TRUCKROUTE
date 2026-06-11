import React, { useEffect, useState } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TextInput,
    TouchableOpacity, ActivityIndicator, Alert,
} from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getVehicle, createVehicle, updateVehicle, VehiclePayload } from '@/api/vehicles';
import { colors, spacing, radius, shadow } from '@/theme';
import { apiErrorMessage, validateNumber } from '@/utils/errors';

type Nav   = NativeStackNavigationProp<RootStackParamList>;
type Route = RouteProp<RootStackParamList, 'VehicleForm'>;

const VEHICLE_TYPES = ['тягач', 'одиночка', 'фургон', 'реф', 'цистерна'];
const FUEL_TYPES    = ['дизель', 'газ', 'электро'];

export default function VehicleFormScreen() {
    const nav   = useNavigation<Nav>();
    const route = useRoute<Route>();
    const id    = route.params?.id;

    const [loading, setLoading]   = useState(!!id);
    const [saving, setSaving]     = useState(false);

    const [title, setTitle]       = useState('');
    const [type, setType]         = useState('тягач');
    const [model, setModel]       = useState('');
    const [fuelType, setFuelType] = useState('дизель');
    const [tankCap, setTankCap]   = useState('');
    const [consumption, setConsumption] = useState('');
    const [speed, setSpeed]       = useState('85');
    const [weight, setWeight]     = useState('');
    const [restrictions, setRestrictions] = useState('');

    useEffect(() => {
        if (!id) return;
        getVehicle(id).then(v => {
            setTitle(v.title);
            setType(v.type);
            setModel(v.model ?? '');
            setFuelType(v.fuel_type);
            setTankCap(String(v.tank_capacity_l));
            setConsumption(String(v.consumption_l_per_100));
            setSpeed(String(v.cruise_speed_kmh));
            setWeight(v.curb_weight_t ? String(v.curb_weight_t) : '');
            setRestrictions(v.restrictions ?? '');
        }).catch(() => {
            Alert.alert('Ошибка', 'Не удалось загрузить данные транспорта');
            nav.goBack();
        }).finally(() => setLoading(false));
    }, [id]);

    async function save() {
        if (!title.trim()) {
            Alert.alert('Ошибка', 'Укажите название транспорта');
            return;
        }
        const tank = validateNumber(tankCap, 'Объём бака', { required: true, min: 1, max: 2000 });
        if (!tank.ok) { Alert.alert('Проверьте данные', tank.message); return; }
        const fuelConsumption = validateNumber(consumption, 'Расход топлива', { required: true, min: 1, max: 100 });
        if (!fuelConsumption.ok) { Alert.alert('Проверьте данные', fuelConsumption.message); return; }
        const cruiseSpeed = validateNumber(speed, 'Крейсерская скорость', { min: 30, max: 120 });
        if (!cruiseSpeed.ok) { Alert.alert('Проверьте данные', cruiseSpeed.message); return; }
        const curbWeight = validateNumber(weight, 'Масса транспорта', { min: 1, max: 40 });
        if (!curbWeight.ok) { Alert.alert('Проверьте данные', curbWeight.message); return; }

        const payload: VehiclePayload = {
            title: title.trim(),
            type,
            model:                model.trim() || undefined,
            fuel_type:            fuelType,
            tank_capacity_l:      tank.value!,
            consumption_l_per_100: fuelConsumption.value!,
            cruise_speed_kmh:     cruiseSpeed.value ?? 85,
            curb_weight_t:        curbWeight.value,
            restrictions:         restrictions.trim() || undefined,
        };

        setSaving(true);
        try {
            if (id) {
                await updateVehicle(id, payload);
            } else {
                await createVehicle(payload);
            }
            nav.goBack();
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось сохранить транспорт'));
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return <View style={s.center}><ActivityIndicator color={colors.accent} /></View>;
    }

    return (
        <ScrollView style={s.root} contentContainerStyle={{ padding: spacing.md, paddingBottom: 60, gap: spacing.md }}>
            <Field label="Название *" placeholder="Например: Volvo FH 2020">
                <TextInput style={s.input} value={title} onChangeText={setTitle} placeholder="Например: Volvo FH 2020" placeholderTextColor={colors.text3} />
            </Field>

            <Field label="Тип ТС">
                <View style={s.chips}>
                    {VEHICLE_TYPES.map(t => (
                        <TouchableOpacity
                            key={t}
                            style={[s.chip, type === t && s.chipActive]}
                            onPress={() => setType(t)}
                        >
                            <Text style={[s.chipText, type === t && s.chipTextActive]}>{t}</Text>
                        </TouchableOpacity>
                    ))}
                </View>
            </Field>

            <Field label="Марка / модель" placeholder="Volvo FH, КАМАЗ 5490...">
                <TextInput style={s.input} value={model} onChangeText={setModel} placeholder="Volvo FH, КАМАЗ 5490..." placeholderTextColor={colors.text3} />
            </Field>

            <Field label="Тип топлива">
                <View style={s.chips}>
                    {FUEL_TYPES.map(t => (
                        <TouchableOpacity
                            key={t}
                            style={[s.chip, fuelType === t && s.chipActive]}
                            onPress={() => setFuelType(t)}
                        >
                            <Text style={[s.chipText, fuelType === t && s.chipTextActive]}>{t}</Text>
                        </TouchableOpacity>
                    ))}
                </View>
            </Field>

            <View style={s.row}>
                <Field label="Объём бака (л) *" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={tankCap} onChangeText={setTankCap} keyboardType="decimal-pad" placeholder="600" placeholderTextColor={colors.text3} />
                </Field>
                <Field label="Расход л/100км *" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={consumption} onChangeText={setConsumption} keyboardType="decimal-pad" placeholder="30" placeholderTextColor={colors.text3} />
                </Field>
            </View>

            <View style={s.row}>
                <Field label="Крейс. скорость км/ч" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={speed} onChangeText={setSpeed} keyboardType="decimal-pad" placeholder="85" placeholderTextColor={colors.text3} />
                </Field>
                <Field label="Снаряж. масса (т)" style={{ flex: 1 }}>
                    <TextInput style={s.input} value={weight} onChangeText={setWeight} keyboardType="decimal-pad" placeholder="20" placeholderTextColor={colors.text3} />
                </Field>
            </View>

            <Field label="Ограничения (опционально)" placeholder="Высота, масса, опасный груз...">
                <TextInput
                    style={[s.input, { height: 72, textAlignVertical: 'top' }]}
                    value={restrictions}
                    onChangeText={setRestrictions}
                    placeholder="Высота 4м, масса 40т..."
                    placeholderTextColor={colors.text3}
                    multiline
                />
            </Field>

            <TouchableOpacity style={s.saveBtn} onPress={save} disabled={saving}>
                {saving
                    ? <ActivityIndicator color="#fff" />
                    : <Text style={s.saveBtnText}>{id ? 'Сохранить изменения' : 'Добавить транспорт'}</Text>
                }
            </TouchableOpacity>
        </ScrollView>
    );
}

function Field({ label, children, placeholder, style }: {
    label: string; children: React.ReactNode; placeholder?: string; style?: object;
}) {
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
    chips:  { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
    chip:   { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radius.full, backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border },
    chipActive: { backgroundColor: colors.accentBg, borderColor: colors.accent },
    chipText:   { fontSize: 13, color: colors.text2 },
    chipTextActive: { color: colors.accent, fontWeight: '600' },
    saveBtn:     { backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', marginTop: spacing.sm, ...shadow.sm },
    saveBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
