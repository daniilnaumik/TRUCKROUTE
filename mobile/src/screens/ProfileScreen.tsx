import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { useAuthStore } from '@/store/auth';
import { colors, spacing, radius, shadow } from '@/theme';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function ProfileScreen() {
    const nav = useNavigation<Nav>();
    const { user, logout } = useAuthStore();

    function handleLogout() {
        Alert.alert('Выход', 'Выйти из аккаунта?', [
            { text: 'Отмена', style: 'cancel' },
            { text: 'Выйти', style: 'destructive', onPress: logout },
        ]);
    }

    if (!user) return null;

    const roleLabels: Record<string, string> = {
        driver: 'Водитель', provider: 'Поставщик', fleet: 'Автопарк', admin: 'Администратор',
    };

    return (
        <ScrollView style={s.root} contentContainerStyle={{ padding: spacing.md, paddingBottom: 40 }}>
            {/* User card */}
            <View style={s.card}>
                <View style={s.avatar}>
                    <Text style={s.avatarText}>{user.name[0].toUpperCase()}</Text>
                </View>
                <Text style={s.name}>{user.name}</Text>
                <Text style={s.email}>{user.email}</Text>
                <View style={s.roleBadge}>
                    <Text style={s.roleText}>{roleLabels[user.role] ?? user.role}</Text>
                </View>
            </View>

            {/* Info rows */}
            {user.phone && (
                <InfoRow label="Телефон" value={user.phone} />
            )}
            <InfoRow label="ID аккаунта" value={String(user.id)} mono />

            {/* Driver-only: vehicle management */}
            {(user.role === 'driver' || user.role === 'admin') && (
                <TouchableOpacity style={s.menuItem} onPress={() => nav.navigate('Vehicles')}>
                    <Text style={s.menuItemText}>🚛  Мой транспорт</Text>
                    <Text style={s.menuItemArrow}>→</Text>
                </TouchableOpacity>
            )}

            {/* Fleet management */}
            {(user.role === 'fleet' || user.role === 'admin') && (
                <TouchableOpacity style={s.menuItem} onPress={() => nav.navigate('FleetList')}>
                    <Text style={s.menuItemText}>🏢  Управление автопарком</Text>
                    <Text style={s.menuItemArrow}>→</Text>
                </TouchableOpacity>
            )}

            {user.role === 'driver' && (
                <>
                    <TouchableOpacity style={s.menuItem} onPress={() => nav.navigate('FleetList')}>
                        <Text style={s.menuItemText}>🏢  Рабочие автопарки</Text>
                        <Text style={s.menuItemArrow}>→</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={s.menuItem} onPress={() => nav.navigate('DriverAssignments')}>
                        <Text style={s.menuItemText}>📋  Мои задания</Text>
                        <Text style={s.menuItemArrow}>→</Text>
                    </TouchableOpacity>
                </>
            )}

            {/* Provider management */}
            {(user.role === 'provider' || user.role === 'admin') && (
                <TouchableOpacity style={s.menuItem} onPress={() => nav.navigate('ProviderPlaces')}>
                    <Text style={s.menuItemText}>📍  Мои объекты</Text>
                    <Text style={s.menuItemArrow}>→</Text>
                </TouchableOpacity>
            )}

            {/* Logout */}
            <TouchableOpacity style={s.logoutBtn} onPress={handleLogout}>
                <Text style={s.logoutText}>Выйти из аккаунта</Text>
            </TouchableOpacity>

            <Text style={s.version}>TruckRoute Mobile v1.0.0</Text>
        </ScrollView>
    );
}

function InfoRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <View style={r.row}>
            <Text style={r.label}>{label}</Text>
            <Text style={[r.value, mono && r.mono]}>{value}</Text>
        </View>
    );
}

const s = StyleSheet.create({
    root:      { flex: 1, backgroundColor: colors.bg },
    card:      { backgroundColor: colors.s1, borderRadius: radius.md, padding: spacing.xl, alignItems: 'center', borderWidth: 1, borderColor: colors.border, ...shadow.sm, marginBottom: spacing.lg },
    avatar:    { width: 72, height: 72, borderRadius: 36, backgroundColor: colors.accent, justifyContent: 'center', alignItems: 'center', marginBottom: spacing.md },
    avatarText:{ fontSize: 28, fontWeight: '700', color: '#fff' },
    name:      { fontSize: 20, fontWeight: '700', color: colors.text },
    email:     { fontSize: 13, color: colors.text2, marginTop: 4 },
    roleBadge: { backgroundColor: colors.accentBg, borderWidth: 1, borderColor: 'rgba(145,100,0,0.25)', borderRadius: radius.full, paddingHorizontal: 14, paddingVertical: 5, marginTop: spacing.sm },
    roleText:  { fontSize: 12, color: colors.accent, fontWeight: '600', letterSpacing: 0.5 },
    logoutBtn: { backgroundColor: colors.s2, borderRadius: radius.md, paddingVertical: 14, alignItems: 'center', marginTop: spacing.xl, borderWidth: 1, borderColor: colors.border },
    logoutText:{ fontSize: 14, color: colors.red, fontWeight: '600' },
    version:   { textAlign: 'center', marginTop: spacing.lg, fontSize: 11, color: colors.text3 },
    menuItem:  { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: colors.s1, borderRadius: radius.md, paddingHorizontal: spacing.md, paddingVertical: 14, borderWidth: 1, borderColor: colors.border, marginTop: spacing.sm },
    menuItemText:  { fontSize: 14, color: colors.text, fontWeight: '500' },
    menuItemArrow: { fontSize: 16, color: colors.accent },
});

const r = StyleSheet.create({
    row:   { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: spacing.md, borderBottomWidth: 1, borderBottomColor: colors.border },
    label: { fontSize: 13, color: colors.text3 },
    value: { fontSize: 13, color: colors.text, fontWeight: '500' },
    mono:  { fontFamily: 'monospace' },
});
