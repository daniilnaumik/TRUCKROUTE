import React, { useState } from 'react';
import {
    View, Text, TextInput, TouchableOpacity, StyleSheet,
    KeyboardAvoidingView, Platform, ScrollView, ActivityIndicator, Alert,
} from 'react-native';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '@/navigation';
import { useAuthStore } from '@/store/auth';
import { colors, spacing, radius } from '@/theme';

type Props = NativeStackScreenProps<RootStackParamList, 'Login'>;

export default function LoginScreen({ navigation }: Props) {
    const { login, loading } = useAuthStore();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    async function handleLogin() {
        if (!email || !password) {
            Alert.alert('Ошибка', 'Введите email и пароль');
            return;
        }
        const res = await login({ email, password });
        if (!res.ok) {
            Alert.alert('Ошибка входа', res.message ?? 'Неверные данные');
        }
    }

    return (
        <KeyboardAvoidingView style={s.root} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
            <ScrollView contentContainerStyle={s.inner} keyboardShouldPersistTaps="handled">
                <Text style={s.logo}>TRUCKROUTE</Text>
                <Text style={s.title}>Вход в аккаунт</Text>
                <Text style={s.sub}>Доступ к маршрутам, профилю и уведомлениям.</Text>

                <View style={s.form}>
                    <Text style={s.label}>Email</Text>
                    <TextInput
                        style={s.input}
                        value={email}
                        onChangeText={setEmail}
                        keyboardType="email-address"
                        autoCapitalize="none"
                        autoComplete="email"
                        placeholder="driver@example.com"
                        placeholderTextColor={colors.text3}
                    />

                    <Text style={[s.label, { marginTop: spacing.md }]}>Пароль</Text>
                    <TextInput
                        style={s.input}
                        value={password}
                        onChangeText={setPassword}
                        secureTextEntry
                        autoComplete="password"
                        placeholder="••••••••"
                        placeholderTextColor={colors.text3}
                        onSubmitEditing={handleLogin}
                    />

                    <TouchableOpacity style={s.btn} onPress={handleLogin} disabled={loading}>
                        {loading
                            ? <ActivityIndicator color="#fff" />
                            : <Text style={s.btnText}>Войти</Text>
                        }
                    </TouchableOpacity>

                    <TouchableOpacity style={s.link} onPress={() => navigation.replace('Register')}>
                        <Text style={s.linkText}>Нет аккаунта? Зарегистрироваться</Text>
                    </TouchableOpacity>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

const s = StyleSheet.create({
    root:   { flex: 1, backgroundColor: colors.bg },
    inner:  { flexGrow: 1, justifyContent: 'center', padding: spacing.xl },
    logo:   { fontSize: 22, fontWeight: '700', letterSpacing: 3, color: colors.accent, textAlign: 'center', marginBottom: spacing.xl },
    title:  { fontSize: 26, fontWeight: '700', color: colors.text, textAlign: 'center' },
    sub:    { fontSize: 14, color: colors.text2, textAlign: 'center', marginTop: spacing.sm, marginBottom: spacing.xl },
    form:   { gap: spacing.xs },
    label:  { fontSize: 13, color: colors.text2, marginBottom: 4 },
    input:  { backgroundColor: colors.s1, borderWidth: 1, borderColor: colors.border, borderRadius: radius.sm, paddingHorizontal: spacing.md, paddingVertical: 12, fontSize: 15, color: colors.text },
    btn:    { backgroundColor: colors.accent, borderRadius: radius.sm, paddingVertical: 14, alignItems: 'center', marginTop: spacing.lg },
    btnText:{ color: '#fff', fontSize: 15, fontWeight: '600' },
    link:   { alignItems: 'center', marginTop: spacing.md, paddingVertical: spacing.sm },
    linkText: { fontSize: 13, color: colors.accent },
});
