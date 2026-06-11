import { test, expect } from '@playwright/test';
import { loginViaForm, waitForSpa } from './helpers';

test.describe('Auth flows', () => {

    test.beforeEach(async ({ page }) => {
        // Clear localStorage so each test starts logged out
        await page.addInitScript(() => localStorage.removeItem('auth_token'));
    });

    // ── Login ──────────────────────────────────────────────────────────────

    test('login page renders without crashing', async ({ page }) => {
        await page.goto('/login');
        await waitForSpa(page);
        await expect(page.locator('h2')).toContainText('Войдите');
    });

    test('valid credentials log user in and redirect to home', async ({ page }) => {
        await loginViaForm(page, 'driver@truckroute.local', 'password');

        await expect(page).toHaveURL('/');
        // Authenticated nav shows profile link
        await expect(page.locator('.main-nav a[href*="profile"]')).toBeVisible();
    });

    test('invalid credentials show error message', async ({ page }) => {
        await page.goto('/login');
        await waitForSpa(page);

        await page.fill('#email', 'wrong@example.com');
        await page.fill('#password', 'badpass');
        await page.click('button[type="submit"]');

        // Error appears below form
        await expect(page.locator('form p[style*="red"]')).toBeVisible({ timeout: 5_000 });
        await expect(page).toHaveURL('/login');
    });

    test('guest is redirected from protected pages to login', async ({ page }) => {
        await page.goto('/profile');
        await expect(page).toHaveURL(/\/login/);
    });

    // ── Register ───────────────────────────────────────────────────────────

    test('register page renders', async ({ page }) => {
        await page.goto('/register');
        await waitForSpa(page);
        await expect(page.locator('h2')).toContainText('Создать аккаунт');
    });

    test('register with valid data creates account and redirects', async ({ page }) => {
        const ts = Date.now();
        await page.goto('/register');
        await waitForSpa(page);

        await page.fill('input[autocomplete="name"]',         `Тест ${ts}`);
        await page.fill('input[autocomplete="email"]',        `test${ts}@example.com`);
        await page.fill('input[autocomplete="new-password"]', 'password123');
        // Two password fields — fill both
        const pwFields = page.locator('input[type="password"]');
        await pwFields.nth(0).fill('password123');
        await pwFields.nth(1).fill('password123');

        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('/', { timeout: 8_000 });
    });

    // ── Logout ─────────────────────────────────────────────────────────────

    test('user can log out', async ({ page }) => {
        await loginViaForm(page, 'driver@truckroute.local', 'password');
        await waitForSpa(page);

        await page.locator('.nav-logout-btn').click();
        // After logout, login link should appear
        await expect(page.locator('.main-nav a', { hasText: 'войти' })).toBeVisible({ timeout: 5_000 });
    });
});
