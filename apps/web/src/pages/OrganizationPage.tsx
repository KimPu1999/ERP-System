import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building2, CheckCircle2, ChevronRight, CircleAlert, ClipboardList, FileText, MapPin, Network, Pencil, RefreshCw, ShieldCheck, UserPlus, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { NavLink, useLocation, useSearchParams } from 'react-router-dom';
import { ApiError, getDashboardCompanies, getMe, getOrganizationOverview, type OrganizationMetric, type OrganizationOverview } from '../lib/api';

function MetricCard({ label, metric, icon: Icon, accent, unavailableLabel }: { label: string; metric?: OrganizationMetric; icon: LucideIcon; accent: string; unavailableLabel?: string }) {
  const available = metric?.available === true;
  return <article className="metric organization-overview-metric">
    <div className={`organization-metric-top org-accent-${accent}`}><span className="organization-metric-icon"><Icon size={16} aria-hidden="true" /></span><span>{label}</span></div>
    <strong>{available ? metric?.value : unavailableLabel ?? '—'}</strong>
    {!available && <small className="organization-metric-status">{unavailableLabel ? 'Not configured' : metric?.reason ?? 'Unavailable'}</small>}
  </article>;
}

function SetupState({ inactive, onRetry }: { inactive: boolean; onRetry?: () => void }) {
  return <div className="organization-state-card card">
    <div className="organization-state-icon"><Building2 size={27} /></div>
    <p className="eyebrow">Workspace setup</p>
    <h2>{inactive ? 'Your company is inactive' : 'Set up your organization'}</h2>
    <p className="muted">{inactive ? 'A company exists in this workspace, but it is not active. Ask an authorized workspace administrator to review its status.' : 'Create your first company profile to unlock branches, departments, memberships and organization reporting.'}</p>
    <div className="organization-state-checklist"><span><CheckCircle2 size={14} /> Company identity</span><span><CheckCircle2 size={14} /> Contact and localization</span><span><CircleAlert size={14} /> Organization records after activation</span></div>
    {inactive ? <button className="secondary" type="button" onClick={onRetry}><RefreshCw size={14} /> Check again</button> : <NavLink className="primary" to="/company"><Building2 size={15} /> Create company</NavLink>}
  </div>;
}

function CompanySummary({ data, canEdit }: { data: OrganizationOverview; canEdit: boolean }) {
  const company = data.company;
  return <article className="card organization-summary organization-summary-card">
    <div className="panel-heading"><div><h2>Company summary</h2></div>{canEdit && <NavLink className="secondary compact-action" to={`/organization/profile?company_id=${company.id}`}><Pencil size={13} /> Edit profile</NavLink>}</div>
    <div className="organization-company-identity"><div className="organization-company-logo">{company.logo_url ? <img src={company.logo_url} alt={`${company.name} logo`} /> : <Building2 size={24} />}</div><div><strong>{company.trading_name || company.name}</strong><span>{company.code} <span className="status-pill success">Active</span></span></div></div>
    <dl className="summary-list organization-summary-list"><div><dt>Business type</dt><dd>{company.business_type || 'Not configured'}</dd></div><div><dt>Registration number</dt><dd>{company.registration_number || 'Not configured'}</dd></div><div><dt>Email</dt><dd>{company.email || 'Not configured'}</dd></div><div><dt>Short name / code</dt><dd>{company.code || 'Not configured'}</dd></div></dl>
  </article>;
}

function BranchPanel({ data }: { data: OrganizationOverview }) {
  return <article className="card organization-panel"><div className="panel-heading"><h2>Locations</h2><NavLink className="text-link" to={`/organization/branches?company_id=${data.company.id}`}>View all</NavLink></div>{data.branches.length ? <div className="organization-record-list">{data.branches.slice(0, 5).map((branch) => <div className="organization-record-row" key={branch.id}><span className="organization-record-icon"><MapPin size={14} /></span><div><strong>{branch.name}</strong><small>{branch.code} · {branch.manager_name || 'No manager'} · {branch.staff_count ?? 0} active staff</small></div><span className={`status-pill ${branch.is_active ? 'success' : ''}`}>{branch.is_active ? 'Active' : 'Inactive'}</span></div>)}</div> : <div className="empty-inline">No branches configured yet. Add a branch to start your operating structure.</div>}</article>;
}

