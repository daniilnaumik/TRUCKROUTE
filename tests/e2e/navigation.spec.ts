import { test, expect } from '@playwright/test';
import { loginViaForm, waitForSpa } from './helpers';

test.describe('Navigation', () => {

    test('home page loads with correct heading', async ({ page }) => {
        await page.goto('/');
        await waitForSpa(page);
        await expect(page.locator('.hero h1')).toContainText('TruckRoute');
    });

    test('news page shows events section', async ({ page }) => {
        await page.goto('/news');
        await waitForSpa(page);
        await expect(page.locator('h1')).toContainText('Новости');
        // Section heading
        await expect(page.locator('#events h2')).toBeVisible();
    });

    test('places page shows filters and grid', async ({ page }) => {
        await page.goto('/places');
        await waitForSpa(page);
        await expect(page.locator('h1')).toContainText('Объекты');
        // Type filter select exists
        await expect(page.locator('select')).toBeVisible();
    });

    test('news map initialises (Leaflet container present)', async ({ page }) => {
        await page.goto('/news');
        await waitForSpa(page);
        await page.waitForTimeout(500); // allow Leaflet to init
        await expect(page.locator('.leaflet-container')).toBeVisible();
    });

    test('nav contains logo link to home', async ({ page }) => {
        await page.goto('/news');
        await waitForSpa(page);
        await page.locator('.logo').click();
        await expect(page).toHaveURL('/');
    });

    test('theme toggle switches between light and dark', async ({ page }) => {
        await page.goto('/');
        await waitForSpa(page);

        // Default is light — html should have no data-theme or data-theme="light"
        const html = page.locator('html');
        await expect(html).not.toHaveAttribute('data-theme', 'dark');

        await page.locator('.nav-theme-toggle').click();
        await expect(html).toHaveAttribute('data-theme', 'dark');

        await page.locator('.nav-theme-toggle').click();
        await expect(html).not.toHaveAttribute('data-theme', 'dark');
    });

    test('404 page renders for unknown routes', async ({ page }) => {
        await page.goto('/does-not-exist-xyz');
        await waitForSpa(page);
        await expect(page.locator('p')).toContainText('404');
    });

    // Mobile hamburger
    test('hamburger opens nav drawer on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/');
        await waitForSpa(page);

        const hamburger = page.locator('.nav-hamburger');
        await expect(hamburger).toBeVisible();
        await hamburger.click();

        await expect(page.locator('.nav-drawer')).toBeVisible();
    });
});
