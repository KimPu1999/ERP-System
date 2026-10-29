import { useQuery } from '@tanstack/react-query';
import { getCustomers, getSalesInvoices, getSalesOrders, getSalesQuotations } from '../lib/api';

export function CrmSalesPage() {
  const customers = useQuery({ queryKey: ['customers'], queryFn: () => getCustomers() });
  const quotations = useQuery({ queryKey: ['sales-quotations'], queryFn: getSalesQuotations });
  const orders = useQuery({ queryKey: ['sales-orders'], queryFn: getSalesOrders });
  const invoices = useQuery({ queryKey: ['sales-invoices'], queryFn: getSalesInvoices });
  const error = [customers, quotations, orders, invoices].find((query) => query.isError)?.error as Error | undefined;
  return <section><p className="eyebrow">CRM & Sales</p><h1>Order-to-cash</h1><p className="muted">Customers, traceable sales documents and verified receivables.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid"><div className="metric"><span>Customers</span><strong>{customers.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Quotations</span><strong>{quotations.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Orders</span><strong>{orders.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Invoices</span><strong>{invoices.data?.data.data.length ?? '—'}</strong></div></div><div className="panel"><h2>Recent invoices</h2><table><thead><tr><th>Number</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead><tbody>{invoices.data?.data.data.map((invoice) => <tr key={invoice.id}><td>{invoice.number}</td><td>{invoice.customer?.name ?? '—'}</td><td><span className="badge">{invoice.state}</span></td><td>{invoice.total ?? '—'}</td></tr>)}</tbody></table>{invoices.data?.data.data.length === 0 && <div className="empty">No invoices have been created.</div>}</div></section>;
}
