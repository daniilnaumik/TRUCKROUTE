import React, { useState } from 'react';
import {
    View,
    Text,
    TextInput,
    TouchableOpacity,
    StyleSheet,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    ActivityIndicator,
    Alert,
} from 'react-native';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { useAuthStore } from '@/store/auth';
import { colors, spacing, radius } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'Register'>;

const roles = [
    { value: 'driver', label: 'Водитель' },
    { value: 'provider', label: 'Поставщик' },
    { value: 'fleet', label: 'Компания' },
] as const;

export default function RegisterScreen({ navigation }: Props) {
    const { register, loading } = useAuthStore();
    const [form, setForm] = useState({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });
    const [role, setRole] = useState<'driver' | 'provider' | 'fleet'>('driver');

    function set(key: keyof typeof form) {
        return (v: string) => setForm(prev => ({ ...prev, [key]: v }));
    }

    async function handleRegister() {
        const email = form.email.trim();
        const phone = form.phone.trim();

        if (!form.name.trim() || !email || !form.password || !form.password_confirmation) {
            Alert.alert('Ошибка', 'Заполните имя, email, пароль и подтверждение пароля.');
            return;
        }

        if (form.password.length < 8) {
            Alert.alert('Ошибка', 'Пароль должен быть не короче 8 символов.');
            return;
        }

        if (form.password !== form.password_confirmation) {
            Alert.alert('Ошибка', 'Пароль и подтверждение пароля не совпадают.');
            return;
        }

        const res = await register({
            ...form,
            name: form.name.trim(),
            email,
            phone,
            role,
        });

        if (!res.ok) {
            Alert.alert('Ошибка', res.message ?? 'Не удалось зарегистрироваться.');
        }
    }

    return (
        <KeyboardAvoidingView style={s.root} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
            <ScrollView contentContainerStyle={s.inner} keyboardShouldPersistTaps="handled">
                <Text style={s.logo}>TRUCKROUTE</Text>
                <Text style={s.title}>Создать аккаунт</Text>

                <View style={s.form}>
                    {[
                        { key: 'name', label: 'Имя', placeholder: 'Имя Фамилия', autoComplete: 'name' as const },
                        { key: 'email', label: 'Email', placeholder: 'email@example.com', autoComplete: 'email' as const, keyboardType: 'email-address' as const },
                        { key: 'phone', label: 'Телефон', placeholder: '+7 900 000-00-00', autoComplete: 'tel' as const },
                        { key: 'password', label: 'Пароль', placeholder: 'Минимум 8 символов', autoComplete: 'new-password' as const, secure: true },
                        { key: 'password_confirmation', label: 'Подтверждение пароля', placeholder: 'Повторите пароль', autoComplete: 'new-password' as const, secure: true },
                    ].map(f => (
                        <View key={f.key}>
                            <Text style={s.label}>{f.label}</Text>
                            <TextInput
                                style={s.input}
                                value={form[f.key as keyof typeof form]}
                                onChangeText={set(f.key as keyof typeof form)}
                                placeholder={f.placeholder}
                                placeholderTextColor={colors.text3}
                                autoCapitalize="none"
                                autoComplete={f.autoComplete}
                                keyboardType={f.keyboardType}
                                secureTextEntry={f.secure}
                            />
                        </View>
                    ))}

                    <View>
                        <Text style={s.label}>Роль</Text>
                        <View style={s.roleRow}>
                            {roles.map(r => (
                                <TouchableOpacity
                                    key={r.value}
                                    style={[s.roleChip, role === r.value && s.roleChipActive]}
                                    onPress={() => setRole(r.value)}
                                >
                                    <Text style={[s.roleChipText, role === r.value && s.roleChipTextActive]}>{r.label}</Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                    </View>

                    <TouchableOpacity style={s.btn} onPress={handleRegister} disabled={loading}>
                        {loading
                            ? <ActivityIndicator color="#fff" />
                            : <Text style={s.btnText}>Зарегистрироваться</Text>
                        }
                    </TouchableOpacity>

                    <TouchableOpacity style={s.link} onPress={() => navigation.replace('Login')}>
                        <Text style={s.linkText}>Уже есть аккаунт? Войти</Text>
                    </TouchableOpacity>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

const s = StyleSheet.create({
    root: { flex: 1, backgroundColor: colors.bg },
    inner: { flexGrow: 1, padding: spacing.xl, paddingTop: spacing.xxl },
    logo: {
        fontSize: 22,
        fontWeight: '700',
        letterSpacing: 3,
        color: colors.accent,
        textAlign: 'center',
        marginBottom: spacing.lg,
    },
    title: {
        fontSize: 26,
        fontWeight: '700',
        color: colors.text,
        textAlign: 'center',
        marginBottom: spacing.xl,
    },
    form: { gap: spacing.md },
    label: { fontSize: 13, color: colors.text2, marginBottom: 4 },
    input: {
        backgroundColor: colors.s1,
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: radius.sm,
        paddingHorizontal: spacing.md,
        paddingVertical: 12,
        fontSize: 15,
        color: colors.text,
    },
    btn: {
        backgroundColor: colors.accent,
        borderRadius: radius.sm,
        paddingVertical: 14,
        alignItems: 'center',
        marginTop: spacing.sm,
    },
    btnText: { color: '#fff', fontSize: 15, fontWeight: '600' },
    link: { alignItems: 'center', marginTop: spacing.md },
    linkText: { fontSize: 13, color: colors.accent },
    roleRow: { flexDirection: 'row', gap: 8, marginTop: 4 },
    roleChip: {
        flex: 1,
        paddingVertical: 9,
        borderRadius: radius.sm,
        backgroundColor: colors.s1,
        borderWidth: 1,
        borderColor: colors.border,
        alignItems: 'center',
    },
    roleChipActive: { borderColor: colors.accent, backgroundColor: colors.accentBg },
    roleChipText: { fontSize: 12, color: colors.text2, fontWeight: '500' },
    roleChipTextActive: { color: colors.accent, fontWeight: '700' },
});
