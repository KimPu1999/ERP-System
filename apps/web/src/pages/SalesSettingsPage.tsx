import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Save, Settings2 } from 'lucide-react';
import { ApiError, getSalesSettings, updateSalesSettings, type SalesSetting } from '../lib/api';
import { useI18n } from '../lib/i18n';

type Tab = 'general' | 'orders' | 'invoices' | 'payments' | 'returns' | 'notifications';
type FormState = Pick<SalesSetting, 'approval_required' | 'invoice_on_order' | 'invoice_on_delivery'>;
const tabs: Array<[Tab, string, boolean]> = [['general', 'General', true], ['orders', 'Sales Orders', true], ['invoices', 'Invoices', true], ['payments', 'Payments', false], ['returns', 'Returns & Refunds', false], ['notifications', 'Notifications', false]];
const errorText = (error: unknown) => error instanceof ApiError ? error.message : error instanceof Error ? error.message : 'The request could not be completed.';
const errorTitle = (error: unknown) => error instanceof ApiError && error.status === 403 ? 'You do not have permission to manage sales settings.' : error instanceof ApiError && error.status === 404 ? 'Sales settings are not available for this tenant.' : error instanceof ApiError && error.status >= 500 ? 'The sales settings service is unavailable.' : 'Unable to load sales settings.';

function Toggle({ checked, onChange, label, description }: { checked: boolean; onChange: (value: boolean) => void; label: string; description: string }) {
  return <label className="sales-setting-toggle"><span><strong>{label}</strong><small>{description}</small></span><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} /><i aria-hidden="true" /></label>;
}

export function SalesSettingsPage() {
  const { t } = useI18n();
  const client = useQueryClient();
  const [tab, setTab] = useState<Tab>('general');
  const settings = useQuery({ queryKey: ['sales-settings'], queryFn: getSalesSettings });
  const [form, setForm] = useState<FormState>({ approval_required: true, invoice_on_order: false, invoice_on_delivery: true });
  const [dirty, setDirty] = useState(false);
  const save = useMutation({ mutationFn: () => updateSalesSettings(form), onSuccess: (response) => { setForm(response.data); setDirty(false); client.setQueryData(['sales-settings'], response); } });

  useEffect(() => { if (settings.data?.data && !dirty) setForm(settings.data.data); }, [settings.data, dirty]);
  const set = (key: keyof FormState, value: boolean) => { setDirty(true); setForm((current) => ({ ...current, [key]: value })); };

  return <section className="page-section sales-settings-page"><h1 className="sr-only">{t('sales.settings.title')}</h1><div className="sales-settings-context"><div className="sales-settings-scope"><Settings2 size={15} /><span>{t('sales.settings.tenantScope')}</span></div></div>
    <nav className="sales-settings-tabs" aria-label={t('sales.settings.title')}>{tabs.map(([key, label, supported]) => <button key={key} className={tab === key ? 'active' : ''} type="button" disabled={!supported} title={!supported ? t('sales.settings.unsupported') : undefined} onClick={() => setTab(key)}>{label}{!supported && <small>Unavailable</small>}</button>)}</nav>
    {settings.isLoading && <div className="sales-settings-state card"><Settings2 size={20} /><h2>{t('sales.settings.loading')}</h2><p>{t('sales.settings.loadingDetail')}</p></div>}
    {settings.isError && <div className="sales-settings-state card"><h2>{errorTitle(settings.error)}</h2><p>{errorText(settings.error)}</p><button className="secondary" type="button" onClick={() => void settings.refetch()}>Retry</button></div>}
    {!settings.isLoading && !settings.isError && tab === 'general' && <SettingsForm title={t('sales.settings.generalTitle')} description={t('sales.settings.generalDetail')} form={form} set={set} reset={() => { if (settings.data?.data) { setForm(settings.data.data); setDirty(false); } }} save={save} dirty={dirty} t={t} />}
    {!settings.isLoading && !settings.isError && tab === 'orders' && <SettingsForm title={t('sales.settings.ordersTitle')} description={t('sales.settings.ordersDetail')} form={form} set={set} reset={() => { if (settings.data?.data) { setForm(settings.data.data); setDirty(false); } }} save={save} dirty={dirty} t={t} ordersOnly />}
    {!settings.isLoading && !settings.isError && tab === 'invoices' && <SettingsForm title={t('sales.settings.invoicesTitle')} description={t('sales.settings.invoicesDetail')} form={form} set={set} reset={() => { if (settings.data?.data) { setForm(settings.data.data); setDirty(false); } }} save={save} dirty={dirty} t={t} invoicesOnly />}
    {!settings.isLoading && !settings.isError && !tabs.find(([key]) => key === tab)?.[2] && <div className="sales-settings-state card"><Settings2 size={20} /><h2>{t('sales.settings.unsupportedTitle')}</h2><p>{t('sales.settings.unsupported')}</p></div>}
  </section>;
}

function SettingsForm({ title, description, form, set, reset, save, dirty, t, ordersOnly, invoicesOnly }: { title: string; description: string; form: FormState; set: (key: keyof FormState, value: boolean) => void; reset: () => void; save: ReturnType<typeof useMutation<{ data: SalesSetting }, Error, void>>; dirty: boolean; t: (key: never) => string; ordersOnly?: boolean; invoicesOnly?: boolean }) {
  return <article className="sales-settings-form card"><div className="sales-settings-form-heading"><div><span className="eyebrow">Sales configuration</span><h2>{title}</h2><p>{description}</p></div><div className="sales-settings-actions"><button className="secondary" type="button" disabled={!dirty || save.isPending} onClick={reset}>{t('sales.settings.reset' as never)}</button><button className="primary" type="button" disabled={!dirty || save.isPending} onClick={() => save.mutate()}><Save size={15} />{save.isPending ? t('sales.settings.saving' as never) : t('sales.settings.save' as never)}</button></div></div><div className="sales-settings-fields">{!invoicesOnly && <Toggle checked={form.approval_required} onChange={(value) => set('approval_required', value)} label={t('sales.settings.approvalRequired' as never)} description={t('sales.settings.approvalDetail' as never)} />}{!ordersOnly && <><Toggle checked={form.invoice_on_order} onChange={(value) => set('invoice_on_order', value)} label={t('sales.settings.invoiceOnOrder' as never)} description={t('sales.settings.invoiceOnOrderDetail' as never)} /><Toggle checked={form.invoice_on_delivery} onChange={(value) => set('invoice_on_delivery', value)} label={t('sales.settings.invoiceOnDelivery' as never)} description={t('sales.settings.invoiceOnDeliveryDetail' as never)} /></>}{ordersOnly && <p className="sales-settings-note">{t('sales.settings.orderNote' as never)}</p>}{invoicesOnly && <p className="sales-settings-note">{t('sales.settings.invoiceNote' as never)}</p>}</div>{save.isError && <div className="alert alert-danger">{errorText(save.error)}{save.error instanceof ApiError && Object.entries(save.error.errors).map(([key, messages]) => <div key={key}>{messages.join(' ')}</div>)}</div>}{save.isSuccess && <div className="inline-callout"><Check size={15} /> {t('sales.settings.saved' as never)}</div>}</article>;
}
