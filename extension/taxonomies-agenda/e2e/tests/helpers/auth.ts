import type { Page } from '@playwright/test';

export async function login(page: Page, username: string, password: string) {
  await page.goto('/');
  await page.getByRole('button', { name: /log in/i }).first().click();
  await page.getByPlaceholder(/username|email/i).fill(username);
  await page.getByPlaceholder(/password/i).fill(password);
  await page.getByRole('button', { name: /log in/i }).last().click();
  await page.waitForLoadState('networkidle');
}

export function adminCreds() {
  return {
    username: process.env.AGENDA_ADMIN_USERNAME || 'admin',
    password: process.env.AGENDA_ADMIN_PASSWORD || 'password',
  };
}
