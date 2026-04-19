import { test, expect } from '@playwright/test';
import { adminCreds, login } from './helpers/auth';

test.describe('Agenda page', () => {
  test('renders the agenda page with header and filters', async ({ page }) => {
    await page.goto('/agenda');
    await expect(page.locator('.AgendaPage-title')).toHaveText(/agenda/i);
    await expect(page.locator('.AgendaFilters, [class*="Agenda"]').first()).toBeVisible();
  });

  test('shows a Post Event button for logged-in users', async ({ page }) => {
    const { username, password } = adminCreds();
    await login(page, username, password);
    await page.goto('/agenda');
    await expect(page.getByRole('button', { name: /post an event|poster un/i })).toBeVisible();
  });

  test('opens the event composer via /agenda/new with prefill', async ({ page }) => {
    const { username, password } = adminCreds();
    await login(page, username, password);
    await page.goto('/agenda/new?title=Concert%20E2E&ville=Lyon&lieu=Le%20P%C3%A9riscope');
    const composer = page.locator('#composer.EventComposer, .EventComposer').first();
    await expect(composer).toBeVisible({ timeout: 10_000 });
    await expect(composer.locator('input[value="Concert E2E"]')).toBeVisible();
    await expect(composer.locator('input[value="Lyon"]')).toBeVisible();
    await expect(composer.locator('input[value="Le Périscope"]')).toBeVisible();
  });

  test('creates an event and redirects to the discussion URL', async ({ page }) => {
    const { username, password } = adminCreds();
    await login(page, username, password);

    const title = `E2E Concert ${Date.now()}`;
    const today = new Date();
    const in30Days = new Date(today.getTime() + 30 * 86_400_000);
    const date = in30Days.toISOString().slice(0, 10);

    await page.goto(
      `/agenda/new?title=${encodeURIComponent(title)}&date=${date}&ville=Lyon&lieu=Le%20P%C3%A9riscope`
    );

    const composer = page.locator('#composer.EventComposer').first();
    await expect(composer).toBeVisible({ timeout: 10_000 });

    await composer.locator('.TextEditor textarea').fill('Created by Playwright E2E.');
    await composer.getByRole('button', { name: /post|publier/i }).click();

    await page.waitForURL(/\/d\//, { timeout: 15_000 });
    await expect(page.locator('.DiscussionHero h2, .Hero-title')).toContainText(title);
  });

  test('rejects submission without a date (server-side validation)', async ({ page }) => {
    const { username, password } = adminCreds();
    await login(page, username, password);
    await page.goto('/agenda/new?title=No%20Date%20Event');

    const composer = page.locator('#composer.EventComposer').first();
    await expect(composer).toBeVisible({ timeout: 10_000 });

    await composer.locator('input[type="date"]').fill('');
    await composer.locator('.TextEditor textarea').fill('Missing date.');
    await composer.getByRole('button', { name: /post|publier/i }).click();

    await expect(page.locator('.Alert--error, .Alerts')).toContainText(/date/i, { timeout: 5_000 });
  });
});
