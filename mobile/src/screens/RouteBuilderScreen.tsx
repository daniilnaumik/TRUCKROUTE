import React, { useCallback, useEffect, useState } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TextInput,
    TouchableOpacity, ActivityIndicator, Alert, KeyboardAvoidingView, Platform,
    Modal, Keyboard, TouchableWithoutFeedback,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { getVehicles, Vehicle } from '@/api/vehicles';
import { createCargo, Cargo } from '@/api/cargos';
import { buildRoute } from '@/api/routes';
import client from '@/api/client';
import LocationMapModal, { LocationPoint } from '@/components/LocationMapModal';
import { colors, spacing, radius, shadow } from '@/theme';
import { apiErrorMessage, validateNumber } from '@/utils/errors';

type Nav = NativeStackNavigationProp<RootStackParamList>;

type Step = 'vehicle' | 'route' | 'prefs' | 'result';

interface GeoSuggestion { label: string; lat: number; lng: number }

type RoutePointKind = 'origin' | 'destination' | 'via';
type RoutePointPicker = { kind: RoutePointKind; index?: number };

const MINSK_POINT: LocationPoint = { lat: 53.9023, lng: 27.5619 };
const FUEL_BRANDS = ['Любые', 'Белоруснефть', 'Лукойл', 'Газпром', 'Shell', 'Rosneft'];

