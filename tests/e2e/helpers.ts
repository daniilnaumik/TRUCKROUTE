import { Page } from '@playwright/test';

export const DEV_ACCOUNTS = {
    driver: { email: 'driver@truckroute.local', name: 'Даниил Наумик', role: 'driver' },
    admin:  { email: 'admin@truckroute.local',  name: 'Администратор',  role: 'admin'  },
} as const;

/**
 * Login via the dev switcher dropdown (only available when APP_DEBUG=true).
 * Faster than filling the login form in each test.
 */
export async function loginViaDevSwitcher(page: Page, role: 'driver' | 'admin') {
    await page.goto('/');
    // Wait for Vue SPA to boot
    await page.waitForSelector('.site-header', { timeout: 10_000 });

    const btn = page.locator('.nav-test-switcher__btn');
    await btn.click();
    await page.locator('.nav-test-switcher__item', { hasText: DEV_ACCOUNTS[role].name }).click();
    // Wait for redirect after login
    await page.waitForURL('/', { timeout: 8_000 });
}

/**
 * Login via the standard login form.
 */
export async function loginViaForm(page: Page, email: string, password = 'password') {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('/', { timeout: 8_000 });
}

/** Wait for Vue SPA to be fully mounted */
export async function waitForSpa(page: Page) {
    await page.waitForSelector('#app > *', { timeout: 10_000 });
    await page.waitForLoadState('networkidle');
}
