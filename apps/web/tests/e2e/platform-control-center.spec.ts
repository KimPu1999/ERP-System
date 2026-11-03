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

test('platform sign-in keeps the password masked and offers a single resend error', async ({ page }) => {
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }));
  await page.route('**/api/v1/auth/platform/login', (route) => route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ message: 'Email verification is required before sign-in.' }) }));
  await page.route('**/api/v1/auth/platform/verification/resend', (route) => route.fulfill({ status: 429, contentType: 'application/json', body: JSON.stringify({ message: 'Too many attempts.' }) }));
  await page.goto('/platform/login');

  const password = page.getByRole('textbox', { name: 'Password' });
  await expect(password).toHaveAttribute('type', 'password');
  await page.getByRole('button', { name: 'Show password' }).click();
  await expect(password).toHaveAttribute('type', 'text');
  await page.getByLabel('Email address').fill('unverified-platform@example.test');
  await password.fill('secret-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByRole('alert')).toHaveCount(1);
  await page.getByRole('button', { name: 'Resend verification email' }).click();
  await expect(page.getByRole('alert')).toHaveCount(1);
  await expect(page.getByRole('status')).toHaveCount(0);
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
  await page.route('**/api/v1/platform/summary**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ summary: { total_businesses: 4, active_businesses: 3, pending_approvals: 1, suspended_businesses: 0, business_administrators: 3, pending_invitations: 1, enabled_modules: 4, total_platform_users: 1, active_subscriptions: 3, system_alerts: { total: 1, pending_businesses: 1, past_due_subscriptions: 0, failed_logins_last_24h: 0 }, business_growth: [{ month: '2026-06', count: 1 }], recent_registrations: [{ id: '01tenant', name: 'Example Business', slug: 'example-business', status: 'active', created_at: '2026-06-03T00:00:00Z' }], recent_activities: [{ id: '01activity', action: 'platform.tenant.created', actor: { id: '01platform-user', name: 'Platform Admin', email: 'admin2026@example.com' }, tenant_id: '01tenant', created_at: '2026-06-03T00:00:00Z' }] } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform');

  await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
  await expect(page.getByText('Example Business')).toBeVisible();
  await expect(page.getByText('Active businesses', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('platform settings renders the authorized general settings form on mobile', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/settings/general', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { value: { display_name: 'ERP Cloud', description: '', support_email: '', support_phone: '', organization_contact: '', default_locale: 'en', default_timezone: 'Asia/Yangon', date_format: 'Y-m-d', time_format: 'H:i', application_url_display: 'http://localhost:5173' }, version: 0 } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/settings');

  await expect(page.getByRole('heading', { name: 'Platform Settings' })).toBeVisible();
  await expect(page.getByLabel('Platform display name')).toHaveValue('ERP Cloud');
  await expect(page.getByRole('button', { name: 'Save changes' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('platform analytics renders live aggregates on a narrow viewport', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/analytics/overview**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { filters: { timezone: 'Asia/Yangon' }, kpis: {}, businesses: { trend: [{ bucket: '2026-06', count: 2 }], by_status: [{ status: 'active', count: 2 }] }, subscriptions: { by_state: [{ state: 'active', count: 2 }] }, platform_users_by_status: [{ status: 'active', count: 1 }] } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/analytics');

  await expect(page.getByRole('heading', { name: 'Platform analytics' })).toBeVisible();
  await expect(page.getByText('Business status', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('platform monitoring center shows truthful stored service states on mobile', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/monitoring/overview', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { overall: 'unknown', last_successful_check: null, latest_verified_check: null, services: [{ service: 'database', status: 'healthy', last_observed_at: '2026-11-03T00:00:00Z', checks: [{ check_key: 'connectivity', status: 'healthy', observed_at: '2026-11-03T00:00:00Z', response_time_ms: 4, detail: 'PostgreSQL connectivity check completed.', metadata: {}, stale: false }] }], open_alerts: { total: 0, critical: 0, warning: 0, info: 0 }, recent_incidents: [], collector: { enabled: true, refresh_seconds: 30, last_run_at: '2026-11-03T00:00:00Z', schedule: 'every minute' }, metric_definitions: {}, limitations: { api: 'Request instrumentation is unavailable.' } } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/monitoring');

  await expect(page.getByRole('heading', { name: 'Monitoring Center' })).toBeVisible();
  await expect(page.getByText('database', { exact: true })).toBeVisible();
  await expect(page.getByText('Unknown', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('security center renders persisted posture metrics without mobile overflow', async ({ page }) => {
  await page.unroute('**/api/v1/auth/me');
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: platformUser }) }));
  await page.route('**/api/v1/platform/security/overview', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { metrics: { failed_login_attempts_24h: 2, active_sessions: 1, mfa_enrollment: { enrolled: 1, total: 1, coverage: 100 }, permission_changes_24h: 0, open_security_alerts: 0 }, posture: 'protected', posture_basis: { mfa_coverage: 'MFA coverage is complete.' }, recent_events: [], monitoring_available: true } }) }));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/security');

  await expect(page.getByRole('heading', { name: 'Security Center' })).toBeVisible();
  await expect(page.getByText('Failed logins (24h)', { exact: true })).toBeVisible();
  await expect(page.getByText('Protected', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});
