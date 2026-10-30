import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import {
  Activity,
  ArrowLeft,
  Building2,
  Check,
  ChevronRight,
  CircleAlert,
  FileText,
  Globe2,
  Image,
  Info,
  Landmark,
  MapPin,
  Pencil,
  Phone,
  Save,
  ShieldCheck,
  Trash2,
  Upload,
  X,
} from 'lucide-react';
import {
  ApiError,
  deleteCompanyAsset,
  deleteCompanyDocument,
  downloadCompanyDocument,
  getCompany,
  getCompanyActivity,
  getCompanyDocuments,
  getDashboardCompanies,
  getAccountingCurrencies,
  updateCompany,
  uploadCompanyAsset,
  uploadCompanyDocument,
  type Company,
  type CompanyActivity,
} from '../lib/api';

type ProfileTab = 'overview' | 'legal' | 'contact' | 'localization' | 'branding' | 'documents' | 'activity';

type FormValues = {
  legal_name: string;
  trading_name: string;
  code: string;
  business_type: string;
  industry: string;
  registration_date: string;
  registration_number: string;
  tax_identifier: string;
  tax_registration_details: string;
  description: string;
  email: string;
  phone: string;
  alternative_phone: string;
  website: string;
  country_code: string;
  region: string;
  city: string;
  township: string;
  postal_code: string;
  street_address: string;
  base_currency: string;
  timezone: string;
  default_locale: string;
  date_format: string;
  number_format: string;
  fiscal_year_start: string;
  document_number_prefix: string;
  invoice_prefix: string;
  purchase_order_prefix: string;
  brand_color: string;
};

const defaults: FormValues = {
  legal_name: '', trading_name: '', code: '', business_type: '', industry: '', registration_date: '',
  registration_number: '', tax_identifier: '', tax_registration_details: '', description: '', email: '',
  phone: '', alternative_phone: '', website: '', country_code: '', region: '', city: '', township: '',
  postal_code: '', street_address: '', base_currency: '', timezone: 'Asia/Yangon', default_locale: 'en',
  date_format: 'YYYY-MM-DD', number_format: '1,234.56', fiscal_year_start: '', document_number_prefix: '',
  invoice_prefix: '', purchase_order_prefix: '', brand_color: '#2563EB',
};

const formValues = (company: Company): FormValues => ({
  legal_name: company.legal_name ?? company.name ?? '', trading_name: company.trading_name ?? '', code: company.code ?? '',
  business_type: company.business_type ?? '', industry: company.industry ?? '', registration_date: company.registration_date?.slice(0, 10) ?? '',
  registration_number: company.registration_number ?? '', tax_identifier: company.tax_identifier ?? '', tax_registration_details: company.tax_registration_details ?? '', description: company.description ?? '',
  email: company.email ?? '', phone: company.phone ?? '', alternative_phone: company.alternative_phone ?? '', website: company.website ?? '',
  country_code: company.country_code ?? '', region: company.region ?? '', city: company.city ?? '', township: company.township ?? '', postal_code: company.postal_code ?? '', street_address: company.street_address ?? '',
  base_currency: company.base_currency ?? '', timezone: company.timezone ?? defaults.timezone, default_locale: company.default_locale ?? defaults.default_locale, date_format: company.date_format ?? defaults.date_format,
  number_format: company.number_format ?? defaults.number_format, fiscal_year_start: company.fiscal_year_start?.slice(0, 10) ?? '', document_number_prefix: company.document_number_prefix ?? '',
  invoice_prefix: company.invoice_prefix ?? '', purchase_order_prefix: company.purchase_order_prefix ?? '', brand_color: company.brand_color ?? defaults.brand_color,
});

const tabs: Array<[ProfileTab, string, typeof Building2]> = [
  ['overview', 'Overview', Building2], ['legal', 'Business & Legal', Landmark], ['contact', 'Contact & Address', MapPin],
  ['localization', 'Localization', Globe2], ['branding', 'Branding', Image], ['documents', 'Documents', FileText], ['activity', 'Activity History', Activity],
];

