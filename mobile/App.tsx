import React, { useEffect, useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ActivityIndicator, View } from 'react-native';
import { useAuthStore } from '@/store/auth';
import Navigation from '@/navigation';
import { useNotifications } from '@/hooks/useNotifications';
import { colors } from '@/theme';

export default function App() {
    const { initialize, initialized } = useAuthStore();
    const [startupChecked, setStartupChecked] = useState(false);
    useNotifications();

    useEffect(() => {
        let mounted = true;

        initialize()
            .catch((error) => {
                console.warn('Auth initialization failed', error);
            })
            .finally(() => {
                if (mounted) {
                    setStartupChecked(true);
                }
            });

        return () => {
            mounted = false;
        };
    }, []);

    if (!initialized && !startupChecked) {
        return (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg }}>
                <ActivityIndicator size="large" color={colors.accent} />
            </View>
        );
    }

    return (
        <SafeAreaProvider>
            <StatusBar style="dark" backgroundColor={colors.bg} />
            <Navigation />
        </SafeAreaProvider>
    );
}
