import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError, createCompany, getActiveTenantId, getCompanies, uploadCompanyAsset } from '../lib/api';

type Form = {
  legal_name: string;
  trading_name: string;
  code: string;
  business_type: string;
  registration_number: string;
  tax_identifier: string;
  email: string;
  phone: string;
  street_address: string;
  city: string;
  region: string;
  postal_code: string;
  country_code: string;
  base_currency: string;
  timezone: string;
  default_locale: string;
  date_format: string;
  logo?: FileList;
};

const defaults: Form = {
  legal_name: '', trading_name: '', code: '', business_type: '',
  registration_number: '', tax_identifier: '', email: '', phone: '',
  street_address: '', city: '', region: '', postal_code: '', country_code: '',
  base_currency: '', timezone: 'UTC', default_locale: 'en', date_format: 'YYYY-MM-DD',
};

export function CompanySetup() {
  const client = useQueryClient();
  const navigate = useNavigate();
  const companies = useQuery({ queryKey: ['companies', 'setup'], queryFn: getCompanies });
  const [notice, setNotice] = useState('');
  const [createdCompanyId, setCreatedCompanyId] = useState('');
  const { register, handleSubmit, reset, formState: { errors } } = useForm<Form>({ defaultValues: defaults });
  const mutation = useMutation({
    mutationFn: async (data: Form) => {
      const { logo, legal_name, ...fields } = data;
      const result = await createCompany({
        ...fields,
        name: legal_name.trim(),
        legal_name: legal_name.trim(),
        trading_name: fields.trading_name.trim() || null,
        code: fields.code.trim().toUpperCase(),
        email: fields.email.trim() || null,
        phone: fields.phone.trim() || null,
        country_code: fields.country_code.trim().toUpperCase() || null,
        base_currency: fields.base_currency.trim().toUpperCase() || null,
      });
      let logoUploadError = false;
      if (logo?.[0]) {
        try { await uploadCompanyAsset(result.data.id, 'logo', logo[0]); } catch { logoUploadError = true; }
      }
      return { result, logoUploadError };
    },
    onSuccess: ({ result, logoUploadError }) => {
      const tenantId = getActiveTenantId();
      client.invalidateQueries({ queryKey: ['companies'] });
      client.invalidateQueries({ queryKey: ['companies', 'dashboard', tenantId] });
      setCreatedCompanyId(result.data.id);
      if (logoUploadError) {
        setNotice('Company created, but the logo could not be uploaded. You can add it from Company Profile.');
        reset(defaults);
        return;
      }
      const query = new URLSearchParams({ ...(tenantId ? { tenant_id: tenantId } : {}), company_id: result.data.id });
      navigate('/?' + query.toString(), { replace: true });
    },
  });

  return <section className="page-section company-setup-page">
    <div className="page-heading"><div><p className="eyebrow">Tenant operations</p><h1>Company Setup</h1><p className="muted">Create the active company that powers your authorized dashboard workspace.</p></div><Link className="secondary" to="/">Back to dashboard</Link></div>
    {notice && <div className="alert alert-warning" role="status">{notice} {createdCompanyId && <Link className="text-link" to={'/?company_id=' + createdCompanyId}>Open dashboard</Link>}</div>}
    {mutation.isError && <div className="alert alert-danger" role="alert">{mutation.error instanceof ApiError && mutation.error.status === 403 ? 'Your account is not authorized to create a company in this workspace. Ask the workspace owner to grant company management access.' : 'Unable to create company. ' + mutation.error.message}</div>}
    <form className="profile-form" onSubmit={handleSubmit((data) => { setNotice(''); mutation.mutate(data); })} noValidate>
      <fieldset className="card profile-card"><legend>Company identity</legend><div className="form-grid">
        <label>Legal company name<input autoFocus {...register('legal_name', { required: 'Legal company name is required' })} />{errors.legal_name && <small className="error-text">{errors.legal_name.message}</small>}</label>
        <label>Trading name<input {...register('trading_name')} /></label>
        <label>Company code<input {...register('code', { required: 'Company code is required', pattern: { value: /^[A-Za-z0-9_-]+$/, message: 'Use letters, numbers, hyphens or underscores.' } })} />{errors.code && <small className="error-text">{errors.code.message}</small>}</label>
        <label>Business type<input {...register('business_type')} placeholder="Retail, services, manufacturing…" /></label>
        <label>Registration number<input {...register('registration_number')} /></label>
        <label>Tax ID<input {...register('tax_identifier')} /></label>
      </div></fieldset>
      <fieldset className="card profile-card"><legend>Contact and address</legend><div className="form-grid">
        <label>Email<input type="email" {...register('email', { pattern: { value: /^\S+@\S+\.\S+$/, message: 'Enter a valid email address.' } })} />{errors.email && <small className="error-text">{errors.email.message}</small>}</label>
        <label>Phone<input {...register('phone')} /></label>
        <label>Country code<input maxLength={2} placeholder="MM" {...register('country_code')} /></label>
        <label>State / Region<input {...register('region')} /></label>
        <label>City<input {...register('city')} /></label>
        <label>Postal code<input {...register('postal_code')} /></label>
        <label className="form-span-2">Street address<input {...register('street_address')} /></label>
      </div></fieldset>
      <fieldset className="card profile-card"><legend>Localization and branding</legend><div className="form-grid">
        <label>Base currency<input maxLength={3} placeholder="USD" {...register('base_currency')} /></label>
        <label>Timezone<input {...register('timezone', { required: 'Timezone is required' })} />{errors.timezone && <small className="error-text">{errors.timezone.message}</small>}</label>
        <label>Default language<select {...register('default_locale')}><option value="en">English</option><option value="my">မြန်မာ</option></select></label>
        <label>Date format<select {...register('date_format')}><option value="YYYY-MM-DD">YYYY-MM-DD</option><option value="DD/MM/YYYY">DD/MM/YYYY</option><option value="MM/DD/YYYY">MM/DD/YYYY</option></select></label>
        <label className="form-span-2">Company logo<input type="file" accept="image/png,image/jpeg,image/webp" {...register('logo')} /><small className="muted">Optional PNG, JPEG or WebP logo.</small></label>
      </div></fieldset>
      <div className="profile-actions"><button className="primary" type="submit" disabled={mutation.isPending}>{mutation.isPending ? 'Creating company…' : 'Create company and open dashboard'}</button><Link className="secondary" to="/">Cancel</Link></div>
    </form>
    <div className="table-card"><h2>Authorized companies</h2>{companies.isLoading && <p className="muted">Loading companies…</p>}{companies.isError && <p className="error-text">Unable to load existing companies. {companies.error.message}</p>}{!companies.isLoading && !companies.isError && companies.data?.data.data.length === 0 && <p className="muted">No active companies are available to this account yet.</p>}{companies.data?.data.data.map((company) => <div className="compact-list-row" key={company.id}><span><strong>{company.name}</strong><small>{company.code}</small></span><span className="status-pill success">Active</span></div>)}</div>
  </section>;
}
