import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Boxes, BriefcaseBusiness, CircleAlert, Landmark, RefreshCw, WalletCards } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { getAssets, getDashboardCompanies, getModules, getPosRegisters, getProjects, isApiNotFound, type FixedAsset, type ModuleFlag, type OptionalList, type OptionalProject, type PosRegister } from '../lib/api';
import { useI18n } from '../lib/i18n';

export type OptionalModuleKey = ModuleFlag['module_key'];

const config: Record<OptionalModuleKey, { labelKey: string; descriptionKey: string; icon: typeof Boxes; path: string; permission: string }> = {
  pos: { labelKey: 'optional.posTitle', descriptionKey: 'optional.posDescription', icon: WalletCards, path: '/optional-modules/pos', permission: 'pos.manage' },
  projects: { labelKey: 'optional.projectsTitle', descriptionKey: 'optional.projectsDescription', icon: BriefcaseBusiness, path: '/optional-modules/projects', permission: 'projects.manage' },
  fixed_assets: { labelKey: 'optional.assetsTitle', descriptionKey: 'optional.assetsDescription', icon: Landmark, path: '/optional-modules/assets', permission: 'assets.manage' },
};

const errorText = (error?: unknown) => { void error; return 'The module status or data could not be loaded. Retry to check again.'; };

export function OptionalModulesPage() {
  const { t } = useI18n();
  const modules = useQuery({ queryKey: ['modules'], queryFn: getModules });
  const flags = modules.data?.data ?? [];
  const flagFor = (key: OptionalModuleKey) => flags.find((item) => item.module_key === key);
  const statusFor = (key: OptionalModuleKey) => modules.isLoading ? 'loading' : modules.isError ? isApiNotFound(modules.error) ? 'unavailable' : 'error' : flagFor(key)?.status === 'enabled' || flagFor(key)?.status === 'available' ? 'enabled' : flagFor(key)?.status === 'unavailable' ? 'unavailable' : 'disabled';
  const pos = useQuery({ queryKey: ['optional-overview', 'pos'], queryFn: () => getPosRegisters(), enabled: statusFor('pos') === 'enabled' });
  const projects = useQuery({ queryKey: ['optional-overview', 'projects'], queryFn: () => getProjects(), enabled: statusFor('projects') === 'enabled' });
  const assets = useQuery({ queryKey: ['optional-overview', 'fixed_assets'], queryFn: () => getAssets(), enabled: statusFor('fixed_assets') === 'enabled' });
  const refresh = () => { void modules.refetch(); void pos.refetch(); void projects.refetch(); void assets.refetch(); };
  const counts: Record<OptionalModuleKey, number | null> = { pos: pos.data?.data.total ?? null, projects: projects.data?.data.total ?? null, fixed_assets: assets.data?.data.total ?? null };
  const errors = [modules.error, pos.error, projects.error, assets.error].filter((error) => Boolean(error) && !isApiNotFound(error));
  const loading = modules.isLoading || pos.isLoading || projects.isLoading || assets.isLoading;

  return <section className="optional-page page-section">
    <header className="optional-header">
      <div><h1>{t('optional.title')}</h1><span>{t('optional.subtitle')}</span></div>
      <button className="icon-button" type="button" onClick={refresh} disabled={loading} aria-label={t('common.refresh')}><RefreshCw size={16} className={loading ? 'is-spinning' : ''} /></button>
    </header>
    {errors.length > 0 && <div className="alert alert-danger optional-alert" role="alert"><CircleAlert size={16} /> <span>{t('optional.error')} {errorText(errors[0])}</span><button className="table-action" type="button" onClick={refresh}>{t('common.retry')}</button></div>}
    <div className="optional-module-grid">
      {(Object.keys(config) as OptionalModuleKey[]).map((key) => {
        const item = config[key]; const Icon = item.icon; const baseStatus = statusFor(key); const queryError = key === 'pos' ? pos.error : key === 'projects' ? projects.error : assets.error; const status = queryError ? isApiNotFound(queryError) ? 'unavailable' : 'error' : baseStatus; const isAvailable = status === 'enabled';
        return <article className="optional-module-card" key={key}>
          <div className="optional-module-icon"><Icon size={21} /></div>
          <div className="optional-module-content"><div className="optional-module-title"><h2>{t(item.labelKey)}</h2><span className={`optional-status status-${status}`}>{queryError ? t('optional.status.error') : t(`optional.status.${status}`)}</span></div><p>{t(item.descriptionKey)}</p><small>{isAvailable && !queryError ? `${counts[key] ?? 0} ${key === 'pos' ? t('optional.registers') : key === 'projects' ? t('optional.projects') : t('optional.assets')}` : queryError ? t('optional.retryDetail') : status === 'disabled' ? t('optional.enableToOpen') : status === 'unavailable' ? t('optional.unavailableDetail') : t('optional.checking')}</small></div>
          {isAvailable && !queryError && <Link className="primary optional-open" to={item.path}>{t('optional.open')}</Link>}
          {queryError && !isApiNotFound(queryError) && <button className="secondary optional-open" type="button" onClick={refresh}>{t('common.retry')}</button>}
        </article>;
      })}
    </div>
    {!loading && flags.length === 0 && !modules.error && <div className="optional-empty">{t('optional.noConfiguration')}</div>}
  </section>;
}

