import { expect, test } from '@playwright/test';

const platformUser = {
  id: '01platform-user',
  name: 'Platform Admin',
  email: 'admin2026@example.com',
  status: 'active',
  email_verified_at: '2026-11-03T02:43:05.000000Z',
  mfa_enabled_at: null,
  is_platform_super_admin: true,
};

test.beforeEach(async ({ page }) => {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }));
});

test('platform sign-in is keyboard reachable and has no mobile overflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/login');

  await expect(page.getByRole('heading', { name: 'Sign in to Platform Control Center' })).toBeVisible();
  await expect(page.getByLabel('Email address')).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Password' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();

  await page.getByLabel('Email address').focus();
  await expect(page.getByRole('textbox', { name: 'Email address' })).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('textbox', { name: 'Password' })).toBeFocused();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('verified platform email result returns to platform sign-in without action overlap', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/verify-email?status=verified&mode=platform');

  await expect(page.getByRole('heading', { name: 'Email verified' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Back to sign in' })).toHaveAttribute('href', '/platform/login');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('protected platform routes redirect unauthenticated users to platform sign-in', async ({ page }) => {
  await page.goto('/platform/analytics');
  await expect(page).toHaveURL(/\/platform\/login$/);
  await expect(page.getByRole('heading', { name: 'Sign in to Platform Control Center' })).toBeVisible();
});

test('platform dashboard renders live authorized data without mobile overflow', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/summary', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ summary: { total_businesses: 4, active_businesses: 3, pending_approvals: 1, suspended_businesses: 0, business_administrators: 3, pending_invitations: 1, enabled_modules: 4, total_platform_users: 1, active_subscriptions: 3, system_alerts: { total: 1, pending_businesses: 1, past_due_subscriptions: 0, failed_logins_last_24h: 0 }, business_growth: [{ month: '2026-06', count: 1 }], recent_registrations: [{ id: '01tenant', name: 'Example Business', slug: 'example-business', status: 'active', created_at: '2026-06-03T00:00:00Z' }], recent_activities: [{ id: '01activity', action: 'platform.tenant.created', actor: { id: '01platform-user', name: 'Platform Admin', email: 'admin2026@example.com' }, tenant_id: '01tenant', created_at: '2026-06-03T00:00:00Z' }] } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform');

  await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
  await expect(page.getByText('Example Business')).toBeVisible();
  await expect(page.getByText('Active businesses', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('platform analytics renders live aggregates on a narrow viewport', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/analytics', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { period: { from: '2026-06-01T00:00:00Z', to: '2026-11-03T00:00:00Z' }, business_growth: [{ month: '2026-06', count: 2 }], businesses_by_status: [{ status: 'active', count: 2 }], subscriptions_by_state: [{ state: 'active', count: 2 }], platform_users_by_status: [{ status: 'active', count: 1 }] } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/analytics');

  await expect(page.getByRole('heading', { name: 'Platform analytics' })).toBeVisible();
  await expect(page.getByText('Business status', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});
