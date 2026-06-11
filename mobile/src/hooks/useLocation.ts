import { useCallback, useRef } from 'react';
import { Platform } from 'react-native';
import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { TripLocationResponse, updateLocation } from '@/api/trip';
import { notifyNearbyRecommendations, ProximityAlertPreferences, resetProximityAlerts } from '@/services/proximityAlerts';

const BACKGROUND_TASK = 'TRUCKROUTE_LOCATION_TASK';
const UPDATE_INTERVAL_MS = 60_000;

export interface TrackingResult {
    active: boolean;
    background: boolean;
    message?: string;
}

if (!TaskManager.isTaskDefined(BACKGROUND_TASK)) {
    TaskManager.defineTask(BACKGROUND_TASK, async ({ data, error }: any) => {
        if (error) return;

        const [location] = data?.locations ?? [];
        if (!location) return;

        try {
            const response = await updateLocation(location.coords.latitude, location.coords.longitude, {
                accuracy_m: location.coords.accuracy,
                speed_kmh: location.coords.speed != null ? Math.max(0, location.coords.speed * 3.6) : null,
            });
            await notifyNearbyRecommendations(response);
        } catch {
            // The next location tick will retry.
        }
    });
}

export function useLocation() {
    const foregroundSubscription = useRef<Location.LocationSubscription | null>(null);

    const sendCurrentPosition = useCallback(async (
        onUpdate?: (response: TripLocationResponse) => void,
        preferences?: ProximityAlertPreferences,
    ) => {
        const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        const response = await updateLocation(loc.coords.latitude, loc.coords.longitude, {
            accuracy_m: loc.coords.accuracy,
            speed_kmh: loc.coords.speed != null ? Math.max(0, loc.coords.speed * 3.6) : null,
        });
        await notifyNearbyRecommendations(response, preferences);
        onUpdate?.(response);

        return { lat: loc.coords.latitude, lng: loc.coords.longitude };
    }, []);

    const requestPermissions = useCallback(async (): Promise<boolean> => {
        const { status } = await Location.requestForegroundPermissionsAsync();
        return status === 'granted';
    }, []);

    const startForegroundUpdates = useCallback(async (
        onUpdate?: (response: TripLocationResponse) => void,
        preferences?: ProximityAlertPreferences,
    ) => {
        foregroundSubscription.current?.remove();
        foregroundSubscription.current = await Location.watchPositionAsync(
            {
                accuracy: Location.Accuracy.Balanced,
                distanceInterval: 250,
                timeInterval: 30_000,
            },
            async (loc) => {
                try {
                    const response = await updateLocation(loc.coords.latitude, loc.coords.longitude, {
                        accuracy_m: loc.coords.accuracy,
                        speed_kmh: loc.coords.speed != null ? Math.max(0, loc.coords.speed * 3.6) : null,
                    });
                    const alert = await notifyNearbyRecommendations(response, preferences);
                    onUpdate?.(response);
                    if (alert) {
                        onUpdate?.({
                            ...response,
                            has_proximity_alert: true,
                        });
                    }
                } catch {
                    // The next GPS tick will retry.
                }
            },
        );
    }, []);

    const startTracking = useCallback(async (
        onUpdate?: (response: TripLocationResponse) => void,
        preferences?: ProximityAlertPreferences,
    ): Promise<TrackingResult> => {
        const foregroundGranted = await requestPermissions();
        if (!foregroundGranted) {
            return {
                active: false,
                background: false,
                message: 'Разрешите доступ к геолокации, чтобы TruckRoute мог вести поездку.',
            };
        }

        let backgroundGranted = false;
        try {
            const { status } = await Location.requestBackgroundPermissionsAsync();
            backgroundGranted = status === 'granted';
        } catch {
            backgroundGranted = false;
        }

        if (!backgroundGranted) {
            try {
                await sendCurrentPosition(onUpdate, preferences);
                await startForegroundUpdates(onUpdate, preferences);
            } catch {
                // Foreground position can also be unavailable indoors; trip start should still survive.
            }

            return {
                active: true,
                background: false,
                message: 'Поездка началась. Фоновая геолокация недоступна в Expo Go или не разрешена на телефоне, поэтому обновления будут работать только пока приложение открыто.',
            };
        }

        try {
            const isRunning = await Location.hasStartedLocationUpdatesAsync(BACKGROUND_TASK).catch(() => false);
            if (!isRunning) {
                await Location.startLocationUpdatesAsync(BACKGROUND_TASK, {
                    accuracy: Location.Accuracy.Balanced,
                    distanceInterval: 200,
                    timeInterval: UPDATE_INTERVAL_MS,
                    showsBackgroundLocationIndicator: Platform.OS === 'ios',
                    ...(Platform.OS === 'android'
                        ? {
                            foregroundService: {
                                notificationTitle: 'TruckRoute активна',
                                notificationBody: 'Слежение за маршрутом включено.',
                                notificationColor: '#c99b3a',
                            },
                        }
                        : {}),
                });
            }
            await sendCurrentPosition(onUpdate, preferences).catch(() => null);
            await startForegroundUpdates(onUpdate, preferences).catch(() => null);

            return { active: true, background: true };
        } catch (error: any) {
            try {
                await sendCurrentPosition(onUpdate, preferences);
                await startForegroundUpdates(onUpdate, preferences);
            } catch {
                // Keep the trip active even if an immediate GPS fix is unavailable.
            }

            return {
                active: true,
                background: false,
                message: error?.message
                    ? `Поездка началась, но фоновое слежение не включилось: ${error.message}`
                    : 'Поездка началась, но фоновое слежение не включилось.',
            };
        }
    }, [requestPermissions, sendCurrentPosition, startForegroundUpdates]);

    const stopTracking = useCallback(async () => {
        foregroundSubscription.current?.remove();
        foregroundSubscription.current = null;
        resetProximityAlerts();
        const isRunning = await Location.hasStartedLocationUpdatesAsync(BACKGROUND_TASK).catch(() => false);
        if (isRunning) {
            await Location.stopLocationUpdatesAsync(BACKGROUND_TASK);
        }
    }, []);

    const getCurrentPosition = useCallback(async (): Promise<{ lat: number; lng: number } | null> => {
        try {
            const loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
            return { lat: loc.coords.latitude, lng: loc.coords.longitude };
        } catch {
            return null;
        }
    }, []);

    return { startTracking, stopTracking, getCurrentPosition, requestPermissions };
}