const tabFromQuery = (value: string | null): ProfileTab => tabs.some(([key]) => key === value) ? value as ProfileTab : 'overview';
const display = (value: unknown): string => value === null || value === undefined || value === '' ? 'Not provided' : String(value);
const statusLabel = (active: boolean): string => active ? 'Active' : 'Inactive';

function ProfileField({ label, required, hint, error, children, wide = false }: { label: string; required?: boolean; hint?: string; error?: string; children: ReactNode; wide?: boolean }) {
  return <label className={wide ? 'profile-field profile-field-wide' : 'profile-field'}><span>{label} {required ? <b className="required-mark">*</b> : <small className="optional-label">Optional</small>}</span>{children}{hint && <small className="field-hint">{hint}</small>}{error && <small className="error-text">{error}</small>}</label>;
}

function SummaryCard({ eyebrow, title, icon: Icon, children }: { eyebrow: string; title: string; icon: typeof Building2; children: ReactNode }) {
  return <article className="card profile-summary-card"><div className="profile-card-heading"><span className="profile-card-icon"><Icon size={16} /></span><div><p className="eyebrow">{eyebrow}</p><h2>{title}</h2></div></div>{children}</article>;
}

function DetailList({ items }: { items: Array<[string, unknown]> }) {
  return <dl className="profile-detail-list">{items.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{display(value)}</dd></div>)}</dl>;
}

