import React from 'react';
import { Modal, SafeAreaView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { colors, radius, shadow, spacing } from '@/theme';

export type LocationPoint = {
    lat: number;
    lng: number;
};

type Props = {
    visible: boolean;
    title: string;
    subtitle?: string;
    point: LocationPoint;
    markerTitle?: string;
    markerDescription?: string | null;
    markerColor?: string;
    editable?: boolean;
    onChange?: (point: LocationPoint) => void;
    onClose: () => void;
    onConfirm?: () => void;
};

export default function LocationMapModal({
    visible,
    title,
    subtitle,
    point,
    markerTitle,
    markerDescription,
    markerColor = colors.accent,
    editable = false,
    onChange,
    onClose,
    onConfirm,
}: Props) {
    const coordinate = { latitude: point.lat, longitude: point.lng };

    function updateFromCoordinate(coord: { latitude: number; longitude: number }) {
        if (!editable || !onChange) return;
        onChange({ lat: coord.latitude, lng: coord.longitude });
    }

    return (
        <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
            <View style={s.root}>
                <MapView
                    style={s.map}
                    provider={PROVIDER_DEFAULT}
                    initialRegion={{
                        ...coordinate,
                        latitudeDelta: 0.06,
                        longitudeDelta: 0.06,
                    }}
                    onPress={(event) => updateFromCoordinate(event.nativeEvent.coordinate)}
                    showsCompass
                    showsUserLocation
                >
                    <Marker
                        coordinate={coordinate}
                        title={markerTitle ?? title}
                        description={markerDescription ?? subtitle}
                        pinColor={markerColor}
                        draggable={editable}
                        onDragEnd={(event) => updateFromCoordinate(event.nativeEvent.coordinate)}
                    />
                </MapView>

                <SafeAreaView style={s.top}>
                    <TouchableOpacity style={s.closeBtn} onPress={onClose}>
                        <Text style={s.closeText}>Назад</Text>
                    </TouchableOpacity>
                    <View style={s.titleCard}>
                        <Text style={s.title} numberOfLines={1}>{title}</Text>
                        {!!subtitle && <Text style={s.subtitle} numberOfLines={1}>{subtitle}</Text>}
                    </View>
                </SafeAreaView>

                {editable && (
                    <SafeAreaView style={s.bottom}>
                        <View style={s.hintCard}>
                            <Text style={s.hintTitle}>Точная метка</Text>
                            <Text style={s.hintText}>Тапните по карте или перетащите метку в нужное место.</Text>
                        </View>
                        <TouchableOpacity style={s.confirmBtn} onPress={onConfirm}>
                            <Text style={s.confirmText}>Готово</Text>
                        </TouchableOpacity>
                    </SafeAreaView>
                )}
            </View>
        </Modal>
    );
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    map: { ...StyleSheet.absoluteFillObject },
    top: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
        paddingHorizontal: spacing.md,
        paddingTop: spacing.sm,
    },
    closeBtn: {
        backgroundColor: colors.s1,
        borderRadius: radius.full,
        paddingHorizontal: spacing.md,
        paddingVertical: 11,
        borderWidth: 1,
        borderColor: colors.border,
        ...shadow.sm,
    },
    closeText: { color: colors.accent, fontWeight: '700', fontSize: 14 },
    titleCard: {
        flex: 1,
        backgroundColor: 'rgba(255,255,255,0.92)',
        borderRadius: radius.md,
        paddingHorizontal: spacing.md,
        paddingVertical: 10,
        ...shadow.sm,
    },
    title: { color: '#171717', fontSize: 15, fontWeight: '700' },
    subtitle: { color: '#555', fontSize: 12, marginTop: 2 },
    bottom: {
        position: 'absolute',
        left: 0,
        right: 0,
        bottom: 0,
        padding: spacing.md,
        gap: spacing.sm,
    },
    hintCard: {
        backgroundColor: 'rgba(255,255,255,0.94)',
        borderRadius: radius.md,
        padding: spacing.md,
        ...shadow.sm,
    },
    hintTitle: { color: '#171717', fontSize: 16, fontWeight: '700' },
    hintText: { color: '#555', fontSize: 12, marginTop: 4, lineHeight: 17 },
    confirmBtn: {
        backgroundColor: colors.accent,
        borderRadius: radius.md,
        paddingVertical: 15,
        alignItems: 'center',
        ...shadow.sm,
    },
    confirmText: { color: '#fff', fontWeight: '800', fontSize: 16 },
});
