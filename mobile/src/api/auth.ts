import client from './client';
import * as SecureStore from 'expo-secure-store';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: 'driver' | 'provider' | 'fleet' | 'admin';
    status: string;
    avatar_url: string | null;
}

export interface LoginPayload {
    email: string;
    password: string;
    device_name?: string;
}

export interface RegisterPayload extends LoginPayload {
    name: string;
    phone?: string;
    password_confirmation: string;
    role?: 'driver' | 'provider' | 'fleet';
}

async function saveToken(token: string) {
    await SecureStore.setItemAsync('auth_token', token);
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
    const { data } = await client.post('/auth/login', {
        ...payload,
        device_name: payload.device_name ?? 'TruckRoute Mobile',
    });
    await saveToken(data.token);
    return data.user;
}

export async function register(payload: RegisterPayload): Promise<AuthUser> {
    const { data } = await client.post('/auth/register', {
        ...payload,
        device_name: 'TruckRoute Mobile',
    });
    await saveToken(data.token);
    return data.user;
}

export async function me(): Promise<AuthUser> {
    const { data } = await client.get('/auth/me');
    return data.user;
}

export async function logout(): Promise<void> {
    try {
        await client.post('/auth/logout');
    } catch { /* ignore */ }
    await SecureStore.deleteItemAsync('auth_token');
}
