import { expect, test } from '@playwright/test';
import { loginViaForm, waitForSpa } from './helpers';

test.describe('Admin dictionaries', () => {
    test('admin can open dictionaries and see configured values', async ({ page }) => {
        await loginViaForm(page, 'admin@truckroute.local', 'password');
        await page.goto('/admin');
        await waitForSpa(page);

        await page.getByRole('button', { name: 'Справочники' }).click();

        await expect(page.locator('.dictionary-layout')).toBeVisible();
        await expect(page.locator('.dictionary-main h2')).toHaveText('Типы транспорта');
        await expect(page.locator('.dictionary-table')).toContainText('Тягач + полуприцеп');
        await expect(page.locator('.dictionary-form input[placeholder="Новое значение"]')).toBeVisible();
    });
});
