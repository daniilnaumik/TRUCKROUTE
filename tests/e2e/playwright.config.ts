import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.ts',
    fullyParallel: false,   // Laravel dev server is single-threaded in testing
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: { timeout: 8_000 },

    use: {
        baseURL: process.env.BASE_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        locale: 'ru-RU',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile',
            use: { ...devices['Pixel 7'] },
        },
    ],
});
