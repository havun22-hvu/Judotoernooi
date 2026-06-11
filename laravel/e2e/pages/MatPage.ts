import { type Page, type Locator, expect } from '@playwright/test';
import { toernooiUrl } from '../fixtures';

/**
 * Mat pages: the overview (`toernooi.mat.index`) and the mat interface
 * (`toernooi.mat.interface`). The interface is the Alpine-heavy referee screen,
 * so it is the most valuable target for the CSP "no JS errors" guard.
 */
export class MatPage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1:visible, h2:visible').first();
    }

    async gotoIndex(): Promise<void> {
        await this.page.goto(toernooiUrl('/mat'), { waitUntil: 'domcontentloaded' });
    }

    async gotoInterface(): Promise<void> {
        await this.page.goto(toernooiUrl('/mat/interface'), { waitUntil: 'domcontentloaded' });
    }

    async expectLoaded(): Promise<void> {
        await expect(this.heading).toBeVisible();
    }
}
