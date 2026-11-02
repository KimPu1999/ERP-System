import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getPlatformTenants, updatePlatformTenantModule, type ModuleKey } from '../lib/api';

const modules: Array<{ key: ModuleKey; label: string }> = [
  { key: 'pos', label: 'POS' },
  { key: 'projects', label: 'Projects' },
  { key: 'fixed_assets', label: 'Fixed Assets' },
];

export function TenantManagement() {
  const client = useQueryClient();
  const tenants = useQuery({ queryKey: ['platform-tenants'], queryFn: getPlatformTenants });
  const update = useMutation({ mutationFn: ({ tenantId, moduleKey, enabled }: { tenantId: string; moduleKey: ModuleKey; enabled: boolean }) => updatePlatformTenantModule(tenantId, moduleKey, enabled), onSuccess: () => { void client.invalidateQueries({ queryKey: ['platform-tenants'] }); } });
  const message = tenants.isError ? tenants.error.message : update.isError ? update.error.message : null;
  return <section className="platform-tenant-page">
    <p className="eyebrow">Platform operations</p><h1>Tenant Management</h1><p className="muted">Assign optional modules independently to each business tenant. Tenant administrators cannot change these controls.</p>
    {message && <div className="alert alert-danger" role="alert">{message}</div>}
    <div className="table-card"><div className="table-scroll"><table><thead><tr><th>Name</th><th>Slug</th>{modules.map((item) => <th key={item.key}>{item.label}</th>)}</tr></thead><tbody>{tenants.isLoading && <tr><td colSpan={5}>Loading tenants…</td></tr>}{tenants.data?.data.data.map((tenant) => <tr key={tenant.id}><td><strong>{tenant.name}</strong><small>{tenant.status ?? 'active'}</small></td><td>{tenant.slug}</td>{modules.map((item) => { const enabled = Boolean(tenant.modules?.find((module) => module.module_key === item.key)?.enabled); return <td key={item.key}><button className={enabled ? 'status-toggle active' : 'status-toggle'} type="button" disabled={update.isPending} aria-pressed={enabled} onClick={() => update.mutate({ tenantId: tenant.id, moduleKey: item.key, enabled: !enabled })}>{enabled ? 'Enabled' : 'Disabled'}</button></td>; })}</tr>)}{!tenants.isLoading && !tenants.data?.data.data.length && <tr><td colSpan={5}>No tenants found.</td></tr>}</tbody></table></div></div>
  </section>;
}
