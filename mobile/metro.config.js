const { getDefaultConfig } = require('expo/metro-config');
const http = require('node:http');

const config = getDefaultConfig(__dirname);
const laravelOrigin = process.env.LARAVEL_API_ORIGIN || 'http://127.0.0.1:8000';

function proxyLaravelApi(req, res) {
    const target = new URL(req.url, laravelOrigin);
    const headers = {
        ...req.headers,
        host: target.host,
    };

    delete headers.connection;

    const proxyReq = http.request(
        {
            protocol: target.protocol,
            hostname: target.hostname,
            port: target.port,
            path: `${target.pathname}${target.search}`,
            method: req.method,
            headers,
        },
        (proxyRes) => {
            res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
            proxyRes.pipe(res);
        },
    );

    proxyReq.on('error', (error) => {
        res.writeHead(502, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            message: 'Laravel API proxy error',
            detail: error.message,
            target: laravelOrigin,
        }));
    });

    req.pipe(proxyReq);
}

const existingEnhanceMiddleware = config.server?.enhanceMiddleware;

config.server = {
    ...config.server,
    enhanceMiddleware: (middleware, server) => {
        const enhancedMiddleware = existingEnhanceMiddleware
            ? existingEnhanceMiddleware(middleware, server)
            : middleware;

        return (req, res, next) => {
            if (
                req.url === '/api/v1'
                || req.url?.startsWith('/api/v1/')
                || req.url?.startsWith('/storage/')
                || req.url?.startsWith('/assets/')
            ) {
                proxyLaravelApi(req, res);
                return;
            }

            enhancedMiddleware(req, res, next);
        };
    },
};

module.exports = config;
