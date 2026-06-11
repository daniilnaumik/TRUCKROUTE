const genericImageNames = [
    'feature-fuel-actros.jpg',
    'feature-route-gas-station.jpg',
    'road-black-canyon.jpg',
    'road-dark-forest.jpg',
    'road-green-forest.jpg',
    'road-mountains-fog.jpg',
    'road-sunset-long.jpg',
    'road-sunset-low.jpg',
    'road-warm-forest.jpg',
    'truck-red.jpg',
    'truck-white.jpg',
    'trucks-night.jpg',
];

const eventImageRules = [
    {
        sources: ['/assets/images/event-border-queue.jpg'],
        keywords: [
            '\u043e\u0447\u0435\u0440\u0435\u0434',
            '\u043a\u043f\u043f',
            '\u043f\u0443\u043d\u043a\u0442 \u043f\u0440\u043e\u043f\u0443\u0441\u043a\u0430',
            '\u0433\u0440\u0430\u043d\u0438\u0446',
            '\u0442\u0430\u043c\u043e\u0436',
            '\u043a\u043e\u0437\u043b\u043e\u0432\u0438\u0447',
        ],
    },
    {
        sources: [
            '/assets/images/event-roadworks.jpg',
            '/assets/images/event-roadworks-lane.jpg',
        ],
        keywords: [
            '\u0440\u0435\u043c\u043e\u043d\u0442',
            '\u0440\u0430\u0431\u043e\u0442',
            '\u0434\u043e\u0440\u043e\u0436\u043d',
            '\u043f\u043e\u043b\u043e\u0441',
            '\u043f\u0435\u0440\u0435\u043a\u0440\u044b\u0442',
            '\u0437\u0430\u043a\u0440\u044b\u0442',
        ],
    },
    {
        sources: ['/assets/images/event-road-control.jpg'],
        keywords: [
            '\u043a\u043e\u043d\u0442\u0440\u043e\u043b',
            '\u043f\u0440\u043e\u0432\u0435\u0440',
            '\u0432\u0435\u0441\u043e\u0432',
            '\u0438\u043d\u0441\u043f\u0435\u043a\u0446',
            '\u043f\u043e\u0441\u0442',
        ],
    },
    {
        sources: ['/assets/images/event-accident.jpg'],
        keywords: [
            '\u0434\u0442\u043f',
            '\u0430\u0432\u0430\u0440',
            '\u0441\u0442\u043e\u043b\u043a',
            '\u043f\u0440\u043e\u0438\u0441\u0448\u0435\u0441\u0442',
        ],
    },
    {
        sources: ['/assets/images/event-traffic-jam.jpg'],
        keywords: [
            '\u0437\u0430\u0442\u043e\u0440',
            '\u043f\u0440\u043e\u0431\u043a',
            '\u0441\u043a\u043e\u043f\u043b',
            '\u043c\u0435\u0434\u043b\u0435\u043d',
        ],
    },
    {
        sources: ['/assets/images/event-weather-fog.jpg'],
        keywords: [
            '\u043f\u043e\u0433\u043e\u0434',
            '\u0442\u0443\u043c\u0430\u043d',
            '\u0432\u0438\u0434\u0438\u043c',
            '\u0441\u043d\u0435\u0433',
            '\u0433\u043e\u043b\u043e\u043b',
        ],
    },
];

function textForEvent(event) {
    return [
        event?.type,
        event?.title,
        event?.location,
        event?.description,
        event?.highway,
    ].filter(Boolean).join(' ').toLowerCase();
}

function isGenericEventImage(src) {
    return genericImageNames.some((name) => String(src || '').includes(name));
}

function selectImage(sources, event) {
    if (!sources?.length) return null;
    const seed = Number(event?.id ?? 0);
    return sources[Math.abs(seed) % sources.length];
}

export function eventImageSrc(event) {
    const explicitImage = event?.image_url || (event?.image ? `/assets/images/${event.image}` : '');

    if (explicitImage && !isGenericEventImage(explicitImage)) {
        return explicitImage;
    }

    const text = textForEvent(event);
    const rule = eventImageRules.find((item) =>
        item.keywords.some((keyword) => text.includes(keyword))
    );

    return selectImage(rule?.sources, event) || explicitImage || '/assets/images/road-dark-forest.jpg';
}
