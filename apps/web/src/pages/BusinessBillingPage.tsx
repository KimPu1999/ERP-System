import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { getBusinessBilling } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function BusinessBillingPage() {
  const { t, formatDate } = useI18n();
  const billing = useQuery({ queryKey: ['business-billing'], queryFn: () => getBusinessBilling({ per_page: 20 }) });
  const data = billing.data?.data;
  if (billing.isLoading) return <section className="page-section"><div className="panel" role="status">{t('common.loading')}</div></section>;
  if (billing.isError || !data) return <section className="page-section"><div className="alert alert-danger" role="alert">{t('business.billingError' as never, 'Unable to load billing records.')}</div></section>;

  return <section className="page-section" aria-labelledby="business-billing-title">
    <div className="page-header"><div><p className="eyebrow">{t('business.billingEyebrow' as never, 'Self-service')}</p><h1 id="business-billing-title">{t('business.billingTitle' as never, 'Billing')}</h1><p className="muted">{t('business.billingSubtitle' as never, 'Tenant-scoped invoices and payment history.')}</p></div><Link className="button button-secondary" to="/business/account">{t('business.backToAccount' as never, 'Back to account')}</Link></div>
    <div className="panel"><div className="table-card-heading"><h2>{t('business.invoices' as never, 'Invoices')}</h2><span className="muted">{data.invoices.total}</span></div>{data.invoices.data.length === 0 ? <p className="muted">{t('business.noInvoices' as never, 'No invoices are available.')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('business.invoiceNumber' as never, 'Invoice')}</th><th>{t('business.issued' as never, 'Issued')}</th><th>{t('business.due' as never, 'Due')}</th><th>{t('business.status' as never, 'Status')}</th><th>{t('business.total' as never, 'Total')}</th></tr></thead><tbody>{data.invoices.data.map((invoice) => <tr key={invoice.id}><td><strong>{invoice.number}</strong></td><td>{formatDate(invoice.issued_on, { dateStyle: 'short' })}</td><td>{formatDate(invoice.due_on, { dateStyle: 'short' })}</td><td><span className="status-badge">{invoice.status}</span></td><td>{invoice.currency_code} {invoice.total}</td></tr>)}</tbody></table></div>}</div>
    <div className="panel"><div className="table-card-heading"><h2>{t('business.payments' as never, 'Payments')}</h2><span className="muted">{data.payments.total}</span></div>{data.payments.data.length === 0 ? <p className="muted">{t('business.noPayments' as never, 'No payments are available.')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('business.invoiceNumber' as never, 'Invoice')}</th><th>{t('business.provider' as never, 'Provider')}</th><th>{t('business.status' as never, 'Status')}</th><th>{t('business.amount' as never, 'Amount')}</th><th>{t('business.verified' as never, 'Verified')}</th></tr></thead><tbody>{data.payments.data.map((payment) => <tr key={payment.id}><td>{payment.invoice_number ?? '—'}</td><td>{payment.provider}</td><td>{payment.status}</td><td>{payment.currency_code} {payment.amount}</td><td>{payment.verified_at ? formatDate(payment.verified_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td></tr>)}</tbody></table></div>}</div>
  </section>;
}
