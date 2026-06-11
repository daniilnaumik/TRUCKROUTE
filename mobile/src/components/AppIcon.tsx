import React from 'react';
import { Image, ImageSourcePropType, ImageStyle, StyleProp } from 'react-native';

const iconSources = {
    home: require('../../assets/icons/home.png'),
    'home-outline': require('../../assets/icons/home-outline.png'),
    bus: require('../../assets/icons/bus.png'),
    'bus-outline': require('../../assets/icons/bus-outline.png'),
    map: require('../../assets/icons/map.png'),
    'map-outline': require('../../assets/icons/map-outline.png'),
    storefront: require('../../assets/icons/storefront.png'),
    'storefront-outline': require('../../assets/icons/storefront-outline.png'),
    warning: require('../../assets/icons/warning.png'),
    'warning-outline': require('../../assets/icons/warning-outline.png'),
    location: require('../../assets/icons/location.png'),
    'location-outline': require('../../assets/icons/location-outline.png'),
    person: require('../../assets/icons/person.png'),
    'person-outline': require('../../assets/icons/person-outline.png'),
    'notifications-outline': require('../../assets/icons/notifications-outline.png'),
} satisfies Record<string, ImageSourcePropType>;

export type AppIconName = keyof typeof iconSources;

type Props = {
    name: AppIconName;
    size: number;
    color: string;
    style?: StyleProp<ImageStyle>;
};

export default function AppIcon({ name, size, color, style }: Props) {
    return (
        <Image
            source={iconSources[name]}
            resizeMode="contain"
            style={[{ width: size, height: size, tintColor: color }, style]}
        />
    );
}