export default function RouteBuilderScreen() {
    const nav = useNavigation<Nav>();

    const [step, setStep]           = useState<Step>('vehicle');
    const [building, setBuilding]   = useState(false);

    // Step 1: vehicle & cargo
    const [vehicles, setVehicles]   = useState<Vehicle[]>([]);
    const [cargos, setCargos]       = useState<Cargo[]>([]);
    const [vehicleId, setVehicleId] = useState<number | null>(null);
    const [fuelNow, setFuelNow]     = useState('');
    const [cargoId, setCargoId]     = useState<number | null>(null);
    const [showCargoModal, setShowCargoModal] = useState(false);
    const [savingCargo, setSavingCargo] = useState(false);
    const [cargoTitle, setCargoTitle] = useState('');
    const [cargoFlag, setCargoFlag] = useState('Обычный');
    const [cargoWeight, setCargoWeight] = useState('');
    const [cargoRequirements, setCargoRequirements] = useState('');

    // Step 2: route
    const [originText, setOriginText]   = useState('');
    const [destText, setDestText]       = useState('');
    const [originPoint, setOriginPoint] = useState<{ lat: number; lng: number } | null>(null);
    const [destPoint, setDestPoint]     = useState<{ lat: number; lng: number } | null>(null);
    const [viaPoints, setViaPoints]     = useState<Array<{ text: string; lat?: number; lng?: number }>>([]);
    const [startDate, setStartDate]     = useState('');
    const [startTime, setStartTime]     = useState('08:00');
    const [routePicker, setRoutePicker] = useState<RoutePointPicker | null>(null);
    const [routeDraft, setRouteDraft]   = useState<LocationPoint>(MINSK_POINT);

    // Step 3: prefs
    const [reservePct, setReservePct]   = useState('15');
    const [planMode, setPlanMode]       = useState<'Безопасный' | 'Экономный'>('Безопасный');
    const [restHours, setRestHours]     = useState('4');
    const [overnightType, setOvernightType] = useState('мотель');
    const [preferredFuelBrand, setPreferredFuelBrand] = useState('Любые');

    // Geocoding
    const [originSugg, setOriginSugg] = useState<GeoSuggestion[]>([]);
    const [destSugg, setDestSugg]     = useState<GeoSuggestion[]>([]);
    const [geoTimer, setGeoTimer]     = useState<ReturnType<typeof setTimeout> | null>(null);

    // Result
    const [result, setResult] = useState<any>(null);

    const loadOptions = useCallback(async () => {
        const nextVehicles = await getVehicles().catch(() => []);
        setVehicles(nextVehicles);
    }, []);

    useFocusEffect(useCallback(() => {
        loadOptions();
    }, [loadOptions]));

    useEffect(() => {
        // Default date = today
        const today = new Date();
        setStartDate(today.toISOString().slice(0, 10));
    }, []);

    // Auto-select active vehicle
    useEffect(() => {
        if (vehicles.length === 0) {
            setVehicleId(null);
            setFuelNow('');
            return;
        }

        const selected = vehicleId ? vehicles.find(v => v.id === vehicleId) : null;
        if (selected) {
            if (!fuelNow) setFuelNow(String(selected.tank_capacity_l));
            return;
        }

        const nextVehicle = vehicles.find(v => v.is_active) ?? vehicles[0];
        setVehicleId(nextVehicle.id);
        setFuelNow(String(nextVehicle.tank_capacity_l));
    }, [vehicles, vehicleId, fuelNow]);

    async function handleCreateCargo() {
        if (!cargoTitle.trim()) {
            Alert.alert('Ошибка', 'Укажите название груза');
            return;
        }

        const weight = validateNumber(cargoWeight, 'Вес груза', { min: 0, max: 45 });
        if (!weight.ok) { Alert.alert('Проверьте данные', weight.message); return; }

        setSavingCargo(true);
        try {
            const cargo = await createCargo({
                title: cargoTitle.trim(),
                flag: cargoFlag,
                weight_t: weight.value,
                requirements: cargoRequirements.trim() || undefined,
            });
            setCargos(prev => [cargo, ...prev.filter(item => item.id !== cargo.id)]);
            setCargoId(cargo.id);
            setShowCargoModal(false);
            setCargoTitle('');
            setCargoFlag('Обычный');
            setCargoWeight('');
            setCargoRequirements('');
        } catch (error: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(error, 'Не удалось добавить груз'));
        } finally {
            setSavingCargo(false);
        }
    }

    function geocode(text: string, setSugg: (s: GeoSuggestion[]) => void) {
        if (geoTimer) clearTimeout(geoTimer);
        if (text.length < 3) { setSugg([]); return; }
        const t = setTimeout(async () => {
            try {
                const { data } = await client.get('/geo/geocode', { params: { q: text, limit: 5 } });
                setSugg(data.results ?? []);
            } catch { setSugg([]); }
        }, 500);
        setGeoTimer(t);
    }

    function addViaPoint() {
        setViaPoints(prev => [...prev, { text: '' }]);
    }

    function removeViaPoint(idx: number) {
        setViaPoints(prev => prev.filter((_, i) => i !== idx));
    }

    function updateViaText(idx: number, text: string) {
        setViaPoints(prev => prev.map((p, i) => i === idx ? { text } : p));
        geocodeVia(idx, text);
    }

    const [viaSugg, setViaSugg]   = useState<Record<number, GeoSuggestion[]>>({});
    const [viaTimer, setViaTimer] = useState<ReturnType<typeof setTimeout> | null>(null);

    function geocodeVia(idx: number, text: string) {
        if (viaTimer) clearTimeout(viaTimer);
        if (text.length < 3) { setViaSugg(prev => ({ ...prev, [idx]: [] })); return; }
        const t = setTimeout(async () => {
            try {
                const { data } = await client.get('/geo/geocode', { params: { q: text, limit: 4 } });
                setViaSugg(prev => ({ ...prev, [idx]: data.results ?? [] }));
            } catch {}
        }, 500);
        setViaTimer(t);
    }

    function selectVia(idx: number, sugg: GeoSuggestion) {
        setViaPoints(prev => prev.map((p, i) => i === idx ? { text: sugg.label, lat: sugg.lat, lng: sugg.lng } : p));
        setViaSugg(prev => ({ ...prev, [idx]: [] }));
    }

    function pointForPicker(kind: RoutePointKind, index?: number): LocationPoint {
        if (kind === 'origin' && originPoint) return originPoint;
        if (kind === 'destination' && destPoint) return destPoint;
        if (kind === 'via' && index !== undefined) {
            const point = viaPoints[index];
            if (point?.lat !== undefined && point?.lng !== undefined) {
                return { lat: point.lat, lng: point.lng };
            }
        }
        return originPoint ?? destPoint ?? MINSK_POINT;
    }

    function openRoutePointPicker(kind: RoutePointKind, index?: number) {
        setRouteDraft(pointForPicker(kind, index));
        setRoutePicker({ kind, index });
    }

    async function reverseRoutePoint(point: LocationPoint) {
        try {
            const { data } = await client.get('/geo/reverse', {
                params: { lat: point.lat, lng: point.lng },
            });
            return data.label ?? `${point.lat.toFixed(5)}, ${point.lng.toFixed(5)}`;
        } catch {
            return `${point.lat.toFixed(5)}, ${point.lng.toFixed(5)}`;
        }
    }

    async function confirmRoutePoint() {
        if (!routePicker) return;

        const label = await reverseRoutePoint(routeDraft);
        if (routePicker.kind === 'origin') {
            setOriginText(label);
            setOriginPoint(routeDraft);
            setOriginSugg([]);
        } else if (routePicker.kind === 'destination') {
            setDestText(label);
            setDestPoint(routeDraft);
            setDestSugg([]);
        } else if (routePicker.index !== undefined) {
            setViaPoints(prev => prev.map((p, i) => (
                i === routePicker.index
                    ? { text: label, lat: routeDraft.lat, lng: routeDraft.lng }
                    : p
            )));
            setViaSugg(prev => ({ ...prev, [routePicker.index!]: [] }));
        }
        setRoutePicker(null);
    }

    function handleStartTimeChange(value: string) {
        setStartTime(formatTimeInput(value));
    }

    function handleStartTimeBlur() {
        setStartTime(normalizeTimeInput(startTime));
    }

    async function doBuildRoute() {
        if (!vehicleId) { Alert.alert('Ошибка', 'Выберите транспорт'); return; }
        if (!originPoint || !destPoint) { Alert.alert('Ошибка', 'Укажите точки маршрута'); return; }

        const selectedVehicle = vehicles.find(v => v.id === vehicleId);
        const selectedCargo   = cargos.find(c => c.id === cargoId);
        const startFuel = validateNumber(fuelNow, 'Топливо при старте', { required: true, min: 0, max: 2000 });
        if (!startFuel.ok) { Alert.alert('Проверьте данные', startFuel.message); return; }
        if (selectedVehicle && startFuel.value! > selectedVehicle.tank_capacity_l) {
            Alert.alert('Проверьте данные', `Топливо при старте не может превышать объём бака ${selectedVehicle.tank_capacity_l} л.`);
            return;
        }
        const reserve = validateNumber(reservePct, 'Резерв топлива', { required: true, integer: true, min: 0, max: 80 });
        if (!reserve.ok) { Alert.alert('Проверьте данные', reserve.message); return; }
        const continuousDrive = validateNumber(restHours, 'Время непрерывного движения', { required: true, min: 1, max: 12 });
        if (!continuousDrive.ok) { Alert.alert('Проверьте данные', continuousDrive.message); return; }

        const viaResolved = viaPoints.filter(p => p.lat && p.lng).map(p => ({
            lat: p.lat!, lng: p.lng!, label: p.text,
        }));

        const normalizedStartTime = normalizeTimeInput(startTime);
        if (startDate && !isValidTime(normalizedStartTime)) {
            Alert.alert('Ошибка', 'Укажите время старта в формате ЧЧ:ММ');
            return;
        }

        const startAt = startDate && normalizedStartTime ? `${startDate}T${normalizedStartTime}:00` : undefined;

        const payload = {
            vehicle_id:   vehicleId,
            start_fuel_l: startFuel.value!,
            origin: {
                ...originPoint,
                label: originText,
            },
            destination: {
                ...destPoint,
                label: destText,
            },
            via: viaResolved,
            start_time: startAt,
            preferences: {
                planning_mode:          planMode,
                reserve_percent:        reserve.value!,
                continuous_drive_hours: continuousDrive.value!,
                lodging_type:           overnightType,
                include_rest_stop:      true,
                preferred_fuel_brand:   preferredFuelBrand,
            },
            ...(selectedCargo ? {
                cargo: {
                    weight_t: selectedCargo.weight_t ?? 0,
                    flag: selectedCargo.flag,
                    requirements: selectedCargo.requirements ?? '',
                },
            } : {}),
        };

        setBuilding(true);
        try {
            const plan = await buildRoute(payload);
            setResult(plan);
            setStep('result');
        } catch (e: any) {
            Alert.alert('Проверьте данные', apiErrorMessage(e, 'Не удалось построить маршрут'));
        } finally {
            setBuilding(false);
        }
    }

    function driveTime(minutes: number) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return h ? `${h}ч ${m}м` : `${m}м`;
    }

    // ─── STEP RENDER ──────────────────────────────────────────────────────────

    return (
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <View style={s.root}>
                {/* Progress bar */}
                {step !== 'result' && (
                    <View style={s.progress}>
                        {(['vehicle', 'route', 'prefs'] as Step[]).map((st, i) => (
                            <View key={st} style={[s.progressDot, step === st && s.progressDotActive,
                                (['vehicle', 'route', 'prefs'] as Step[]).indexOf(step) > i && s.progressDotDone
                            ]} />
                        ))}
                    </View>
                )}

                <ScrollView contentContainerStyle={{ padding: spacing.md, paddingBottom: 100, gap: spacing.md }}>

                    {/* ── STEP 1: Транспорт и груз ── */}
                    {step === 'vehicle' && (
                        <>
                            <Text style={s.stepTitle}>Транспорт и груз</Text>

                            <Section title="Выберите транспорт">
                                {vehicles.length === 0
                                    ? <TouchableOpacity style={s.addVehicleBtn} onPress={() => nav.navigate('VehicleForm', {})}>
                                        <Text style={s.addVehicleBtnText}>+ Добавить транспорт</Text>
                                    </TouchableOpacity>
                                    : vehicles.map(v => (
                                        <TouchableOpacity
                                            key={v.id}
                                            style={[s.vehicleCard, vehicleId === v.id && s.vehicleCardActive]}
                                            onPress={() => {
                                                setVehicleId(v.id);
                                                setFuelNow(String(v.tank_capacity_l));
                                            }}
                                        >
                                            <View style={{ flex: 1 }}>
                                                <Text style={s.vehicleCardTitle}>{v.title}</Text>
                                                <Text style={s.vehicleCardMeta}>{v.type} · {v.tank_capacity_l}л · {v.consumption_l_per_100}л/100</Text>
                                            </View>
                                            {vehicleId === v.id && <Text style={s.checkmark}>✓</Text>}
                                        </TouchableOpacity>
                                    ))
                                }
                            </Section>

                            {vehicleId && (
                                <Section title="Топливо сейчас (л)">
                                    <TextInput
                                        style={s.input}
                                        value={fuelNow}
                                        onChangeText={setFuelNow}
                                        keyboardType="decimal-pad"
                                        placeholder={`Макс: ${vehicles.find(v => v.id === vehicleId)?.tank_capacity_l ?? '—'} л`}
                                        placeholderTextColor={colors.text3}
                                    />
                                    <Text style={s.hint}>
                                        Запас хода ≈ {fuelNow && vehicleId
                                            ? Math.round((parseFloat(fuelNow) * 100) / (vehicles.find(v => v.id === vehicleId)?.consumption_l_per_100 ?? 30))
                                            : '—'} км
                                    </Text>
                                </Section>
                            )}

                            <Section title="Груз">
                                <TouchableOpacity
                                    style={[s.cargoChip, cargoId === null && s.cargoChipActive]}
                                    onPress={() => setCargoId(null)}
                                >
                                    <Text style={[s.cargoChipText, cargoId === null && s.cargoChipTextActive]}>Без груза</Text>
                                </TouchableOpacity>
                                {cargos.map(c => (
                                    <TouchableOpacity
                                        key={c.id}
                                        style={[s.cargoChip, cargoId === c.id && s.cargoChipActive]}
                                        onPress={() => setCargoId(c.id)}
                                    >
                                        <Text style={[s.cargoChipText, cargoId === c.id && s.cargoChipTextActive]}>
                                            {c.title}
                                            {c.flag !== 'Обычный' ? ` (${c.flag})` : ''}
                                            {c.weight_t ? ` · ${c.weight_t}т` : ''}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                                <TouchableOpacity style={s.addCargoBtn} onPress={() => setShowCargoModal(true)}>
                                    <Text style={s.addCargoBtnText}>+ Добавить груз</Text>
                                </TouchableOpacity>
                            </Section>
                        </>
                    )}

                    {/* ── STEP 2: Маршрут ── */}
                    {step === 'route' && (
                        <>
                            <Text style={s.stepTitle}>Маршрут</Text>

                            <Section title="Откуда *">
                                <TextInput
                                    style={s.input}
                                    value={originText}
                                    onChangeText={t => { setOriginText(t); setOriginPoint(null); geocode(t, setOriginSugg); }}
                                    placeholder="Город или адрес..."
                                    placeholderTextColor={colors.text3}
                                />
                                {originSugg.map((sg, i) => (
                                    <TouchableOpacity key={i} style={s.sugg} onPress={() => {
                                        setOriginText(sg.label);
                                        setOriginPoint({ lat: sg.lat, lng: sg.lng });
                                        setOriginSugg([]);
                                    }}>
                                        <Text style={s.suggText}>{sg.label}</Text>
                                    </TouchableOpacity>
                                ))}
                                <TouchableOpacity style={s.mapPickBtn} onPress={() => openRoutePointPicker('origin')}>
                                    <Text style={s.mapPickBtnText}>Поставить точку “Откуда” на карте</Text>
                                </TouchableOpacity>
                                {originPoint && <Text style={[s.hint, { color: colors.green }]}>✓ Точная точка выбрана</Text>}
                            </Section>

                            {viaPoints.map((p, idx) => (
                                <Section key={idx} title={`Транзит ${idx + 1}`}>
                                    <View style={{ flexDirection: 'row', gap: spacing.sm }}>
                                        <TextInput
                                            style={[s.input, { flex: 1 }]}
                                            value={p.text}
                                            onChangeText={t => updateViaText(idx, t)}
                                            placeholder="Транзитный город..."
                                            placeholderTextColor={colors.text3}
                                        />
                                        <TouchableOpacity style={s.removeBtn} onPress={() => removeViaPoint(idx)}>
                                            <Text style={{ color: colors.red, fontSize: 18 }}>✕</Text>
                                        </TouchableOpacity>
                                    </View>
                                    {(viaSugg[idx] ?? []).map((sg, i) => (
                                        <TouchableOpacity key={i} style={s.sugg} onPress={() => selectVia(idx, sg)}>
                                            <Text style={s.suggText}>{sg.label}</Text>
                                        </TouchableOpacity>
                                    ))}
                                    <TouchableOpacity style={s.mapPickBtn} onPress={() => openRoutePointPicker('via', idx)}>
                                        <Text style={s.mapPickBtnText}>Поставить транзит на карте</Text>
                                    </TouchableOpacity>
                                    {p.lat && <Text style={[s.hint, { color: colors.green }]}>✓ Точка выбрана</Text>}
                                </Section>
                            ))}

                            <TouchableOpacity style={s.addPointBtn} onPress={addViaPoint}>
                                <Text style={s.addPointBtnText}>+ Добавить транзитную точку</Text>
                            </TouchableOpacity>

                            <Section title="Куда *">
                                <TextInput
                                    style={s.input}
                                    value={destText}
                                    onChangeText={t => { setDestText(t); setDestPoint(null); geocode(t, setDestSugg); }}
                                    placeholder="Город или адрес..."
                                    placeholderTextColor={colors.text3}
                                />
                                {destSugg.map((sg, i) => (
                                    <TouchableOpacity key={i} style={s.sugg} onPress={() => {
                                        setDestText(sg.label);
                                        setDestPoint({ lat: sg.lat, lng: sg.lng });
                                        setDestSugg([]);
                                    }}>
                                        <Text style={s.suggText}>{sg.label}</Text>
                                    </TouchableOpacity>
                                ))}
                                <TouchableOpacity style={s.mapPickBtn} onPress={() => openRoutePointPicker('destination')}>
                                    <Text style={s.mapPickBtnText}>Поставить точку “Куда” на карте</Text>
                                </TouchableOpacity>
                                {destPoint && <Text style={[s.hint, { color: colors.green }]}>✓ Точная точка выбрана</Text>}
                            </Section>

                            <View style={s.row}>
                                <Section title="Дата старта" style={{ flex: 1 }}>
                                    <TextInput
                                        style={s.input}
                                        value={startDate}
                                        onChangeText={setStartDate}
                                        placeholder="ГГГГ-ММ-ДД"
                                        placeholderTextColor={colors.text3}
                                        keyboardType="numeric"
                                    />
                                </Section>
                                <Section title="Время" style={{ flex: 1 }}>
                                    <TextInput
                                        style={s.input}
                                        value={startTime}
                                        onChangeText={handleStartTimeChange}
                                        onBlur={handleStartTimeBlur}
                                        placeholder="ЧЧ:ММ"
                                        placeholderTextColor={colors.text3}
                                        keyboardType="number-pad"
                                        maxLength={5}
                                    />
                                </Section>
                            </View>
                        </>
                    )}

                    {/* ── STEP 3: Предпочтения ── */}
                    {step === 'prefs' && (
                        <>
                            <Text style={s.stepTitle}>Предпочтения</Text>

                            <Section title="Режим планирования заправок">
                                {(['Безопасный', 'Экономный'] as const).map(m => (
                                    <TouchableOpacity
                                        key={m}
                                        style={[s.modeCard, planMode === m && s.modeCardActive]}
                                        onPress={() => setPlanMode(m)}
                                    >
                                        <Text style={[s.modeCardTitle, planMode === m && s.modeCardTitleActive]}>{m}</Text>
                                        <Text style={s.modeCardDesc}>
                                            {m === 'Безопасный'
                                                ? 'Чаще заправляться, больше резерв топлива'
                                                : 'Реже, ближе к оптимуму — меньше остановок'}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                            </Section>

                            <Section title="Предпочтительная АЗС">
                                <View style={s.chips}>
                                    {FUEL_BRANDS.map(brand => (
                                        <TouchableOpacity
                                            key={brand}
                                            style={[s.chip, preferredFuelBrand === brand && s.chipActive]}
                                            onPress={() => setPreferredFuelBrand(brand)}
                                        >
                                            <Text style={[s.chipText, preferredFuelBrand === brand && s.chipTextActive]}>{brand}</Text>
                                        </TouchableOpacity>
                                    ))}
                                </View>
                                <Text style={s.hint}>Эта настройка влияет на заправки в расчете маршрута и на рекомендации по пути.</Text>
                            </Section>

                            <View style={s.row}>
                                <Section title="Резерв топлива %" style={{ flex: 1 }}>
                                    <TextInput
                                        style={s.input}
                                        value={reservePct}
                                        onChangeText={setReservePct}
                                        keyboardType="numeric"
                                        placeholder="15"
                                        placeholderTextColor={colors.text3}
                                    />
                                </Section>
                                <Section title="Отдых каждые (ч)" style={{ flex: 1 }}>
                                    <TextInput
                                        style={s.input}
                                        value={restHours}
                                        onChangeText={setRestHours}
                                        keyboardType="decimal-pad"
                                        placeholder="4"
                                        placeholderTextColor={colors.text3}
                                    />
                                </Section>
                            </View>

                            <Section title="Тип ночлега">
                                <View style={s.chips}>
                                    {['стоянка', 'мотель', 'отель'].map(t => (
                                        <TouchableOpacity
                                            key={t}
                                            style={[s.chip, overnightType === t && s.chipActive]}
                                            onPress={() => setOvernightType(t)}
                                        >
                                            <Text style={[s.chipText, overnightType === t && s.chipTextActive]}>{t}</Text>
                                        </TouchableOpacity>
                                    ))}
                                </View>
                            </Section>
                        </>
                    )}

                    {/* ── RESULT ── */}
                    {step === 'result' && result && (
                        <>
                            <Text style={s.stepTitle}>Маршрут построен</Text>

                            <View style={s.resultCard}>
                                <Text style={s.resultTitle}>{result.title ?? `${originText} → ${destText}`}</Text>
                                <View style={s.resultStats}>
                                    <ResStat label="Расстояние" value={`${result.distance_km} км`} />
                                    <ResStat label="В пути" value={driveTime(result.drive_time_minutes)} />
                                    <ResStat label="Топливо" value={`${result.fuel?.needed_l ?? '—'} л`} />
                                    <ResStat label="Остановок" value={String(result.stops_count ?? 0)} />
                                </View>
                            </View>

                            {(result.stops ?? []).length > 0 && (
                                <Section title={`Рекомендации (${result.stops.length})`}>
                                    {result.stops.map((stop: any, i: number) => (
                                        <View key={i} style={s.stopCard}>
                                            <View style={[s.stopTypeBadge, { backgroundColor: stopColor(stop.type) }]}>
                                                <Text style={s.stopTypeBadgeText}>{stopLabel(stop.type)}</Text>
                                            </View>
                                            <Text style={s.stopName}>{stop.poi?.name ?? '—'}</Text>
                                            <Text style={s.stopMeta}>
                                                {stop.distance_from_start_km} км от старта
                                                {stop.detour_km ? ` · +${stop.detour_km} км детур` : ''}
                                                {stop.eta_at ? ` · ${new Date(stop.eta_at).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' })}` : ''}
                                            </Text>
                                            {stop.suggested_fuel_l && (
                                                <Text style={s.stopFuel}>Заправить ≈ {stop.suggested_fuel_l} л</Text>
                                            )}
                                        </View>
                                    ))}
                                </Section>
                            )}

                            <TouchableOpacity
                                style={s.mapBtn}
                                onPress={() => nav.navigate('Map', { routeId: result.id, preferredFuelBrand })}
                            >
                                <Text style={s.mapBtnText}>▶ Открыть на карте</Text>
                            </TouchableOpacity>
                        </>
                    )}
                </ScrollView>

                {/* Bottom nav */}
                <View style={s.bottomBar}>
                    {step !== 'vehicle' && step !== 'result' && (
                        <TouchableOpacity style={s.backBtn} onPress={() => {
                            if (step === 'route') setStep('vehicle');
                            else if (step === 'prefs') setStep('route');
                        }}>
                            <Text style={s.backBtnText}>← Назад</Text>
                        </TouchableOpacity>
                    )}

                    {step === 'vehicle' && (
                        <TouchableOpacity
                            style={[s.nextBtn, !vehicleId && s.nextBtnDisabled]}
                            onPress={() => vehicleId ? setStep('route') : Alert.alert('Выберите транспорт')}
                        >
                            <Text style={s.nextBtnText}>Далее →</Text>
                        </TouchableOpacity>
                    )}
                    {step === 'route' && (
                        <TouchableOpacity
                            style={[s.nextBtn, (!originPoint || !destPoint) && s.nextBtnDisabled]}
                            onPress={() => (originPoint && destPoint) ? setStep('prefs') : Alert.alert('Укажите точки маршрута')}
                        >
                            <Text style={s.nextBtnText}>Далее →</Text>
                        </TouchableOpacity>
                    )}
                    {step === 'prefs' && (
                        <TouchableOpacity style={s.nextBtn} onPress={doBuildRoute} disabled={building}>
                            {building
                                ? <ActivityIndicator color="#fff" />
                                : <Text style={s.nextBtnText}>Построить маршрут</Text>
                            }
                        </TouchableOpacity>
                    )}
                    {step === 'result' && (
                        <TouchableOpacity style={s.backBtn} onPress={() => nav.goBack()}>
                            <Text style={s.backBtnText}>← К маршрутам</Text>
                        </TouchableOpacity>
                    )}
                </View>

                <Modal visible={showCargoModal} transparent animationType="slide" onRequestClose={() => setShowCargoModal(false)}>
                    <View style={s.modalOverlay}>
                        <KeyboardAvoidingView
                            style={s.modalKeyboard}
                            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
                        >
                            <TouchableWithoutFeedback onPress={Keyboard.dismiss} accessible={false}>
                                <View style={s.modalCard}>
                                    <Text style={s.modalTitle}>Новый груз</Text>

                                    <Text style={s.modalLabel}>Название *</Text>
                                    <TextInput
                                        style={s.input}
                                        value={cargoTitle}
                                        onChangeText={setCargoTitle}
                                        placeholder="Например: Паллеты с товаром"
                                        placeholderTextColor={colors.text3}
                                        returnKeyType="next"
                                    />

                                    <Text style={s.modalLabel}>Тип</Text>
                                    <View style={s.chips}>
                                        {['Обычный', 'Опасный', 'Негабарит', 'Рефриж'].map(flag => (
                                            <TouchableOpacity
                                                key={flag}
                                                style={[s.chip, cargoFlag === flag && s.chipActive]}
                                                onPress={() => setCargoFlag(flag)}
                                            >
                                                <Text style={[s.chipText, cargoFlag === flag && s.chipTextActive]}>{flag}</Text>
                                            </TouchableOpacity>
                                        ))}
                                    </View>

                                    <Text style={s.modalLabel}>Вес, т</Text>
                                    <TextInput
                                        style={s.input}
                                        value={cargoWeight}
                                        onChangeText={setCargoWeight}
                                        keyboardType="decimal-pad"
                                        placeholder="20"
                                        placeholderTextColor={colors.text3}
                                    />

                                    <Text style={s.modalLabel}>Требования</Text>
                                    <TextInput
                                        style={[s.input, { height: 72, textAlignVertical: 'top' }]}
                                        value={cargoRequirements}
                                        onChangeText={setCargoRequirements}
                                        placeholder="Температура, крепление, режим..."
                                        placeholderTextColor={colors.text3}
                                        multiline
                                    />

                                    <View style={s.modalActions}>
                                        <TouchableOpacity style={s.modalCancelBtn} onPress={() => setShowCargoModal(false)}>
                                            <Text style={s.modalCancelText}>Отмена</Text>
                                        </TouchableOpacity>
                                        <TouchableOpacity style={s.modalSaveBtn} onPress={handleCreateCargo} disabled={savingCargo}>
                                            {savingCargo
                                                ? <ActivityIndicator color="#fff" />
                                                : <Text style={s.modalSaveText}>Добавить</Text>
                                            }
                                        </TouchableOpacity>
                                    </View>
                                </View>
                            </TouchableWithoutFeedback>
                        </KeyboardAvoidingView>
                    </View>
                </Modal>

                {routePicker && (
                    <LocationMapModal
                        visible={!!routePicker}
                        title={routePicker.kind === 'origin' ? 'Откуда' : routePicker.kind === 'destination' ? 'Куда' : 'Транзит'}
                        subtitle="Поставьте точную метку"
                        point={routeDraft}
                        editable
                        markerTitle="Точка маршрута"
                        markerColor={routePicker.kind === 'destination' ? colors.red : routePicker.kind === 'origin' ? colors.green : colors.accent}
                        onChange={setRouteDraft}
                        onClose={() => setRoutePicker(null)}
                        onConfirm={confirmRoutePoint}
                    />
                )}
            </View>
        </KeyboardAvoidingView>
    );
}

function Section({ title, children, style }: { title: string; children: React.ReactNode; style?: object }) {
    return (
        <View style={[{ gap: 8 }, style]}>
            <Text style={sec.title}>{title}</Text>
            {children}
        </View>
    );
}

function ResStat({ label, value }: { label: string; value: string }) {
    return (
        <View style={{ alignItems: 'center', flex: 1 }}>
            <Text style={{ fontSize: 16, fontWeight: '700', color: colors.accent, fontFamily: 'monospace' }}>{value}</Text>
            <Text style={{ fontSize: 10, color: colors.text3, marginTop: 2 }}>{label}</Text>
        </View>
    );
}

function stopLabel(type: string): string {
    const m: Record<string, string> = { fuel: 'АЗС', rest: 'Отдых', overnight: 'Ночлег', food: 'Еда' };
    return m[type] ?? type;
}

function stopColor(type: string): string {
    const m: Record<string, string> = {
        fuel: colors.accent, rest: colors.green, overnight: '#5b5bd6', food: '#c07a2a',
    };
    return m[type] ?? colors.text3;
}

function formatTimeInput(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 4);
    if (digits.length <= 2) return digits;
    return `${digits.slice(0, 2)}:${digits.slice(2)}`;
}

function normalizeTimeInput(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 4);
    if (!digits) return '';

    const hourDigits = digits.length <= 2 ? digits : digits.slice(0, 2);
    const minuteDigits = digits.length > 2 ? digits.slice(2) : '00';
    const hours = Math.min(23, Math.max(0, parseInt(hourDigits, 10) || 0));
    const minutes = Math.min(59, Math.max(0, parseInt(minuteDigits.padEnd(2, '0'), 10) || 0));

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

function isValidTime(value: string): boolean {
    if (!/^\d{2}:\d{2}$/.test(value)) return false;
    const [hours, minutes] = value.split(':').map(Number);
    return hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59;
}

const sec = StyleSheet.create({
    title: { fontSize: 12, color: colors.text3, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
});

const s = StyleSheet.create({
    root:   { flex: 1, backgroundColor: colors.bg },
    progress: { flexDirection: 'row', justifyContent: 'center', gap: 8, paddingTop: spacing.md },
    progressDot:       { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.border },
    progressDotActive: { backgroundColor: colors.accent, width: 24 },
    progressDotDone:   { backgroundColor: colors.accentDark },
    stepTitle:  { fontSize: 22, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },

    input:  { backgroundColor: colors.s1, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 14, color: colors.text },
    hint:   { fontSize: 11, color: colors.text3, marginTop: 4 },
    row:    { flexDirection: 'row', gap: spacing.md },

    sugg:     { backgroundColor: colors.s2, borderRadius: radius.sm, paddingHorizontal: spacing.md, paddingVertical: 10, borderWidth: 1, borderColor: colors.border },
    suggText: { fontSize: 13, color: colors.text },

    vehicleCard:        { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, flexDirection: 'row', alignItems: 'center' },
    vehicleCardActive:  { borderColor: colors.accent, backgroundColor: colors.accentBg },
    vehicleCardTitle:   { fontSize: 14, fontWeight: '600', color: colors.text },
    vehicleCardMeta:    { fontSize: 12, color: colors.text3, marginTop: 2 },
    checkmark:          { fontSize: 18, color: colors.accent, fontWeight: '700' },

    addVehicleBtn:     { backgroundColor: colors.accentBg, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(145,100,0,0.3)' },
    addVehicleBtnText: { color: colors.accent, fontWeight: '600', fontSize: 14 },

    cargoChip:         { backgroundColor: colors.s1, borderRadius: radius.md, paddingHorizontal: spacing.md, paddingVertical: 10, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm },
    cargoChipActive:   { borderColor: colors.accent, backgroundColor: colors.accentBg },
    cargoChipText:     { fontSize: 13, color: colors.text },
    cargoChipTextActive: { color: colors.accent, fontWeight: '600' },
    addCargoBtn:       { borderRadius: radius.md, paddingVertical: 11, alignItems: 'center', borderWidth: 1, borderColor: colors.border, borderStyle: 'dashed', marginTop: 2 },
    addCargoBtnText:   { fontSize: 13, color: colors.accent, fontWeight: '600' },

    addPointBtn:     { borderRadius: radius.md, paddingVertical: 10, alignItems: 'center', borderWidth: 1, borderColor: colors.border, borderStyle: 'dashed' },
    addPointBtnText: { fontSize: 13, color: colors.accent },
    mapPickBtn: { borderRadius: radius.md, paddingVertical: 11, alignItems: 'center', borderWidth: 1, borderColor: colors.accent, backgroundColor: colors.accentBg },
    mapPickBtnText: { color: colors.accent, fontWeight: '700', fontSize: 13 },
    removeBtn:       { width: 44, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.s2, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border },

    chips:      { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
    chip:       { minHeight: 36, justifyContent: 'center', paddingHorizontal: 14, paddingVertical: 8, borderRadius: radius.full, backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border },
    chipActive: { backgroundColor: colors.accentBg, borderColor: colors.accent },
    chipText:   { fontSize: 13, color: colors.text2 },
    chipTextActive: { color: colors.accent, fontWeight: '600' },

    modeCard:           { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm },
    modeCardActive:     { borderColor: colors.accent, backgroundColor: colors.accentBg },
    modeCardTitle:      { fontSize: 14, fontWeight: '700', color: colors.text },
    modeCardTitleActive:{ color: colors.accent },
    modeCardDesc:       { fontSize: 12, color: colors.text3, marginTop: 4 },

    resultCard:  { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, ...shadow.sm },
    resultTitle: { fontSize: 15, fontWeight: '700', color: colors.text, marginBottom: spacing.md },
    resultStats: { flexDirection: 'row', justifyContent: 'space-between' },

    stopCard:          { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.md, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.sm },
    stopTypeBadge:     { alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.sm, marginBottom: 6 },
    stopTypeBadgeText: { color: '#fff', fontSize: 10, fontWeight: '700' },
    stopName:  { fontSize: 14, fontWeight: '600', color: colors.text },
    stopMeta:  { fontSize: 11, color: colors.text3, marginTop: 3 },
    stopFuel:  { fontSize: 12, color: colors.accent, marginTop: 4 },

    mapBtn:     { backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', ...shadow.sm },
    mapBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },

    bottomBar: { position: 'absolute', bottom: 0, left: 0, right: 0, flexDirection: 'row', gap: spacing.sm, padding: spacing.md, backgroundColor: colors.bg, borderTopWidth: 1, borderTopColor: colors.border },
    nextBtn:         { flex: 1, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center' },
    nextBtnDisabled: { opacity: 0.5 },
    nextBtnText:     { color: '#fff', fontWeight: '700', fontSize: 14 },
    backBtn:         { flex: 0, paddingHorizontal: spacing.lg, backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    backBtnText:     { fontSize: 14, color: colors.text2, fontWeight: '600' },

    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)', justifyContent: 'flex-end' },
    modalKeyboard: { width: '100%' },
    modalCard: { backgroundColor: colors.bg, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg, padding: spacing.xl, gap: spacing.sm, borderTopWidth: 1, borderTopColor: colors.border },
    modalTitle: { fontSize: 18, fontWeight: '700', color: colors.text, marginBottom: spacing.sm },
    modalLabel: { fontSize: 12, color: colors.text3, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
    modalActions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md },
    modalCancelBtn: { flex: 1, backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
    modalCancelText: { fontSize: 14, color: colors.text2, fontWeight: '600' },
    modalSaveBtn: { flex: 2, backgroundColor: colors.accent, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center' },
    modalSaveText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
