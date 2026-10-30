import { useQuery } from '@tanstack/react-query';
import { getPlatformTenants } from '../lib/api';

export function PlatformDashboard() { const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: getPlatformTenants }); return <section><p className="eyebrow">Platform operations</p><h1>Platform Dashboard</h1><div className="grid"><div className="card"><span>Tenants</span><strong>{tenants.data?.data.total ?? '—'}</strong></div><div className="card"><span>Monitoring</span><strong><a className="text-link" href="/platform/monitoring">System health →</a></strong></div></div></section>; }
