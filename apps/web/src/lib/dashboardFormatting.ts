export const formatMoney = (value: string | number, currency: string | null) => currency ? new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value) || 0) : '—';
export const formatNumber = (value: string | number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(Number(value) || 0);
