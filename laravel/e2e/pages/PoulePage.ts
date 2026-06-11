import { type Page, type Locator, expect } from '@playwright/test';
import { toernooiUrl } from '../fixtures';

/** Poule overview (route name: `toernooi.poule.index`). */
export class PoulePage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1:visible, h2:visible').first();
    }

    async goto(): Promise<void> {
        await this.page.goto(toernooiUrl('/poule'), { waitUntil: 'domcontentloaded' });
    }

    async expectLoaded(): Promise<void> {
        await expect(this.heading).toBeVisible();
    }
}
