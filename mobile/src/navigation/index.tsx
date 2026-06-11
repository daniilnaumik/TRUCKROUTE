import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuthStore } from '@/store/auth';
import TabNavigator from './TabNavigator';
import LoginScreen from '@/screens/auth/LoginScreen';
import RegisterScreen from '@/screens/auth/RegisterScreen';
import RouteDetailScreen from '@/screens/RouteDetailScreen';
import PlaceDetailScreen from '@/screens/PlaceDetailScreen';
import MapScreen from '@/screens/MapScreen';
import VehiclesScreen from '@/screens/VehiclesScreen';
import VehicleFormScreen from '@/screens/VehicleFormScreen';
import RouteBuilderScreen from '@/screens/RouteBuilderScreen';
import ProviderPlacesScreen from '@/screens/provider/ProviderPlacesScreen';
import ProviderPlaceFormScreen from '@/screens/provider/ProviderPlaceFormScreen';
import FleetScreen from '@/screens/fleet/FleetScreen';
import FleetDetailScreen from '@/screens/fleet/FleetDetailScreen';
import DriverAssignmentsScreen from '@/screens/fleet/DriverAssignmentsScreen';
import AssignmentDetailScreen from '@/screens/fleet/AssignmentDetailScreen';
import AssignmentMapScreen from '@/screens/fleet/AssignmentMapScreen';
import NewsDetailScreen from '@/screens/NewsDetailScreen';
import EventDetailScreen from '@/screens/EventDetailScreen';
import { colors } from '@/theme';
import { navigationRef } from './navigationRef';

export type RootStackParamList = {
    Tabs: undefined;
    Login: undefined;
    Register: undefined;
    RouteDetail: { id: number };
    RouteBuilder: undefined;
    PlaceDetail: { id: number };
    Map: { routeId?: number; preferredFuelBrand?: string };
    Vehicles: undefined;
    VehicleForm: { id?: number };
    ProviderPlaces: undefined;
    ProviderPlaceForm: { id?: number };
    FleetList: undefined;
    FleetDetail: { id: number };
    DriverAssignments: undefined;
    AssignmentDetail: { id: number; fleetId?: number };
    AssignmentMap: { id: number; fleetId?: number };
    NewsDetail: { slug: string };
    EventDetail: { id: number };
};

const Stack = createNativeStackNavigator<RootStackParamList>();

export default function Navigation() {
    const { isAuthenticated } = useAuthStore();

    return (
        <NavigationContainer ref={navigationRef}>
            <Stack.Navigator
                screenOptions={{
                    headerStyle: { backgroundColor: colors.bg },
                    headerTintColor: colors.text,
                    headerTitleStyle: { fontSize: 16, fontWeight: '600' },
                    headerShadowVisible: false,
                    contentStyle: { backgroundColor: colors.bg },
                }}
            >
                {isAuthenticated() ? (
                    <>
                        <Stack.Screen name="Tabs" component={TabNavigator} options={{ headerShown: false }} />
                        <Stack.Screen name="RouteDetail" component={RouteDetailScreen} options={{ title: 'Маршрут' }} />
                        <Stack.Screen name="RouteBuilder" component={RouteBuilderScreen} options={{ title: 'Новый маршрут' }} />
                        <Stack.Screen name="PlaceDetail" component={PlaceDetailScreen} options={{ title: 'Объект' }} />
                        <Stack.Screen name="Map" component={MapScreen} options={{ title: 'Карта', headerShown: false }} />
                        <Stack.Screen name="Vehicles" component={VehiclesScreen} options={{ title: 'Мой транспорт' }} />
                        <Stack.Screen name="VehicleForm" component={VehicleFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Изменить транспорт' : 'Добавить транспорт' })} />
                        <Stack.Screen name="ProviderPlaces" component={ProviderPlacesScreen} options={{ title: 'Мои объекты' }} />
                        <Stack.Screen name="ProviderPlaceForm" component={ProviderPlaceFormScreen} options={({ route }) => ({ title: route.params?.id ? 'Изменить объект' : 'Добавить объект' })} />
                        <Stack.Screen name="FleetList" component={FleetScreen} options={{ title: 'Автопарк' }} />
                        <Stack.Screen name="FleetDetail" component={FleetDetailScreen} options={{ title: 'Автопарк' }} />
                        <Stack.Screen name="DriverAssignments" component={DriverAssignmentsScreen} options={{ title: 'Мои задания' }} />
                        <Stack.Screen name="AssignmentDetail" component={AssignmentDetailScreen} options={{ title: 'Задание' }} />
                        <Stack.Screen name="AssignmentMap" component={AssignmentMapScreen} options={{ title: 'Карта задания', headerShown: false }} />
                        <Stack.Screen name="NewsDetail" component={NewsDetailScreen} options={{ title: 'Статья' }} />
                        <Stack.Screen name="EventDetail" component={EventDetailScreen} options={{ title: 'Событие' }} />
                    </>
                ) : (
                    <>
                        <Stack.Screen name="Login" component={LoginScreen} options={{ headerShown: false }} />
                        <Stack.Screen name="Register" component={RegisterScreen} options={{ headerShown: false }} />
                    </>
                )}
            </Stack.Navigator>
        </NavigationContainer>
    );
}