function CompanyProfileEditForm({ company, onSaved, onCancel }: { company: Company; onSaved: (data: FormValues) => void; onCancel: () => void }) {
  const currencies = useQuery({ queryKey: ['accounting-currencies'], queryFn: getAccountingCurrencies });
  const { register, handleSubmit, reset, formState: { errors, isDirty } } = useForm<FormValues>({ defaultValues: formValues(company) });
  const update = useMutation({ mutationFn: (data: FormValues) => updateCompany(company.id, data), onSuccess: (result) => { reset(formValues(result.data)); onSaved(formValues(result.data)); } });
  useEffect(() => { reset(formValues(company)); }, [company, reset]);
  useEffect(() => {
    if (!isDirty) return undefined;
    const handler = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = ''; };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);
  const confirmCancel = () => { if (!isDirty || window.confirm('Discard unsaved company profile changes?')) onCancel(); };
  const message = update.error instanceof ApiError ? update.error.message : update.error?.message;
  return <form className="profile-edit-form" onSubmit={handleSubmit((data) => update.mutate(data))}>
    <fieldset className="card profile-edit-card"><div className="profile-section-heading"><div><p className="eyebrow">Company identity</p><h2>Company identity</h2></div><Building2 size={18} className="muted" /></div><div className="profile-form-grid">
      <ProfileField label="Legal company name" required error={errors.legal_name?.message}><input {...register('legal_name', { required: 'Legal company name is required' })} /></ProfileField>
      <ProfileField label="Trading name"><input {...register('trading_name')} /></ProfileField>
      <ProfileField label="Company code" required hint="Unique within this tenant." error={errors.code?.message}><input {...register('code', { required: 'Company code is required', pattern: { value: /^[A-Za-z0-9_-]+$/, message: 'Use letters, numbers, hyphens or underscores.' } })} /></ProfileField>
      <ProfileField label="Business type" required error={errors.business_type?.message}><input {...register('business_type', { required: 'Business type is required' })} placeholder="Private company, partnership…" /></ProfileField>
      <ProfileField label="Industry"><input {...register('industry')} placeholder="Retail, manufacturing…" /></ProfileField>
      <ProfileField label="Registration date"><input type="date" {...register('registration_date')} /></ProfileField>
      <ProfileField label="Registration number"><input {...register('registration_number')} /></ProfileField>
      <ProfileField label="Tax ID"><input {...register('tax_identifier')} /></ProfileField>
      <ProfileField label="Tax registration details" wide><textarea rows={3} {...register('tax_registration_details')} /></ProfileField>
      <ProfileField label="Business description" wide><textarea rows={3} {...register('description')} /></ProfileField>
    </div></fieldset>
    <fieldset className="card profile-edit-card"><div className="profile-section-heading"><div><p className="eyebrow">Contact & address</p><h2>Contact & address</h2></div><MapPin size={18} className="muted" /></div><div className="profile-form-grid">
      <ProfileField label="Company email" required error={errors.email?.message}><input type="email" {...register('email', { required: 'Company email is required', pattern: { value: /^\S+@\S+\.\S+$/, message: 'Enter a valid email address.' } })} /></ProfileField>
      <ProfileField label="Phone number" required error={errors.phone?.message}><input {...register('phone', { required: 'Phone number is required' })} /></ProfileField>
      <ProfileField label="Alternate phone"><input {...register('alternative_phone')} /></ProfileField>
      <ProfileField label="Website"><input type="url" {...register('website', { pattern: { value: /^$|https?:\/\/.+/, message: 'Use a valid URL beginning with http:// or https://.' } })} placeholder="https://example.com" /></ProfileField>
      <ProfileField label="Country code" required error={errors.country_code?.message}><input maxLength={2} {...register('country_code', { required: 'Country code is required', pattern: { value: /^[A-Za-z]{2}$/, message: 'Use a two-letter country code.' } })} placeholder="MM" /></ProfileField>
      <ProfileField label="State / region"><input {...register('region')} /></ProfileField>
      <ProfileField label="City"><input {...register('city')} /></ProfileField>
      <ProfileField label="Township"><input {...register('township')} /></ProfileField>
      <ProfileField label="Postal code"><input {...register('postal_code')} /></ProfileField>
      <ProfileField label="Street address" required error={errors.street_address?.message} wide><input {...register('street_address', { required: 'Street address is required' })} /></ProfileField>
    </div></fieldset>
    <fieldset className="card profile-edit-card"><div className="profile-section-heading"><div><p className="eyebrow">Localization & financial preferences</p><h2>Localization & financial preferences</h2></div><Globe2 size={18} className="muted" /></div><div className="profile-form-grid">
      <ProfileField label="Base accounting currency" required hint="Used for company-level financial totals. Changing it after posted journals requires an approved conversion." error={errors.base_currency?.message}><select {...register('base_currency', { required: 'Base accounting currency is required' })}><option value="">Select currency</option>{(currencies.data?.data ?? []).map((currency) => <option value={currency.code} key={currency.id}>{currency.code} — {currency.name}</option>)}</select></ProfileField>
      <ProfileField label="Timezone" required error={errors.timezone?.message}><input {...register('timezone', { required: 'Timezone is required' })} placeholder="Asia/Yangon" /></ProfileField>
      <ProfileField label="Default language" required><select {...register('default_locale', { required: true })}><option value="en">English</option><option value="my">မြန်မာ</option></select></ProfileField>
      <ProfileField label="Date format" required><select {...register('date_format', { required: true })}><option value="YYYY-MM-DD">YYYY-MM-DD</option><option value="DD/MM/YYYY">DD/MM/YYYY</option><option value="MM/DD/YYYY">MM/DD/YYYY</option></select></ProfileField>
      <ProfileField label="Number format"><select {...register('number_format')}><option value="1,234.56">1,234.56</option><option value="1.234,56">1.234,56</option><option value="1 234,56">1 234,56</option></select></ProfileField>
      <ProfileField label="Fiscal year start"><input type="date" {...register('fiscal_year_start')} /></ProfileField>
      <ProfileField label="Document prefix"><input {...register('document_number_prefix')} placeholder="DOC" /></ProfileField>
      <ProfileField label="Invoice prefix"><input {...register('invoice_prefix')} placeholder="INV" /></ProfileField>
      <ProfileField label="Purchase order prefix"><input {...register('purchase_order_prefix')} placeholder="PO" /></ProfileField>
      <ProfileField label="Brand color" hint="Choose the six-digit hex color used for company branding." error={errors.brand_color?.message}><input type="color" {...register('brand_color', { pattern: { value: /^#[0-9A-Fa-f]{6}$/, message: 'Use a valid hex color.' } })} /></ProfileField>
    </div></fieldset>
    {message && <div className="alert alert-danger" role="alert">{message}</div>}
    <div className="profile-actions profile-edit-actions"><button className="primary" type="submit" disabled={update.isPending || !isDirty}><Save size={15} />{update.isPending ? 'Saving changes…' : 'Save changes'}</button><button className="secondary" type="button" onClick={confirmCancel} disabled={update.isPending}><X size={15} />Cancel</button></div>
  </form>;
}

export function CompanyProfilePage() {
  const [params, setParams] = useSearchParams();
  const client = useQueryClient();
  const [editMode, setEditMode] = useState(params.get('edit') === '1');
  const [notice, setNotice] = useState('');
  const [assetError, setAssetError] = useState('');
  const [documentCategory, setDocumentCategory] = useState('registration_certificate');
  const companies = useQuery({ queryKey: ['dashboard-companies'], queryFn: getDashboardCompanies });
  const companyList = useMemo(() => companies.data?.data.data ?? [], [companies.data]);
  const companyId = params.get('company_id') ?? companyList[0]?.id ?? '';
  const tab = tabFromQuery(params.get('tab'));
  const profile = useQuery({ queryKey: ['company-profile', companyId], queryFn: () => getCompany(companyId), enabled: Boolean(companyId) });
  const documents = useQuery({ queryKey: ['company-documents', companyId], queryFn: () => getCompanyDocuments(companyId), enabled: Boolean(companyId) });
  const activity = useQuery({ queryKey: ['company-activity', companyId], queryFn: () => getCompanyActivity(companyId), enabled: Boolean(companyId) });
  const updateParams = (changes: Record<string, string | null>) => { const next = new URLSearchParams(params); Object.entries(changes).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key)); setParams(next, { replace: true }); };
  useEffect(() => { if (!params.get('company_id') && companyList[0]?.id) { const next = new URLSearchParams(params); next.set('company_id', companyList[0].id); setParams(next, { replace: true }); } }, [companyList, params, setParams]);
  useEffect(() => { setEditMode(params.get('edit') === '1'); }, [params]);
  const invalidateProfile = () => { client.invalidateQueries({ queryKey: ['company-profile', companyId] }); client.invalidateQueries({ queryKey: ['dashboard-companies'] }); client.invalidateQueries({ queryKey: ['companies'] }); client.invalidateQueries({ queryKey: ['organization-overview'] }); };
  const upload = useMutation({ mutationFn: ({ kind, file }: { kind: 'logo' | 'cover'; file: File }) => uploadCompanyAsset(companyId, kind, file), onSuccess: () => { setAssetError(''); setNotice('Branding updated.'); invalidateProfile(); } });
  const removeAsset = useMutation({ mutationFn: (kind: 'logo' | 'cover') => deleteCompanyAsset(companyId, kind), onSuccess: () => { setNotice('Branding removed.'); invalidateProfile(); } });
  const documentUpload = useMutation({ mutationFn: ({ category, file }: { category: string; file: File }) => uploadCompanyDocument(companyId, category, file), onSuccess: () => { setNotice('Document uploaded securely.'); client.invalidateQueries({ queryKey: ['company-documents', companyId] }); } });
  const documentDelete = useMutation({ mutationFn: (documentId: string) => deleteCompanyDocument(companyId, documentId), onSuccess: () => { setNotice('Document removed.'); client.invalidateQueries({ queryKey: ['company-documents', companyId] }); } });
  const company = profile.data?.data;
  const apiError = companies.error instanceof ApiError ? companies.error : profile.error instanceof ApiError ? profile.error : null;
  const selectCompany = (id: string) => { if (id) updateParams({ company_id: id, edit: null }); };
  const selectTab = (value: ProfileTab) => updateParams({ tab: value === 'overview' ? null : value });
  const openEdit = () => { setEditMode(true); updateParams({ edit: '1' }); setNotice(''); };
  const closeEdit = () => { setEditMode(false); updateParams({ edit: null }); };
  const handleAsset = (kind: 'logo' | 'cover', file: File) => {
    setAssetError('');
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) { setAssetError('Use a PNG, JPEG or WebP image.'); return; }
    if (file.size > 5 * 1024 * 1024) { setAssetError('Images must be 5 MB or smaller.'); return; }
    upload.mutate({ kind, file });
  };
  const handleDocument = (file: File) => {
    setAssetError('');
    if (!['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(file.type)) { setAssetError('Use a PDF, PNG, JPEG, WebP, DOC or DOCX file.'); return; }
    if (file.size > 20 * 1024 * 1024) { setAssetError('Documents must be 20 MB or smaller.'); return; }
    documentUpload.mutate({ category: documentCategory, file });
  };
  if (companies.isLoading) return <section className="page-section company-profile-page"><div className="company-profile-state card" role="status"><div className="state-spinner" /><strong>Checking company access…</strong><span>Loading your authorized organization profile.</span></div></section>;
  if (companies.isError) return <section className="page-section company-profile-page"><div className="company-profile-state card"><span className="state-icon danger"><CircleAlert size={20} /></span><h1>Company profile unavailable</h1><p>{apiError?.status === 403 ? 'Your account does not have permission to view a company profile.' : 'We could not load authorized companies. Check your connection and try again.'}</p><button className="primary" type="button" onClick={() => companies.refetch()}>Retry</button></div></section>;
  if (!companyId) {
    const hasInactive = (companies.data?.meta?.inactive_count ?? 0) > 0;
    return <section className="page-section company-profile-page"><div className="company-profile-state card"><span className="state-icon"><Building2 size={22} /></span><p className="eyebrow">Organization · Company Profile</p><h1>{hasInactive ? 'Your company is not active' : 'Set up your company'}</h1>{hasInactive && <p>A company exists in this workspace, but it is inactive or pending activation. Contact an authorized administrator for the next step.</p>}{!hasInactive && <ul className="state-checklist"><li><Check size={14} /> Company identity and legal details</li><li><Check size={14} /> Contact, address and localization</li><li><Check size={14} /> Optional branding and documents</li></ul>}<div className="profile-actions state-actions"><Link className="secondary" to="/"><ArrowLeft size={15} />Back to dashboard</Link>{!hasInactive && <Link className="primary" to="/company"><Pencil size={15} />Open company setup</Link>}</div></div></section>;
  }
  if (profile.isLoading) return <section className="page-section company-profile-page"><div className="company-profile-skeleton" aria-label="Loading company profile"><div /><div /><div /><div /></div></section>;
  if (profile.isError || !company) return <section className="page-section company-profile-page"><div className="company-profile-state card"><span className="state-icon danger"><ShieldCheck size={20} /></span><h1>{profile.error instanceof ApiError && profile.error.status === 403 ? 'Company access is not authorized' : 'Unable to load company profile'}</h1><p>{profile.error instanceof ApiError && profile.error.status === 403 ? 'Your account cannot view this company. Select another authorized company or contact an administrator.' : profile.error instanceof ApiError ? profile.error.message : 'The company profile could not be found.'}</p><div className="profile-actions state-actions"><button className="secondary" type="button" onClick={() => profile.refetch()}>Retry</button><Link className="secondary" to="/">Back to dashboard</Link></div></div></section>;
  return <section className="page-section company-profile-page">
    <div className="company-profile-heading"><div><Link className="back-link" to={`/organization?company_id=${company.id}`}><ArrowLeft size={14} />Organization overview</Link><p className="eyebrow">Organization · Company Profile</p><h1>Company profile</h1><p className="muted">Localization & financial preferences</p></div><div className="company-profile-heading-actions"><label className="company-profile-selector"><Building2 size={15} /><span className="sr-only">Select company</span><select aria-label="Select company" value={company.id} onChange={(event) => selectCompany(event.target.value)}>{companyList.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>{!editMode && <button className="primary" type="button" onClick={openEdit}><Pencil size={15} />Edit profile</button>}</div></div>
    <header className="company-profile-header card"><div className="company-profile-brand"><div className="company-profile-logo">{company.logo_url ? <img src={company.logo_url} alt="Company logo" /> : <span>{(company.trading_name || company.name).slice(0, 1).toUpperCase()}</span>}</div><div className="company-profile-brand-copy"><div className="profile-title-line"><h2>{company.trading_name || company.name}</h2><span className="status-badge active">{statusLabel(company.is_active)}</span></div><p>{company.legal_name && company.legal_name !== company.trading_name ? company.legal_name : 'Legal name not provided'} · {display(company.business_type)}</p><div className="company-profile-meta"><span>Code: <strong>{display(company.code)}</strong></span><span>Company ID: <strong>{company.id.slice(0, 8)}…</strong></span></div></div></div><div className="company-profile-cover-mini">{company.cover_image_url ? <img src={company.cover_image_url} alt="Company cover" /> : <span><Landmark size={19} /></span>}</div></header>
    {notice && <div className="alert alert-success profile-notice" role="status">{notice}</div>}
    <nav className="company-profile-tabs" aria-label="Company profile sections">{tabs.map(([key, label, Icon]) => <button className={tab === key ? 'active' : ''} type="button" aria-current={tab === key ? 'page' : undefined} key={key} onClick={() => selectTab(key)}><Icon size={15} />{label}</button>)}</nav>
    {editMode ? <CompanyProfileEditForm company={company} onSaved={(data) => { setNotice('Company profile saved.'); closeEdit(); client.setQueryData(['company-profile', companyId], { data: { ...company, ...data, name: data.legal_name || company.name } }); invalidateProfile(); }} onCancel={closeEdit} /> : <div className="company-profile-content">
      {tab === 'overview' && <div className="profile-summary-grid"><SummaryCard eyebrow="Identity" title="Company identity" icon={Building2}><DetailList items={[["Legal company name", company.legal_name || company.name], ['Trading name', company.trading_name], ['Company code', company.code], ['Business type', company.business_type], ['Industry', company.industry], ['Status', statusLabel(company.is_active)]]} /></SummaryCard><SummaryCard eyebrow="Business & legal" title="Registration details" icon={Landmark}><DetailList items={[["Registration number", company.registration_number], ['Registration date', company.registration_date], ['Tax ID', company.tax_identifier], ['Tax registration details', company.tax_registration_details], ['Description', company.description]]} /></SummaryCard><SummaryCard eyebrow="Contact" title="Contact details" icon={Phone}><DetailList items={[["Company email", company.email], ['Phone', company.phone], ['Alternate phone', company.alternative_phone], ['Website', company.website]]} /></SummaryCard><SummaryCard eyebrow="Address" title="Registered address" icon={MapPin}><DetailList items={[["Country", company.country_code], ['State / region', company.region], ['City', company.city], ['Township', company.township], ['Postal code', company.postal_code], ['Street address', company.street_address]]} /></SummaryCard></div>}
      {tab === 'legal' && <div className="profile-single-grid"><SummaryCard eyebrow="Business & legal" title="Legal identity and registration" icon={Landmark}><DetailList items={[["Legal company name", company.legal_name || company.name], ['Trading name', company.trading_name], ['Company code', company.code], ['Business type', company.business_type], ['Industry', company.industry], ['Registration number', company.registration_number], ['Registration date', company.registration_date], ['Tax ID', company.tax_identifier], ['Tax registration details', company.tax_registration_details], ['Business description', company.description]]} /></SummaryCard></div>}
      {tab === 'contact' && <div className="profile-single-grid"><SummaryCard eyebrow="Contact & address" title="How to reach the company" icon={MapPin}><DetailList items={[["Company email", company.email], ['Phone', company.phone], ['Alternate phone', company.alternative_phone], ['Website', company.website], ['Country', company.country_code], ['State / region', company.region], ['City', company.city], ['Township', company.township], ['Postal code', company.postal_code], ['Street address', company.street_address]]} /></SummaryCard></div>}
      {tab === 'localization' && <div className="profile-single-grid"><SummaryCard eyebrow="Localization & financial preferences" title="Workspace defaults" icon={Globe2}><DetailList items={[["Base currency", company.base_currency], ['Timezone', company.timezone], ['Default language', company.default_locale === 'my' ? 'မြန်မာ' : company.default_locale], ['Date format', company.date_format], ['Number format', company.number_format], ['Fiscal year start', company.fiscal_year_start], ['Document prefix', company.document_number_prefix], ['Invoice prefix', company.invoice_prefix], ['Purchase order prefix', company.purchase_order_prefix]]} /><div className="profile-info-note"><Info size={15} /><span>Base currency changes are restricted after posted financial transactions to protect accounting integrity.</span></div></SummaryCard></div>}
      {tab === 'branding' && <BrandingPanel company={company} editMode={editMode} handleAsset={handleAsset} removeAsset={removeAsset.mutate} uploadPending={upload.isPending || removeAsset.isPending} error={assetError || (upload.error instanceof ApiError ? upload.error.message : '')} />}
      {tab === 'documents' && <DocumentsPanel documents={documents.data?.data ?? []} category={documentCategory} setCategory={setDocumentCategory} onUpload={handleDocument} onView={(document) => { downloadCompanyDocument(companyId, document.id, document.name).catch((error: unknown) => setAssetError(error instanceof ApiError ? error.message : 'Unable to download the document.')); }} onDelete={(id) => { if (window.confirm('Remove this company document?')) documentDelete.mutate(id); }} loading={documents.isLoading} uploading={documentUpload.isPending} deleting={documentDelete.isPending} error={assetError || (documentUpload.error instanceof ApiError ? documentUpload.error.message : '')} />}
      {tab === 'activity' && <ActivityPanel activity={activity.data?.data ?? []} loading={activity.isLoading} error={activity.error instanceof ApiError ? activity.error.message : ''} />}
    </div>}
  </section>;
}

function BrandingPanel({ company, handleAsset, removeAsset, uploadPending, error }: { company: Company; editMode: boolean; handleAsset: (kind: 'logo' | 'cover', file: File) => void; removeAsset: (kind: 'logo' | 'cover') => void; uploadPending: boolean; error: string }) {
  return <div className="profile-branding-grid"><article className="card profile-branding-card"><div className="profile-section-heading"><div><p className="eyebrow">Workspace identity</p><h2>Company logo</h2></div><Image size={18} className="muted" /></div><div className="profile-brand-preview">{company.logo_url ? <img src={company.logo_url} alt="Company logo preview" /> : <span>{(company.trading_name || company.name).slice(0, 1).toUpperCase()}</span>}<div><strong>{company.trading_name || company.name}</strong><small>Shown in the workspace and company documents.</small></div></div><div className="profile-upload-actions"><label className="secondary file-button"><Upload size={14} />{company.logo_url ? 'Replace logo' : 'Upload logo'}<input type="file" accept="image/png,image/jpeg,image/webp" disabled={uploadPending} onChange={(event) => { const file = event.target.files?.[0]; if (file) handleAsset('logo', file); event.currentTarget.value = ''; }} /></label>{company.logo_url && <button className="ghost-button" type="button" disabled={uploadPending} onClick={() => removeAsset('logo')}><Trash2 size={14} />Remove</button>}</div></article><article className="card profile-branding-card"><div className="profile-section-heading"><div><p className="eyebrow">Document branding</p><h2>Cover image & preview</h2></div><Image size={18} className="muted" /></div>{company.cover_image_url ? <img className="profile-cover-preview" src={company.cover_image_url} alt="Company cover preview" /> : <div className="profile-cover-empty"><Image size={20} /><span>No cover image configured.</span></div>}<div className="profile-upload-actions"><label className="secondary file-button"><Upload size={14} />{company.cover_image_url ? 'Replace cover' : 'Upload cover'}<input type="file" accept="image/png,image/jpeg,image/webp" disabled={uploadPending} onChange={(event) => { const file = event.target.files?.[0]; if (file) handleAsset('cover', file); event.currentTarget.value = ''; }} /></label>{company.cover_image_url && <button className="ghost-button" type="button" disabled={uploadPending} onClick={() => removeAsset('cover')}><Trash2 size={14} />Remove</button>}</div></article>{error && <div className="alert alert-danger profile-branding-error" role="alert">{error}</div>}</div>;
}

function DocumentsPanel({ documents, category, setCategory, onUpload, onView, onDelete, loading, uploading, deleting, error }: { documents: Array<{ id: string; name: string; category: string; mime_type: string; size: number; created_at: string; uploaded_by_name?: string | null; download_path: string }>; category: string; setCategory: (value: string) => void; onUpload: (file: File) => void; onView: (document: { id: string; name: string }) => void; onDelete: (id: string) => void; loading: boolean; uploading: boolean; deleting: boolean; error: string }) {
  return <div className="profile-single-grid"><article className="card profile-documents-card"><div className="profile-section-heading"><div><p className="eyebrow">Authorized documents</p><h2>Company documents</h2><p className="muted">Sensitive files are stored privately. Downloads are checked by company permission.</p></div><ShieldCheck size={18} className="muted" /></div><div className="profile-document-upload"><select aria-label="Document type" value={category} onChange={(event) => setCategory(event.target.value)}><option value="registration_certificate">Business registration certificate</option><option value="tax_document">Tax registration certificate</option><option value="other">Other company document</option></select><label className="primary file-button"><Upload size={14} />{uploading ? 'Uploading…' : 'Upload document'}<input type="file" accept="application/pdf,image/png,image/jpeg,image/webp,.doc,.docx" disabled={uploading} onChange={(event) => { const file = event.target.files?.[0]; if (file) onUpload(file); event.currentTarget.value = ''; }} /></label></div>{error && <div className="alert alert-danger" role="alert">{error}</div>}{loading ? <div className="panel-loading">Loading documents…</div> : documents.length === 0 ? <div className="panel-empty"><FileText size={20} /><p>No company documents uploaded yet.</p><span>Add registration or tax documents when ready.</span></div> : <div className="profile-document-list">{documents.map((document) => <div className="profile-document-row" key={document.id}><span className="profile-document-icon"><FileText size={16} /></span><div><strong>{document.name}</strong><small>{document.category.replaceAll('_', ' ')} · {Math.ceil(document.size / 1024)} KB · {document.uploaded_by_name || 'Authorized user'} · {new Date(document.created_at).toLocaleDateString()}</small></div><div className="profile-document-actions"><button className="text-link" type="button" onClick={() => onView(document)}><ChevronRight size={14} />View</button><button className="ghost-button" type="button" disabled={deleting} onClick={() => onDelete(document.id)}><Trash2 size={14} /></button></div></div>)}</div>}</article></div>;
}

function ActivityPanel({ activity, loading, error }: { activity: CompanyActivity[]; loading: boolean; error: string }) {
  return <div className="profile-single-grid"><article className="card profile-activity-card"><div className="profile-section-heading"><div><p className="eyebrow">Audit trail</p><h2>Recent profile activity</h2><p className="muted">Authorized changes and sensitive document actions for this company.</p></div><Activity size={18} className="muted" /></div>{error && <div className="alert alert-danger" role="alert">{error}</div>}{loading ? <div className="panel-loading">Loading activity…</div> : activity.length === 0 ? <div className="panel-empty"><Activity size={20} /><p>No company activity recorded yet.</p><span>Updates will appear here after authorized changes.</span></div> : <div className="profile-activity-list">{activity.map((item) => <div className="profile-activity-row" key={item.id}><span className="profile-activity-dot" /><div><strong>{item.action.replaceAll('.', ' ')}</strong><small>{item.actor_name} · {new Date(item.created_at).toLocaleString()}</small></div></div>)}</div>}</article></div>;
}
