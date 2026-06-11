/** Shared design tokens — mirrors web CSS variables */
export const colors = {
    bg:          '#f4f3ef',
    s1:          '#eceae4',
    s2:          '#e4e1da',
    text:        '#1a1b19',
    text2:       '#4d4a44',
    text3:       '#6a6762',
    accent:      '#916400',
    accentDark:  '#c99b3a',   // for dark contexts
    accentBg:    'rgba(145,100,0,0.09)',
    red:         '#c0312a',
    green:       '#2d7a4f',
    border:      'rgba(0,0,0,0.085)',
    borderMid:   'rgba(0,0,0,0.14)',
    white:       '#ffffff',
    dark:        '#0b0c0b',
};

export const spacing = {
    xs: 4,
    sm: 8,
    md: 16,
    lg: 24,
    xl: 32,
    xxl: 48,
};

export const radius = {
    sm: 6,
    md: 10,
    lg: 16,
    full: 999,
};

export const font = {
    regular:   'System',
    mono:      'monospace',   // IBM Plex Mono not bundled — use system mono
};

export const shadow = {
    sm: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.07,
        shadowRadius: 4,
        elevation: 2,
    },
    md: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.10,
        shadowRadius: 8,
        elevation: 4,
    },
};
