import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertCircle, Banknote, Boxes, CheckCircle2, Clock3, Plus, RefreshCw, Search, ShoppingCart, Store, X } from 'lucide-react';
import { ApiError, closePosShift, getDashboardBranches, getDashboardCompanies, getModules, getPosOverview, getPosProducts, getPosReceipts, getPosRegisters, getPosShifts, openPosShift, postPosSale, type Branch, type Company, type PosProduct, type PosReceipt, type PosRegister, type PosShift } from '../lib/api';
import { useI18n, type TranslationKey } from '../lib/i18n';

type Tab = 'overview' | 'checkout' | 'registers' | 'shifts' | 'history';
type CartLine = PosProduct & { quantity: number };

const errorText = (error: unknown) => error instanceof ApiError ? `${error.message} (HTTP ${error.status})` : error instanceof Error ? error.message : 'Unable to load POS data.';
const money = (value: string | number, currency: string | null) => `${currency ? `${currency} ` : ''}${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export function PosWorkspacePage() {
  const { t, formatDate } = useI18n();
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>('overview');
  const [companyId, setCompanyId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [registerId, setRegisterId] = useState('');
  const [shiftId, setShiftId] = useState('');
  const [search, setSearch] = useState('');
  const [cart, setCart] = useState<CartLine[]>([]);
  const [openingCash, setOpeningCash] = useState('0');
  const [closingCash, setClosingCash] = useState('0');
  const [notice, setNotice] = useState('');

  const modules = useQuery({ queryKey: ['modules'], queryFn: getModules });
  const flag = modules.data?.data.find((item) => item.module_key === 'pos');
  const status = flag?.status ?? (flag?.enabled ? 'unavailable' : 'disabled');
  const enabled = status === 'available';
  const companies = useQuery({ queryKey: ['pos-companies'], queryFn: getDashboardCompanies });
  const companyRows = companies.data?.data.data ?? [];
  const effectiveCompanyId = companyId || (companyRows.length === 1 ? companyRows[0].id : '');
  const branches = useQuery({ queryKey: ['pos-branches', effectiveCompanyId], queryFn: () => getDashboardBranches(effectiveCompanyId), enabled: Boolean(effectiveCompanyId) });
  const branchRows = useMemo(() => branches.data?.data.data ?? [], [branches.data]);
  const params = { company_id: effectiveCompanyId || undefined, branch_id: branchId || undefined, per_page: 50 };
  const registers = useQuery({ queryKey: ['pos-registers', params], queryFn: () => getPosRegisters(params), enabled: status !== 'disabled' });
  const overview = useQuery({ queryKey: ['pos-overview', params], queryFn: () => getPosOverview(params), enabled });
  const shifts = useQuery({ queryKey: ['pos-shifts', params], queryFn: () => getPosShifts({ ...params, state: tab === 'checkout' ? 'open' : undefined }), enabled });
  const products = useQuery({ queryKey: ['pos-products', params, search], queryFn: () => getPosProducts({ ...params, search: search || undefined }), enabled: enabled && tab === 'checkout' });
  const receipts = useQuery({ queryKey: ['pos-receipts', params], queryFn: () => getPosReceipts(params), enabled: enabled && tab === 'history' });
  const registerRows = useMemo(() => registers.data?.data.data ?? [], [registers.data]);
  const shiftRows = shifts.data?.data.data ?? [];
  const openShifts = shiftRows.filter((row) => row.state === 'open');
  const selectedRegister = registerRows.find((row) => row.id === registerId) ?? registerRows[0];
  const selectedShift = openShifts.find((row) => row.id === shiftId) ?? openShifts.find((row) => row.register_id === selectedRegister?.id);
  const currency = overview.data?.data.currency_code ?? null;
  const subtotal = useMemo(() => cart.reduce((sum, line) => sum + Number(line.price) * line.quantity, 0), [cart]);
  const total = subtotal;
  const refresh = () => { void modules.refetch(); void companies.refetch(); void registers.refetch(); void overview.refetch(); void shifts.refetch(); if (tab === 'checkout') void products.refetch(); if (tab === 'history') void receipts.refetch(); };

  useEffect(() => { if (!registerId && registerRows[0]) setRegisterId(registerRows[0].id); }, [registerId, registerRows]);
  useEffect(() => { if (!shiftId && selectedShift) setShiftId(selectedShift.id); }, [selectedShift, shiftId]);
  useEffect(() => { if (branchId && !branchRows.some((branch) => branch.id === branchId)) setBranchId(''); }, [branchId, branchRows]);

  const invalidate = () => { void queryClient.invalidateQueries({ queryKey: ['pos-registers'] }); void queryClient.invalidateQueries({ queryKey: ['pos-shifts'] }); void queryClient.invalidateQueries({ queryKey: ['pos-overview'] }); void queryClient.invalidateQueries({ queryKey: ['pos-receipts'] }); void queryClient.invalidateQueries({ queryKey: ['pos-products'] }); };
  const openShift = useMutation({ mutationFn: () => openPosShift(selectedRegister!.id, openingCash || '0'), onSuccess: (result) => { setShiftId(result.data.id); setNotice(t('pos.openShift')); invalidate(); }, onError: (error) => setNotice(errorText(error)) });
  const closeShift = useMutation({ mutationFn: () => closePosShift(selectedShift!.id, closingCash || '0'), onSuccess: () => { setNotice(t('pos.closeShift')); setShiftId(''); invalidate(); }, onError: (error) => setNotice(errorText(error)) });
  const sale = useMutation({ mutationFn: () => postPosSale(selectedRegister!.id, { shift_id: selectedShift!.id, idempotency_key: `pos-ui-${crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`}`, items: cart.map((line) => ({ variant_id: line.id, quantity: String(line.quantity), unit_price: line.price })), payments: [{ method: 'cash', amount: total.toFixed(6) }] }), onSuccess: () => { setCart([]); setNotice(t('pos.salePosted')); invalidate(); }, onError: (error) => setNotice(errorText(error)) });

  const addToCart = (product: PosProduct) => setCart((current) => {
    const existing = current.find((line) => line.id === product.id);
    if (existing) return current.map((line) => line.id === product.id ? { ...line, quantity: Math.min(line.quantity + 1, Number(product.available_quantity)) } : line);
    if (Number(product.available_quantity) <= 0) return current;
    return [...current, { ...product, quantity: 1 }];
  });
  const changeQuantity = (id: string, quantity: number) => setCart((current) => current.map((line) => line.id === id ? { ...line, quantity: Math.min(Math.max(quantity, 0), Number(line.available_quantity)) } : line).filter((line) => line.quantity > 0));

  const error = modules.error || companies.error || registers.error || (enabled && (overview.error || shifts.error));
  if (error) return <section className="pos-page page-section"><div className="pos-error"><AlertCircle size={20} /><strong>{t('optional.error')}</strong><span>{errorText(error)}</span><button className="secondary" type="button" onClick={refresh}>{t('common.retry')}</button></div></section>;
  if (!modules.isLoading && status !== 'available') return <section className="pos-page page-section"><header className="pos-header"><div><span className="eyebrow">{t('optional.title')}</span><h1>{t('pos.title')}</h1></div><button className="icon-button" type="button" onClick={refresh} aria-label={t('pos.refresh')}><RefreshCw size={16} /></button></header><div className="pos-setup"><Store size={24} /><div><h2>{status === 'setup_required' ? t('pos.setupTitle') : t('optional.disabledTitle')}</h2><p>{flag?.reason ?? (status === 'setup_required' ? t('pos.setupDetail') : t('optional.disabledDetail'))}</p></div></div></section>;

  return <section className="pos-page page-section">
    <header className="pos-header"><div><span className="eyebrow">{t('optional.title')}</span><h1>{t('pos.title')}</h1><p>{t('pos.subtitle')}</p></div><button className="icon-button" type="button" onClick={refresh} disabled={overview.isFetching || registers.isFetching} aria-label={t('pos.refresh')}><RefreshCw size={16} className={overview.isFetching ? 'is-spinning' : ''} /></button></header>
    <div className="pos-toolbar"><label><span>{t('pos.company')}</span><select value={effectiveCompanyId} onChange={(event) => { setCompanyId(event.target.value); setBranchId(''); }}><option value="">{t('pos.allCompanies')}</option>{companyRows.map((company: Company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label><label><span>{t('pos.branch')}</span><select value={branchId} onChange={(event) => setBranchId(event.target.value)} disabled={!effectiveCompanyId}><option value="">{t('pos.allBranches')}</option>{branchRows.map((branch: Branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label></div>
    <nav className="pos-tabs" aria-label={t('pos.title')}>{([['overview', t('pos.overview'), Store], ['checkout', t('pos.checkout'), ShoppingCart], ['registers', t('pos.registers'), Boxes], ['shifts', t('pos.shifts'), Clock3], ['history', t('pos.history'), CheckCircle2]] as const).map(([key, label, Icon]) => <button key={key} className={tab === key ? 'active' : ''} type="button" onClick={() => setTab(key)}><Icon size={15} />{label}</button>)}</nav>
    {notice && <div className={`pos-notice ${notice.includes('HTTP') || notice.includes('Unable') ? 'error' : ''}`} role="status"><span>{notice}</span><button type="button" onClick={() => setNotice('')} aria-label="Dismiss"><X size={15} /></button></div>}
    {tab === 'overview' && <div className="pos-overview"><div className="pos-kpi-grid"><article><Banknote size={17} /><span>{t('pos.todaySales')}</span><strong>{overview.isLoading ? '…' : money(overview.data?.data.today.total ?? 0, currency)}</strong><small>{currency ?? t('pos.noCurrency')}</small></article><article><CheckCircle2 size={17} /><span>{t('pos.todayReceipts')}</span><strong>{overview.isLoading ? '…' : overview.data?.data.today.sales ?? 0}</strong><small>{t('pos.receipt')}</small></article><article><Clock3 size={17} /><span>{t('pos.openShifts')}</span><strong>{overview.isLoading ? '…' : overview.data?.data.open_shifts ?? 0}</strong><small>{t('pos.shifts')}</small></article></div>{!overview.isLoading && !currency && <div className="pos-currency-warning"><AlertCircle size={16} /><span>{t('pos.currencyUnavailable')}</span></div>}<div className="pos-overview-grid"><div className="pos-panel"><div className="pos-panel-heading"><h2>{t('pos.registers')}</h2><button className="secondary" type="button" onClick={() => setTab('registers')}>{t('optional.open')}</button></div><RegisterTable rows={registerRows} t={t} /></div><div className="pos-panel"><div className="pos-panel-heading"><h2>{t('pos.shifts')}</h2><button className="secondary" type="button" onClick={() => setTab('shifts')}>{t('optional.open')}</button></div><ShiftTable rows={openShifts.slice(0, 5)} formatDate={formatDate} t={t} /></div></div></div>}
    {tab === 'checkout' && <div className="pos-checkout"><div className="pos-checkout-main"><div className="pos-checkout-toolbar"><label className="pos-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('pos.searchProducts')} /></label><label><span>{t('pos.register')}</span><select value={selectedRegister?.id ?? ''} onChange={(event) => { setRegisterId(event.target.value); setShiftId(''); }}><option value="">{t('pos.selectRegister')}</option>{registerRows.map((register) => <option key={register.id} value={register.id}>{register.code} · {register.name}</option>)}</select></label><label><span>{t('pos.shifts')}</span><select value={selectedShift?.id ?? ''} onChange={(event) => setShiftId(event.target.value)}><option value="">{t('pos.selectShift')}</option>{openShifts.filter((shift) => !selectedRegister || shift.register_id === selectedRegister.id).map((shift) => <option key={shift.id} value={shift.id}>{shift.register?.code ?? shift.register_id.slice(0, 8)} · {formatDate(shift.opened_at)}</option>)}</select></label></div>{!selectedShift && <div className="pos-inline-warning"><AlertCircle size={16} />{t('pos.stockRequired')}</div>}<div className="pos-product-grid">{products.isLoading && [1, 2, 3, 4].map((item) => <div className="pos-product-skeleton" key={item} />)}{!products.isLoading && (products.data?.data.data ?? []).map((product) => <button className="pos-product-card" type="button" key={product.id} onClick={() => addToCart(product)} disabled={Number(product.available_quantity) <= 0}><span className="pos-product-icon"><Boxes size={18} /></span><strong>{product.product_name}{product.name ? ` · ${product.name}` : ''}</strong><small>{product.sku}</small><b>{money(product.price, currency)}</b><em>{t('pos.available')}: {product.available_quantity}</em><span className="pos-add"><Plus size={14} /> {t('pos.add')}</span></button>)}{!products.isLoading && (products.data?.data.data ?? []).length === 0 && <div className="pos-empty"><Boxes size={20} /><span>{t('pos.noProducts')}</span></div>}</div></div><aside className="pos-cart"><div className="pos-panel-heading"><h2>{t('pos.cart')}</h2><span>{cart.length}</span></div>{cart.length === 0 && <div className="pos-empty compact"><ShoppingCart size={20} /><span>{t('pos.emptyCart')}</span></div>}{cart.map((line) => <div className="pos-cart-line" key={line.id}><div><strong>{line.product_name}</strong><small>{line.sku} · {money(line.price, currency)}</small></div><div className="pos-quantity"><button type="button" onClick={() => changeQuantity(line.id, line.quantity - 1)} aria-label="Decrease">−</button><span>{line.quantity}</span><button type="button" onClick={() => changeQuantity(line.id, line.quantity + 1)} aria-label="Increase">+</button></div><b>{money(Number(line.price) * line.quantity, currency)}</b></div>)}<div className="pos-cart-total"><span>{t('pos.subtotal')}</span><strong>{money(subtotal, currency)}</strong><span>{t('pos.tax')}</span><strong>{money(0, currency)}</strong><span>{t('pos.total')}</span><strong>{money(total, currency)}</strong></div><button className="primary pos-complete" type="button" disabled={!selectedRegister || !selectedShift || cart.length === 0 || sale.isPending || total <= 0} onClick={() => sale.mutate()}>{sale.isPending ? t('common.loading') : t('pos.completeSale')}</button><small className="pos-payment-note"><Banknote size={14} /> {t('pos.cashOnly')} · {t('pos.cashExact')}</small></aside></div>}
    {tab === 'registers' && <div className="pos-panel"><div className="pos-panel-heading"><h2>{t('pos.registers')}</h2><span>{registerRows.length}</span></div><RegisterTable rows={registerRows} t={t} onSelect={(id) => { setRegisterId(id); setTab('checkout'); }} /><div className="pos-action-row"><label><span>{t('pos.openingCash')}</span><input inputMode="decimal" value={openingCash} onChange={(event) => setOpeningCash(event.target.value)} /></label><button className="primary" type="button" disabled={!selectedRegister || openShifts.some((shift) => shift.register_id === selectedRegister.id) || openShift.isPending} onClick={() => openShift.mutate()}>{openShift.isPending ? t('common.loading') : t('pos.openShift')}</button></div></div>}
    {tab === 'shifts' && <div className="pos-panel"><div className="pos-panel-heading"><h2>{t('pos.shifts')}</h2><span>{shiftRows.length}</span></div><ShiftTable rows={shiftRows} formatDate={formatDate} t={t} /><div className="pos-action-row"><label><span>{t('pos.closingCash')}</span><input inputMode="decimal" value={closingCash} onChange={(event) => setClosingCash(event.target.value)} /></label><button className="secondary" type="button" disabled={!selectedShift || closeShift.isPending} onClick={() => closeShift.mutate()}>{closeShift.isPending ? t('common.loading') : t('pos.closeShift')}</button></div></div>}
    {tab === 'history' && <div className="pos-panel"><div className="pos-panel-heading"><h2>{t('pos.history')}</h2><span>{receipts.data?.data.total ?? 0}</span></div><ReceiptTable rows={receipts.data?.data.data ?? []} currency={currency} formatDate={formatDate} t={t} loading={receipts.isLoading} /></div>}
  </section>;
}

function RegisterTable({ rows, t, onSelect }: { rows: PosRegister[]; t: (key: TranslationKey) => string; onSelect?: (id: string) => void }) {
  if (!rows.length) return <div className="pos-empty compact"><Store size={20} /><span>{t('pos.noRegisters')}</span></div>;
  return <div className="table-scroll"><table className="pos-table"><thead><tr><th>{t('pos.registerCode')}</th><th>{t('optional.name')}</th><th>{t('pos.state')}</th><th>{t('optional.paymentMethods')}</th><th /></tr></thead><tbody>{rows.map((row) => <tr key={row.id}><td><code>{row.code}</code></td><td><strong>{row.name}</strong></td><td><span className="badge">{row.status}</span></td><td>{row.payment_methods?.join(', ') || 'cash'}</td><td>{onSelect && <button className="table-action" type="button" onClick={() => onSelect(row.id)}>{t('pos.checkout')}</button>}</td></tr>)}</tbody></table></div>;
}

function ShiftTable({ rows, formatDate, t }: { rows: PosShift[]; formatDate: (value?: string | null) => string; t: (key: TranslationKey) => string }) {
  if (!rows.length) return <div className="pos-empty compact"><Clock3 size={20} /><span>{t('pos.noShifts')}</span></div>;
  return <div className="table-scroll"><table className="pos-table"><thead><tr><th>{t('pos.register')}</th><th>{t('pos.state')}</th><th>{t('pos.openingCash')}</th><th>{t('pos.date')}</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id}><td><strong>{row.register?.code ?? row.register_id.slice(0, 8)}</strong></td><td><span className="badge">{row.state}</span></td><td>{row.opening_cash}</td><td>{formatDate(row.opened_at)}</td></tr>)}</tbody></table></div>;
}

function ReceiptTable({ rows, currency, formatDate, t, loading }: { rows: PosReceipt[]; currency: string | null; formatDate: (value?: string | null) => string; t: (key: TranslationKey) => string; loading: boolean }) {
  if (loading) return <div className="pos-skeleton-list"><i /><i /><i /></div>;
  if (!rows.length) return <div className="pos-empty"><CheckCircle2 size={20} /><span>{t('pos.noReceipts')}</span></div>;
  return <div className="table-scroll"><table className="pos-table"><thead><tr><th>{t('pos.receipt')}</th><th>{t('pos.register')}</th><th>{t('pos.date')}</th><th>{t('pos.amount')}</th><th>{t('pos.payment')}</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id}><td><strong>{row.number}</strong></td><td>{row.register?.code ?? row.register_id.slice(0, 8)}</td><td>{formatDate(row.created_at)}</td><td>{money(row.total, currency)}</td><td>{row.payments?.map((payment) => payment.method).join(', ') || 'cash'}</td></tr>)}</tbody></table></div>;
}
