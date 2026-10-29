import { useQuery } from '@tanstack/react-query';
import { getTenants } from '../lib/api';

export function PlatformDashboard() { const tenants = useQuery({ queryKey: ['tenants'], queryFn: getTenants }); return <section><p className="eyebrow">Platform operations</p><h1>Platform Dashboard</h1><p className="muted">Manage tenants, subscriptions and platform controls from one place.</p><div className="grid"><div className="card"><span>Tenants</span><strong>{tenants.data?.data.length ?? '—'}</strong></div><div className="card"><span>Monitoring</span><strong><a className="text-link" href="/platform/monitoring">System health →</a></strong></div></div></section>; }
