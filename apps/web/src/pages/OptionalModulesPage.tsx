import { useQuery } from '@tanstack/react-query';
import { getAssets, getModules, getPosRegisters, getProjects } from '../lib/api';

export function OptionalModulesPage() {
  const modules = useQuery({ queryKey: ['modules'], queryFn: getModules });
  const pos = useQuery({ queryKey: ['pos-registers'], queryFn: getPosRegisters, enabled: modules.data?.data.some((module) => module.module_key === 'pos' && module.enabled) });
  const projects = useQuery({ queryKey: ['projects'], queryFn: getProjects, enabled: modules.data?.data.some((module) => module.module_key === 'projects' && module.enabled) });
  const assets = useQuery({ queryKey: ['fixed-assets'], queryFn: getAssets, enabled: modules.data?.data.some((module) => module.module_key === 'fixed_assets' && module.enabled) });
  const error = (modules.error ?? pos.error ?? projects.error ?? assets.error) as Error | null;
  return <section><p className="eyebrow">Optional modules</p><h1>POS, Projects & Fixed Assets</h1><p className="muted">Enable modules per tenant plan. Offline synchronization is intentionally not included.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid">{modules.data?.data.map((module) => <div className="metric" key={module.module_key}><span>{module.module_key.replace('_', ' ')}</span><strong>{module.enabled ? 'Enabled' : 'Disabled'}</strong></div>)}</div><div className="panel"><h2>Shared workflow surfaces</h2><table><thead><tr><th>Module</th><th>Records</th><th>Integration</th></tr></thead><tbody><tr><td>POS</td><td>{pos.data?.data.data.length ?? '—'} registers</td><td>Inventory movements + accounting event</td></tr><tr><td>Projects</td><td>{projects.data?.data.data.length ?? '—'} projects</td><td>Employees, customers and budgets</td></tr><tr><td>Fixed assets</td><td>{assets.data?.data.data.length ?? '—'} assets</td><td>Accounting event + depreciation ledger link</td></tr></tbody></table></div></section>;
}
