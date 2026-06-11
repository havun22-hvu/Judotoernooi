import { type Page, type Locator, expect } from '@playwright/test';
import { dashboardUrl, TOERNOOI_SLUG } from '../fixtures';

/** Organisator dashboard (route name: `organisator.dashboard`). */
export class DashboardPage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        // :visible skips headings inside collapsed/x-cloak'd menus, which only
        // render once Alpine runs; the first *visible* heading is the content one.
        this.heading = page.locator('h1:visible, h2:visible').first();
    }

    async goto(): Promise<void> {
        // domcontentloaded, not 'load'/networkidle: these pages open a Reverb
        // WebSocket that keeps the network busy, so a full-load wait would hang.
        await this.page.goto(dashboardUrl(), { waitUntil: 'domcontentloaded' });
    }

    async expectLoaded(): Promise<void> {
        await expect(this.heading).toBeVisible();
        // The seeded tournament should surface on the dashboard.
        await expect(this.page.getByText('E2E Test Toernooi').first()).toBeVisible();
    }

    /** Link to the seeded tournament's detail page. */
    tournamentLink(): Locator {
        return this.page.locator(`a[href*="/toernooi/${TOERNOOI_SLUG}"]`).first();
    }
}
