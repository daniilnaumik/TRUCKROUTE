import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import client from '@/api/client';
import { useAuthStore } from '@/store/auth';
import { navigationRef } from '@/navigation/navigationRef';

Notifications.setNotificationHandler({
    handleNotification: async () => ({
        shouldShowAlert: true,
        shouldShowBanner: true,
        shouldShowList: true,
        shouldPlaySound: true,
        shouldSetBadge: true,
    }),
});

export function useNotifications(onProximityAlert?: (data: any) => void) {
    const responseListener = useRef<Notifications.Subscription | null>(null);
    const notifListener = useRef<Notifications.Subscription | null>(null);
    const userId = useAuthStore(state => state.user?.id ?? null);

    async function register() {
        const { status: existing } = await Notifications.getPermissionsAsync();
        let finalStatus = existing;

        if (existing !== 'granted') {
            const { status } = await Notifications.requestPermissionsAsync();
            finalStatus = status;
        }

        if (finalStatus !== 'granted') return;

        try {
            const token = (await Notifications.getExpoPushTokenAsync()).data;
            await client.post('/devices', {
                platform: Platform.OS,
                fcm_token: token,
                app_version: '1.0.0',
                locale: 'ru',
            });
        } catch {
            // Device token registration should not block app startup.
        }
    }

    useEffect(() => {
        if (userId) {
            register();
        }
    }, [userId]);

    useEffect(() => {
        notifListener.current = Notifications.addNotificationReceivedListener((notification) => {
            const data = notification.request.content.data;
            if (data?.type === 'proximity_alert' && onProximityAlert) {
                onProximityAlert(data);
            }
        });

        responseListener.current = Notifications.addNotificationResponseReceivedListener((response) => {
            const data = response.notification.request.content.data;
            const assignmentId = Number(data?.assignment_id);

            if (
                data?.type === 'fleet_assignment'
                && Number.isFinite(assignmentId)
                && navigationRef.isReady()
            ) {
                navigationRef.navigate('AssignmentDetail', { id: assignmentId });
            }
        });

        return () => {
            notifListener.current?.remove();
            responseListener.current?.remove();
        };
    }, [onProximityAlert]);
}
