const genericArticleImageNames = [
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

const articleImageRules = [
    {
        src: '/assets/images/event-weather-fog.jpg',
        keywords: [
            '\u043f\u043e\u0433\u043e\u0434',
            '\u0442\u0443\u043c\u0430\u043d',
            '\u0432\u0438\u0434\u0438\u043c',
            '\u043c\u043e\u043a\u0440',
        ],
    },
    {
        src: '/assets/images/news-route-planning.jpg',
        keywords: [
            '\u043f\u043b\u0430\u043d\u0438\u0440',
            '\u0440\u0435\u0439\u0441',
            '\u0431\u0440\u0435\u0441\u0442',
            '\u043c\u043e\u0441\u043a\u0432',
            '\u043b\u043e\u0433\u0438\u0441\u0442',
            '\u043e\u0441\u0442\u0430\u043d\u043e\u0432',
        ],
    },
    {
        src: '/assets/images/news-overnight-parking.jpg',
        keywords: [
            '\u043f\u0435\u0440\u0435\u043d\u043e\u0447',
            '\u043d\u043e\u0447\u043b\u0435\u0433',
            '\u0441\u0442\u043e\u044f\u043d',
            '\u043e\u0445\u0440\u0430\u043d',
            '\u0431\u0435\u0437\u043e\u043f\u0430\u0441',
        ],
    },
    {
        src: '/assets/images/news-adblue-station.jpg',
        keywords: [
            'adblue',
            '\u0430\u0437\u0441',
            '\u0442\u043e\u043f\u043b\u0438\u0432',
            '\u0437\u0430\u043f\u0440\u0430\u0432',
            '\u0431\u0435\u043b\u043e\u0440\u0443\u0441\u043d\u0435\u0444\u0442',
        ],
    },
    {
        src: '/assets/images/news-app-cards.jpg',
        keywords: [
            'truckroute',
            '\u043a\u0430\u0440\u0442\u043e\u0447',
            '\u043f\u0440\u0438\u043b\u043e\u0436',
            '\u0434\u0435\u043c\u043e',
            '\u0442\u043e\u0447\u0435\u043a',
        ],
    },
    {
        src: '/assets/images/news-m1-upgrade.jpg',
        keywords: [
            '\u043e\u0431\u043d\u043e\u0432',
            '\u0443\u0447\u0430\u0441\u0442\u043e\u043a',
            '\u043c1',
            '\u0441\u0442\u043e\u043b\u0431\u0446',
            '\u0440\u0430\u0437\u043c\u0435\u0442',
            '\u0440\u0430\u0437\u0433\u043e\u043d',
        ],
    },
];

function textForArticle(article) {
    return [
        article?.title,
        article?.excerpt,
        ...(article?.tags ?? []),
    ].filter(Boolean).join(' ').toLowerCase();
}

function isGenericArticleImage(src) {
    return genericArticleImageNames.some((name) => String(src || '').includes(name));
}

export function articleImageSrc(article) {
    const explicitImage = article?.image_url
        || article?.gallery?.[0]
        || (article?.image ? `/assets/images/${article.image}` : '');

    if (explicitImage && !isGenericArticleImage(explicitImage)) {
        return explicitImage;
    }

    const text = textForArticle(article);
    const rule = articleImageRules.find((item) =>
        item.keywords.some((keyword) => text.includes(keyword))
    );

    return rule?.src || explicitImage || '/assets/images/news-hero.jpg';
}

export function articleHasAttachedMedia(article) {
    return !!article?.image_url
        || !!article?.image
        || (article?.gallery ?? []).length > 0;
}

export function articleGalleryImages(article) {
    const images = [
        articleImageSrc(article),
        ...(article?.gallery ?? []).filter((img) => !isGenericArticleImage(img)),
    ];

    return [...new Set(images)];
}

export const newsHeroImage = '/assets/images/news-hero.jpg';
