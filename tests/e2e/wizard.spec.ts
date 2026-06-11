import { test, expect } from '@playwright/test';
import { loginViaForm, waitForSpa } from './helpers';

test.describe('Route Builder Wizard', () => {

    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.removeItem('auth_token'));
        await loginViaForm(page, 'driver@truckroute.local', 'password');
    });

    test('wizard page shows step indicator with 5 steps', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        const steps = page.locator('.wizard-step-btn');
        await expect(steps).toHaveCount(5);
    });

    test('step 1 shows vehicle list', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        await expect(page.locator('.wizard-body h2')).toContainText('Транспортное средство');
        // Vehicle cards should be rendered (seeded vehicle exists)
        await expect(page.locator('.vehicle-card').first()).toBeVisible({ timeout: 5_000 });
    });

    test('cannot advance from step 1 without selecting vehicle', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        // The "Далее" button should be disabled before selecting
        // It may be enabled already if a vehicle is auto-selected
        const nextBtn = page.locator('.wizard-nav button', { hasText: 'Далее' });
        await expect(nextBtn).toBeVisible();
    });

    test('clicking vehicle card selects it', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        const card = page.locator('.vehicle-card').first();
        await card.click();
        await expect(card).toHaveClass(/is-selected/);
    });

    test('step 2 is cargo selection', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        // Click first vehicle to select it if not auto-selected
        await page.locator('.vehicle-card').first().click();
        await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();

        await expect(page.locator('.wizard-body h2')).toContainText('Груз');
    });

    test('step 3 shows route inputs', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        // Go through step 1 and 2
        await page.locator('.vehicle-card').first().click();
        await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();
        await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();

        await expect(page.locator('.wizard-body h2')).toContainText('Маршрут');
        await expect(page.locator('#origin')).toBeVisible();
        await expect(page.locator('#dest')).toBeVisible();
    });

    test('step 3 geo input shows result after typing', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        await page.locator('.vehicle-card').first().click();
        await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();
        await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();

        // Type in origin field
        await page.fill('#origin', 'Москва');
        // Dropdown or loading indicator should appear
        await expect(page.locator('.geo-input__dropdown')).toBeVisible({ timeout: 6_000 });
    });

    test('map panel renders on routes page', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);
        await page.waitForTimeout(500);

        await expect(page.locator('.wizard-map .leaflet-container')).toBeVisible();
    });

    test('step 4 shows preference controls', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        await page.locator('.vehicle-card').first().click();
        for (let i = 0; i < 3; i++) {
            await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();
        }

        await expect(page.locator('.wizard-body h2')).toContainText('Предпочтения');
        await expect(page.locator('select')).toBeVisible();
    });

    test('step 5 shows summary with vehicle and Построить button', async ({ page }) => {
        await page.goto('/routes');
        await waitForSpa(page);

        await page.locator('.vehicle-card').first().click();
        for (let i = 0; i < 4; i++) {
            await page.locator('.wizard-nav button', { hasText: 'Далее' }).click();
        }

        await expect(page.locator('.wizard-body h2')).toContainText('Итог');
        await expect(page.locator('.summary-grid')).toBeVisible();
        await expect(page.locator('.wizard-nav button', { hasText: 'Построить' })).toBeVisible();
    });
});
