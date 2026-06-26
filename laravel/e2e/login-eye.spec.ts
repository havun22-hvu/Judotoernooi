import { test, expect } from '@playwright/test';

test('login password eye toggles visibility', async ({ page }) => {
  await page.goto('/login');
  await page.waitForLoadState('domcontentloaded');
  const pw = page.locator('#password');
  await pw.fill('geheim123');
  await expect(pw).toHaveAttribute('type', 'password');
  await page.locator('#toggle-password').click();
  await expect(pw).toHaveAttribute('type', 'text');
  await page.locator('#toggle-password').click();
  await expect(pw).toHaveAttribute('type', 'password');
});
