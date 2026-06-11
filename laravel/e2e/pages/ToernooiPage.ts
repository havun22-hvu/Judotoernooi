import { type Page, type Locator, expect } from '@playwright/test';
import { toernooiUrl } from '../fixtures';

/** Tournament detail page (route name: `toernooi.show`). */
export class ToernooiPage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1:visible, h2:visible').first();
    }

    async goto(): Promise<void> {
        await this.page.goto(toernooiUrl(), { waitUntil: 'domcontentloaded' });
    }

    async expectLoaded(): Promise<void> {
        await expect(this.heading).toBeVisible();
        await expect(this.page.getByText('E2E Test Toernooi').first()).toBeVisible();
    }
}
