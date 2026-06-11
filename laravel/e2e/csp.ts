import { type Page, expect } from '@playwright/test';

/**
 * Collect Content-Security-Policy violations for the lifetime of a page.
 *
 * The app serves a strict CSP (nonce + 'strict-dynamic', no 'unsafe-inline')
 * in every non-local environment, including the e2e `testing` server. A blocked
 * script/style/handler is reported by the browser as a CSP *console error*, not
 * a `pageerror`, so the pageerror guards elsewhere cannot see it. Attach this at
 * the very start of a test (before the first navigation) to catch them.
 */
export function trackCspViolations(page: Page): string[] {
    const violations: string[] = [];
    page.on('console', (msg) => {
        const text = msg.text();
        if (
            msg.type() === 'error' &&
            (text.includes('Content Security Policy') || text.includes('Refused to'))
        ) {
            violations.push(text);
        }
    });
    return violations;
}

/** Assert no CSP violations were collected. */
export function expectNoCspViolations(violations: string[], where: string): void {
    expect(violations, `CSP violations on ${where}:\n${violations.join('\n')}`).toEqual([]);
}
