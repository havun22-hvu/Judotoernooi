import { type Page, type Locator, expect } from '@playwright/test';

/**
 * Page Object for the public landing page (route name: `home`).
 *
 * Keep selectors here so specs stay readable and a markup change only needs a
 * single update. Prefer role/text based locators over brittle CSS classes.
 */
export class HomePage {
    readonly page: Page;
    readonly heroHeading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heroHeading = page.locator('h1').first();
    }

    async goto(): Promise<void> {
        await this.page.goto('/');
    }

    async expectLoaded(): Promise<void> {
        await expect(this.page).toHaveTitle(/JudoToernooi/i);
        await expect(this.heroHeading).toBeVisible();
    }
}