function DepartmentPanel({ data }: { data: OrganizationOverview }) {
  return <article className="card organization-panel"><div className="panel-heading"><h2>Departments</h2><NavLink className="text-link" to={`/organization/departments?company_id=${data.company.id}`}>View all</NavLink></div>{data.departments.length ? <div className="organization-record-list">{data.departments.slice(0, 5).map((department) => <div className="organization-record-row" key={department.id}><span className="organization-record-icon"><Network size={14} /></span><div><strong>{department.name}</strong><small>{department.code} · {department.manager_name || 'Manager not configured'} · {department.staff_count} active staff</small></div><span className="status-pill">{department.status || 'Configured'}</span></div>)}</div> : <div className="empty-inline">No departments configured yet. Create departments after adding your company.</div>}</article>;
}

function StructurePanel({ data }: { data: OrganizationOverview }) {
  return <article className="card organization-panel organization-structure-panel"><div className="panel-heading"><h2>Organization structure</h2><NavLink className="text-link" to={`/organization/branches?company_id=${data.company.id}`}>Manage branches</NavLink></div><div className="structure-preview-root"><Building2 size={15} /><strong>{data.hierarchy.company.name}</strong></div><div className="structure-preview-children">{data.hierarchy.branches.map((branch) => <details key={branch.id} open><summary><Warehouse size={14} /> <strong>{branch.name}</strong><span>{branch.departments.length} departments</span></summary><div className="structure-department-list">{branch.departments.map((department) => <span key={department.id}><Network size={12} />{department.name}</span>)}{branch.departments.length === 0 && <small>No departments configured</small>}</div></details>)}{data.hierarchy.branches.length === 0 && <p className="muted">Add branches to build the organization structure.</p>}</div>{data.hierarchy.teams.length === 0 ? <small className="structure-unavailable">Teams are not assigned to the structure preview yet.</small> : <small className="structure-team-count">{data.hierarchy.teams.length} active teams available</small>}</article>;
}

function AccessPanel({ data }: { data: OrganizationOverview }) {
  return <article className="card organization-panel"><div className="panel-heading"><div><p className="eyebrow">Membership & access</p><h2>People and permissions</h2></div><Users size={17} className="muted" /></div><div className="access-summary-grid"><div><strong>{data.kpis.active_members.available ? data.kpis.active_members.value : '—'}</strong><span>Active members</span></div><div><strong>{data.kpis.pending_invitations.available ? data.kpis.pending_invitations.value : '—'}</strong><span>Pending invitations</span></div><div><strong>{data.kpis.suspended_members.available ? data.kpis.suspended_members.value : '—'}</strong><span>Suspended</span></div></div><p className="access-review-note">{data.kpis.access_review.reason ?? 'Access review queue is available to authorized administrators.'}</p><div className="quick-action-grid"><NavLink className="quick-action" to={`/organization/members?company_id=${data.company.id}`}>Staff & memberships <ChevronRight size={14} /></NavLink><NavLink className="quick-action" to={`/organization/members?company_id=${data.company.id}&invite=1`}>Invite member <UserPlus size={14} /></NavLink><NavLink className="quick-action" to="/roles">Roles & permissions <ShieldCheck size={14} /></NavLink></div></article>;
}

function ActivityPanel({ data }: { data: OrganizationOverview }) {
  return <article className="card organization-panel organization-activity"><div className="panel-heading"><h2>Latest changes</h2><ClipboardList size={17} className="muted" /></div>{data.recent_activity.length ? <div className="organization-record-list">{data.recent_activity.map((item) => <div className="organization-record-row activity-row" key={item.id}><span className="organization-record-icon"><FileText size={14} /></span><div><strong>{item.action.replaceAll('.', ' ')}</strong><small>{item.actor_name} · {item.entity || 'Organization record'} · {new Date(item.created_at).toLocaleString()}</small></div><span className="status-pill success">Recorded</span></div>)}</div> : <div className="empty-inline">No organization activity has been recorded yet.</div>}</article>;
}

