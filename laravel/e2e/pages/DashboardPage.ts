import { type Page, type Locator, expect } from '@playwright/test';
import { dashboardUrl, TOERNOOI_SLUG } from '../fixtures';

/** Organisator dashboard (route name: `organisator.dashboard`). */
export class DashboardPage {
    readonly page: Page;
    readonly heading: Locator;

    constructor(page: Page) {
        this.page = page;
        this.heading = page.locator('h1, h2').first();
    }

    async goto(): Promise<void> {
        await this.page.goto(dashboardUrl());
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
