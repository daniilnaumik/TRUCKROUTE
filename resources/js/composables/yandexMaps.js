let readyPromise = null;

function scriptUrl() {
    const key = window.__YANDEX_JS_API_KEY__ || '';
    const params = new URLSearchParams({
        apikey: key,
        lang: 'ru_RU',
        load: 'package.full',
    });

    return `https://api-maps.yandex.ru/2.1/?${params.toString()}`;
}

function waitForReady() {
    return new Promise((resolve, reject) => {
        if (!window.ymaps) {
            reject(new Error('Yandex Maps API script loaded without ymaps global.'));
            return;
        }

        window.ymaps.ready(() => resolve(window.ymaps));
    });
}

export function loadYandexMaps() {
    if (window.ymaps) {
        return waitForReady();
    }

    if (readyPromise) {
        return readyPromise;
    }

    readyPromise = new Promise((resolve, reject) => {
        let script = document.getElementById('yandex-maps-api');

        if (!script) {
            script = Array.from(document.scripts).find((item) =>
                item.src.includes('api-maps.yandex.ru/2.1/')
            );
        }

        let settled = false;
        const finish = (callback, value) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            callback(value);
        };
        const onLoad = () => {
            waitForReady()
                .then((ymaps) => finish(resolve, ymaps))
                .catch((error) => {
                    readyPromise = null;
                    finish(reject, error);
                });
        };
        const onError = () => {
            readyPromise = null;
            script?.remove();
            finish(reject, new Error('Failed to load Yandex Maps API script.'));
        };
        const timer = setTimeout(() => {
            readyPromise = null;
            script?.remove();
            finish(reject, new Error('Yandex Maps API loading timed out.'));
        }, 8000);

        if (!script) {
            script = document.createElement('script');
            script.id = 'yandex-maps-api';
            script.src = scriptUrl();
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        script.addEventListener('load', onLoad, { once: true });
        script.addEventListener('error', onError, { once: true });
    });

    return readyPromise;
}
