import { useQuery } from '@tanstack/react-query';
import { api } from '../lib/api';

type Plan = { id: string; code: string; name: string; monthly_price: string; annual_price: string };
export function PlatformControlPage() {
  const plans = useQuery({ queryKey: ['platform-plans'], queryFn: () => api<{ data: Plan[] }>('/platform/billing/plans') });
  const revenue = useQuery({ queryKey: ['platform-revenue'], queryFn: () => api<{ data: { invoiced: string; paid: string; past_due: string } }>('/platform/billing/revenue') });
  const error = (plans.error ?? revenue.error) as Error | null;
  return <section><p className="eyebrow">Platform control</p><h1>Subscriptions & revenue</h1><p className="muted">Platform billing is separate from tenant accounting. Card data remains with the configured provider.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid"><div className="metric"><span>Invoiced</span><strong>{revenue.data?.data.invoiced ?? '—'}</strong></div><div className="metric"><span>Paid</span><strong>{revenue.data?.data.paid ?? '—'}</strong></div><div className="metric"><span>Past due</span><strong>{revenue.data?.data.past_due ?? '—'}</strong></div></div><div className="panel"><h2>Subscription plans</h2><table><thead><tr><th>Code</th><th>Name</th><th>Monthly</th><th>Annual</th></tr></thead><tbody>{plans.data?.data.map((plan) => <tr key={plan.id}><td>{plan.code}</td><td>{plan.name}</td><td>{plan.monthly_price}</td><td>{plan.annual_price}</td></tr>)}</tbody></table></div></section>;
}
