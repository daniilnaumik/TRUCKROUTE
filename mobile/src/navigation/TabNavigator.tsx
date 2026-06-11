import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '@/store/auth';
import AppIcon, { AppIconName } from '@/components/AppIcon';
import HomeScreen from '@/screens/HomeScreen';
import RoutesScreen from '@/screens/RoutesScreen';
import NewsScreen from '@/screens/NewsScreen';
import PlacesScreen from '@/screens/PlacesScreen';
import ProfileScreen from '@/screens/ProfileScreen';
import ProviderPlacesScreen from '@/screens/provider/ProviderPlacesScreen';
import FleetScreen from '@/screens/fleet/FleetScreen';
import { colors } from '@/theme';

const Tab = createBottomTabNavigator();

function tabIcon(
    active: AppIconName,
    inactive: AppIconName,
) {
    return ({ focused, color, size }: { focused: boolean; color: string; size: number }) => (
        <AppIcon
            name={focused ? active : inactive}
            size={Math.max(size, 23)}
            color={color}
        />
    );
}

export default function TabNavigator() {
    const { user } = useAuthStore();
    const insets = useSafeAreaInsets();
    const role = user?.role ?? 'driver';
    const bottomInset = Math.max(insets.bottom, 24);

    return (
        <Tab.Navigator
            screenOptions={{
                tabBarActiveTintColor: colors.accent,
                tabBarInactiveTintColor: colors.text3,
                tabBarLabelPosition: 'below-icon',
                tabBarAllowFontScaling: false,
                tabBarHideOnKeyboard: true,
                tabBarStyle: {
                    backgroundColor: colors.bg,
                    borderTopColor: colors.border,
                    borderTopWidth: 1,
                    height: 72 + bottomInset,
                    paddingTop: 8,
                    paddingBottom: bottomInset,
                },
                tabBarItemStyle: {
                    paddingTop: 4,
                    paddingBottom: 2,
                },
                tabBarLabelStyle: {
                    fontSize: 11,
                    lineHeight: 13,
                    fontWeight: '600',
                    marginTop: 2,
                    marginBottom: 0,
                },
                tabBarIconStyle: {
                    marginTop: 0,
                    marginBottom: 0,
                },
                headerStyle: { backgroundColor: colors.bg },
                headerTintColor: colors.text,
                headerShadowVisible: false,
            }}
        >
            <Tab.Screen
                name="Главная"
                component={HomeScreen}
                options={{ tabBarIcon: tabIcon('home', 'home-outline') }}
            />

            {role === 'provider' ? (
                <Tab.Screen
                    name="Мои объекты"
                    component={ProviderPlacesScreen}
                    options={{ tabBarIcon: tabIcon('storefront', 'storefront-outline') }}
                />
            ) : role === 'fleet' ? (
                <Tab.Screen
                    name="Автопарк"
                    component={FleetScreen}
                    options={{ tabBarIcon: tabIcon('bus', 'bus-outline') }}
                />
            ) : (
                <Tab.Screen
                    name="Маршруты"
                    component={RoutesScreen}
                    options={{ tabBarIcon: tabIcon('map', 'map-outline') }}
                />
            )}

            <Tab.Screen
                name="Новости"
                component={NewsScreen}
                options={{ tabBarIcon: tabIcon('warning', 'warning-outline') }}
            />
            <Tab.Screen
                name="Объекты"
                component={PlacesScreen}
                options={{ tabBarIcon: tabIcon('location', 'location-outline') }}
            />
            <Tab.Screen
                name="Профиль"
                component={ProfileScreen}
                options={{ tabBarIcon: tabIcon('person', 'person-outline') }}
            />
        </Tab.Navigator>
    );
}
