import { type Page, type Locator, expect } from '@playwright/test';
import { toernooiUrl } from '../fixtures';

/** Poule overview (route name: `toernooi.poule.index`). */
export class PoulePage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1, h2').first();
    }

    async goto(): Promise<void> {
        await this.page.goto(toernooiUrl('/poule'));
    }

    async expectLoaded(): Promise<void> {
        await expect(this.heading).toBeVisible();
    }
}