type Row = OptionalProject | PosRegister | FixedAsset;

export function OptionalModuleWorkspacePage({ module }: { module: OptionalModuleKey }) {
  const { t, formatCurrency, formatDate } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['optional-companies'], queryFn: getDashboardCompanies });
  const companyRows = companies.data?.data.data ?? [];
  const companyId = searchParams.get('company_id') ?? (companyRows.length === 1 ? companyRows[0].id : '');
  const flags = useQuery({ queryKey: ['modules'], queryFn: getModules });
  const moduleFlag = flags.data?.data.find((item) => item.module_key === module);
  const status = flags.isLoading ? 'loading' : flags.isError ? isApiNotFound(flags.error) ? 'unavailable' : 'error' : moduleFlag?.status ?? (moduleFlag?.enabled ? 'unavailable' : 'disabled');
  const enabled = status === 'enabled' || status === 'available';
  const params = { company_id: companyId || undefined, page: Number(searchParams.get('page') ?? 1), per_page: 25 };
  const records = useQuery({ queryKey: ['optional-workspace', module, params], enabled: enabled, queryFn: async (): Promise<OptionalList<Row>> => {
    if (module === 'pos') return (await getPosRegisters(params)).data;
    if (module === 'projects') return (await getProjects(params)).data;
    return (await getAssets(params)).data;
  } });
  const item = config[module]; const Icon = item.icon; const rows = useMemo(() => records.data?.data ?? [], [records.data]);
  const title = t(item.labelKey);
  const isError = (flags.isError && !isApiNotFound(flags.error)) || (companies.isError && !isApiNotFound(companies.error)) || (records.isError && !isApiNotFound(records.error));
  const refresh = () => { void flags.refetch(); void companies.refetch(); void records.refetch(); };
  const setCompany = (value: string) => setSearchParams(value ? { company_id: value } : {});
  const totals = useMemo(() => {
    if (module === 'pos') return { primary: rows.length, secondary: rows.filter((row) => (row as PosRegister).status === 'active').length, label: t('optional.registers'), secondaryLabel: t('optional.activeRegisters') };
    if (module === 'projects') return { primary: rows.length, secondary: rows.filter((row) => (row as OptionalProject).state === 'active').length, label: t('optional.projects'), secondaryLabel: t('optional.activeProjects') };
    return { primary: rows.length, secondary: rows.filter((row) => (row as FixedAsset).state === 'capitalized').length, label: t('optional.assets'), secondaryLabel: t('optional.capitalizedAssets') };
  }, [module, rows, t]);

  return <section className="optional-page page-section">
    <header className="optional-header optional-workspace-header"><div><Link className="optional-back" to="/optional-modules"><ArrowLeft size={15} /> {t('optional.back')}</Link><div className="optional-title-line"><span className="optional-title-icon"><Icon size={19} /></span><h1>{title}</h1></div></div><button className="icon-button" type="button" onClick={refresh} disabled={records.isFetching || flags.isFetching} aria-label={t('common.refresh')}><RefreshCw size={16} className={records.isFetching ? 'is-spinning' : ''} /></button></header>
    <nav className="optional-tabs" aria-label={title}><Link className="active" to={item.path}>{t('optional.overview')}</Link><span>{module === 'pos' ? t('optional.registers') : module === 'projects' ? t('optional.tasks') : t('optional.depreciation')}</span><span>{module === 'pos' ? t('optional.salesHistory') : module === 'projects' ? t('optional.reports') : t('optional.reports')}</span></nav>
    {status === 'loading' && <div className="optional-empty"><strong>{t('optional.checking')}</strong></div>}
    {!enabled && status !== 'loading' && <div className="optional-empty"><CircleAlert size={20} /><strong>{t(status === 'error' ? 'optional.status.error' : status === 'unavailable' ? 'optional.unavailableTitle' : status === 'setup_required' ? 'optional.setupTitle' : 'optional.disabledTitle')}</strong><span>{status === 'error' ? t('optional.statusError') : moduleFlag?.reason ?? t(status === 'setup_required' ? 'optional.setupDetail' : status === 'unavailable' ? 'optional.unavailableDetail' : 'optional.disabledDetail')}</span>{status === 'error' && <button className="secondary" type="button" onClick={refresh}>{t('common.retry')}</button>}</div>}
    {isError && status === 'enabled' && <div className="alert alert-danger optional-alert" role="alert"><CircleAlert size={16} /><span>{t('optional.dataError')}</span><button className="table-action" type="button" onClick={refresh}>{t('common.retry')}</button></div>}
    {enabled && !isError && <>
      <div className="optional-toolbar"><label><span className="sr-only">{t('app.selectCompany')}</span><select value={companyId} onChange={(event) => setCompany(event.target.value)}><option value="">{t('app.allCompanies')}</option>{companyRows.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label><span className="optional-scope">{companyId ? t('optional.companyScope') : t('optional.authorizedScope')}</span></div>
      <div className="optional-kpi-grid"><article className="optional-kpi"><span>{t('optional.records')}</span><strong>{records.isLoading ? '…' : totals.primary}</strong><small>{totals.label}</small></article><article className="optional-kpi"><span>{t('optional.status')}</span><strong>{records.isLoading ? '…' : totals.secondary}</strong><small>{totals.secondaryLabel}</small></article><article className="optional-kpi"><span>{t('optional.apiStatus')}</span><strong>{records.isLoading ? '…' : t('optional.connected')}</strong><small>{t('optional.realRecords')}</small></article></div>
      <div className="panel optional-records"><div className="table-card-header"><h2>{t('optional.registeredRecords')}</h2><span>{records.data?.total ?? 0}</span></div>
        {records.isLoading && <div className="optional-skeleton"><i /><i /><i /></div>}
        {!records.isLoading && rows.length === 0 && <div className="optional-empty compact"><Boxes size={20} /><strong>{t('optional.noRecords')}</strong><span>{t('optional.noRecordsDetail')}</span></div>}
        {!records.isLoading && rows.length > 0 && <div className="table-scroll"><table><thead><tr>{module === 'pos' ? <><th>{t('optional.code')}</th><th>{t('optional.name')}</th><th>{t('optional.status')}</th><th>{t('optional.paymentMethods')}</th></> : module === 'projects' ? <><th>{t('optional.code')}</th><th>{t('optional.name')}</th><th>{t('optional.status')}</th><th>{t('optional.budget')}</th><th>{t('optional.dates')}</th></> : <><th>{t('optional.assetNumber')}</th><th>{t('optional.name')}</th><th>{t('optional.status')}</th><th>{t('optional.acquisitionCost')}</th><th>{t('optional.acquiredOn')}</th></>}</tr></thead><tbody>{rows.map((row) => module === 'pos' ? <tr key={row.id}><td><code>{(row as PosRegister).code}</code></td><td><strong>{(row as PosRegister).name}</strong></td><td><span className="badge">{(row as PosRegister).status}</span></td><td>{(row as PosRegister).payment_methods?.join(', ') || '—'}</td></tr> : module === 'projects' ? <tr key={row.id}><td><code>{(row as OptionalProject).code}</code></td><td><strong>{(row as OptionalProject).name}</strong></td><td><span className="badge">{(row as OptionalProject).state}</span></td><td>{formatCurrency((row as OptionalProject).budget_amount, null)}</td><td>{formatDate((row as OptionalProject).starts_on)} – {formatDate((row as OptionalProject).ends_on)}</td></tr> : <tr key={row.id}><td><code>{(row as FixedAsset).asset_number}</code></td><td><strong>{(row as FixedAsset).name}</strong></td><td><span className="badge">{(row as FixedAsset).state}</span></td><td>{formatCurrency((row as FixedAsset).acquisition_cost, null)}</td><td>{formatDate((row as FixedAsset).acquired_on)}</td></tr>)}</tbody></table></div>}
      </div>
      <p className="optional-note">{t('optional.scopeNote')}</p>
    </>}
  </section>;
}
