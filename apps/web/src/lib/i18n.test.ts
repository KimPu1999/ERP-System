import { describe, expect, it } from 'vitest';
import { formatCurrency, formatNumber, translate } from './i18n';

describe('ERP localization', () => {
  it('returns Burmese translations without changing stable identifiers', () => {
    expect(translate('nav.inventory', 'my-MM')).toBe('ကုန်လက်ကျန်');
    expect(translate('inventory.sku', 'my-MM')).toBe('SKU');
  });

  it('falls back to English for missing Burmese keys', () => {
    expect(translate('common.refresh', 'my-MM')).toBe('ပြန်လည်ရယူရန်');
    expect(translate('not-a-real-key', 'my-MM', 'Fallback')).toBe('Fallback');
  });

  it('formats values by locale without changing numeric meaning', () => {
    expect(formatNumber('1234.5', 'en')).toContain('1,234.5');
    expect(formatCurrency('1000', 'USD', 'en')).toContain('$1,000.00');
  });
});