export function OrganizationPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const location = useLocation();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'organization'], queryFn: getDashboardCompanies });
  const permissions = useQuery({ queryKey: ['me', 'organization'], queryFn: getMe, retry: false });
  const companyList = companies.data?.data.data ?? [];
  const selectedCompanyId = searchParams.get('company_id');
  const companyId = selectedCompanyId ?? companyList[0]?.id ?? '';
  const overview = useQuery({ queryKey: ['organization-overview', companyId], queryFn: () => getOrganizationOverview(companyId), enabled: Boolean(companyId) });
  const granted = permissions.data?.data.permissions ?? [];
  const allowed = (permission: string) => {
    if (permission === 'overview' || permission === 'structure') return true;
    if (permission === 'profile') return granted.includes('company.manage') || granted.includes('organization.manage');
    return granted.includes(permission) || granted.includes('organization.manage');
  };

  useEffect(() => {
    if (!selectedCompanyId && companyId) {
      const next = new URLSearchParams(searchParams);
      next.set('company_id', companyId);
      setSearchParams(next, { replace: true });
    }
  }, [companyId, searchParams, selectedCompanyId, setSearchParams]);

  const data = overview.data?.data;
  const structureOnly = location.pathname === '/organization/structure';
  const noActiveCompany = !companies.isLoading && !companies.isError && companyList.length === 0;
  const inactiveCompany = noActiveCompany && (companies.data?.meta?.inactive_count ?? 0) > 0;
  const metricCards: Array<[string, OrganizationMetric | undefined, LucideIcon, string, string?]> = data ? [
    ['Total branches', data.kpis.total_branches, Warehouse, 'blue'],
    ['Active departments', data.kpis.active_departments, Network, 'indigo'],
    ['Business units', data.kpis.business_units, Building2, 'violet', 'Coming soon'],
    ['Active members', data.kpis.active_members, Users, 'green'],
    ['Teams', data.kpis.teams, Users, 'cyan'],
    ['Pending invitations', data.kpis.pending_invitations, UserPlus, 'amber'],
  ] : [];

  return <section className="page-section organization-page organization-overview-page">
    {companies.isLoading && <div className="dashboard-loading" role="status">Loading authorized companies…</div>}
    {companies.isError && <div className="alert alert-danger" role="alert">{companies.error instanceof ApiError && companies.error.status === 403 ? 'You do not have access to an active company in this workspace. Ask an authorized administrator to grant company access.' : `Unable to load company access. ${companies.error.message}`}<button className="table-action" type="button" onClick={() => void companies.refetch()}>Retry</button></div>}
    {noActiveCompany && !companies.isError && <SetupState inactive={inactiveCompany} onRetry={() => void companies.refetch()} />}
    {overview.isLoading && companyId && <div className="dashboard-loading" role="status">Loading company overview…</div>}
    {overview.isError && companyId && <div className="alert alert-danger" role="alert">{overview.error instanceof ApiError && overview.error.status === 403 ? 'This company is inactive or you no longer have access to it.' : `Unable to load organization overview. ${overview.error.message}`}<button className="table-action" type="button" onClick={() => void overview.refetch()}>Retry</button></div>}
    {data && <>
      {structureOnly ? <div className="organization-structure-route"><StructurePanel data={data} /></div> : <>
      <div className="organization-kpi-grid organization-overview-kpi-grid">{metricCards.map(([label, metric, Icon, accent, unavailableLabel]) => <MetricCard key={label} label={label} metric={metric} icon={Icon} accent={accent} unavailableLabel={unavailableLabel} />)}</div>
      <div className="organization-overview-grid"><CompanySummary data={data} canEdit={allowed('profile')} /><AccessPanel data={data} /><BranchPanel data={data} /><DepartmentPanel data={data} /><StructurePanel data={data} /><ActivityPanel data={data} /></div>
      <article className="card organization-quick-actions"><div className="panel-heading"><h2>Quick actions</h2></div><div className="quick-action-grid organization-quick-action-grid">{allowed('profile') && <NavLink to={`/organization/profile?company_id=${data.company.id}`} className="quick-action"><Pencil size={14} /> Edit company profile <ChevronRight size={14} /></NavLink>}{allowed('organization.manage') && <NavLink to={`/organization/branches?company_id=${data.company.id}`} className="quick-action"><PlusIcon /> Add branch <ChevronRight size={14} /></NavLink>}{allowed('organization.manage') && <NavLink to={`/organization/departments?company_id=${data.company.id}`} className="quick-action"><Network size={14} /> Add department <ChevronRight size={14} /></NavLink>}{allowed('members.invite') && <NavLink to={`/organization/members?company_id=${data.company.id}&invite=1`} className="quick-action"><UserPlus size={14} /> Invite member <ChevronRight size={14} /></NavLink>}{allowed('roles.manage') && <NavLink to={`/organization/roles?company_id=${data.company.id}`} className="quick-action"><ShieldCheck size={14} /> Manage permissions <ChevronRight size={14} /></NavLink>}</div></article>
      </>}
    </>}
  </section>;
}

function PlusIcon() {
  return <span aria-hidden="true" style={{ fontSize: 16, lineHeight: 1 }}>+</span>;
}
