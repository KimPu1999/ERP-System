import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { useForm } from 'react-hook-form';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Building2, Check, CircleHelp, Globe2, ImagePlus, MapPin, Save, ShieldCheck, Upload, X } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError, createCompany, getActiveTenantId, getCompanies, uploadCompanyAsset } from '../lib/api';

type Form = {
  legal_name: string;
  trading_name: string;
  code: string;
  business_type: string;
  industry: string;
  registration_number: string;
  tax_identifier: string;
  registration_date: string;
  description: string;
  email: string;
  phone: string;
  alternative_phone: string;
  website: string;
  street_address: string;
  city: string;
  township: string;
  region: string;
  postal_code: string;
  country_code: string;
  base_currency: string;
  timezone: string;
  default_locale: string;
  date_format: string;
  fiscal_year_start: string;
  document_number_prefix: string;
  logo?: FileList;
};

const DRAFT_KEY = 'erp_cloud.company_setup_draft';
const ACCEPTED_LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
const MAX_LOGO_SIZE = 5 * 1024 * 1024;

const defaults: Form = {
  legal_name: '', trading_name: '', code: '', business_type: '', industry: '',
  registration_number: '', tax_identifier: '', registration_date: '', description: '',
  email: '', phone: '', alternative_phone: '', website: '', street_address: '',
  city: '', township: '', region: '', postal_code: '', country_code: '',
  base_currency: '', timezone: 'Asia/Yangon', default_locale: 'en', date_format: 'YYYY-MM-DD',
  fiscal_year_start: '', document_number_prefix: '',
};

function loadDraft(): Partial<Form> {
  if (typeof window === 'undefined') return {};
  try {
    const saved = window.localStorage.getItem(DRAFT_KEY);
    return saved ? JSON.parse(saved) as Partial<Form> : {};
  } catch {
    return {};
  }
}

export function CompanySetup() {
  const client = useQueryClient();
  const navigate = useNavigate();
  const companies = useQuery({ queryKey: ['companies', 'setup'], queryFn: getCompanies });
  const [notice, setNotice] = useState('');
  const [createdCompanyId, setCreatedCompanyId] = useState('');
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoError, setLogoError] = useState('');
  const logoInputRef = useRef<HTMLInputElement>(null);
  const { register, handleSubmit, reset, setValue, watch, formState: { errors } } = useForm<Form>({
    defaultValues: { ...defaults, ...loadDraft() },
  });
  const values = watch();

  useEffect(() => {
    const draft = { ...values };
    delete draft.logo;
    window.localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
  }, [values]);

  useEffect(() => () => {
    if (logoPreview) URL.revokeObjectURL(logoPreview);
  }, [logoPreview]);

  const mutation = useMutation({
    mutationFn: async (data: Form) => {
      const { logo, legal_name, ...fields } = data;
      const result = await createCompany({
        ...fields,
        name: legal_name.trim(),
        legal_name: legal_name.trim(),
        trading_name: fields.trading_name.trim() || null,
        code: fields.code.trim().toUpperCase(),
        business_type: fields.business_type.trim(),
        industry: fields.industry.trim() || null,
        registration_date: fields.registration_date || null,
        fiscal_year_start: fields.fiscal_year_start || null,
        description: fields.description.trim() || null,
        email: fields.email.trim(),
        phone: fields.phone.trim(),
        alternative_phone: fields.alternative_phone.trim() || null,
        website: fields.website.trim() || null,
        country_code: fields.country_code.trim().toUpperCase(),
        base_currency: fields.base_currency.trim().toUpperCase(),
        document_number_prefix: fields.document_number_prefix.trim().toUpperCase() || null,
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
      window.localStorage.removeItem(DRAFT_KEY);
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

  const logoRegistration = register('logo');
  const handleLogoChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!ACCEPTED_LOGO_TYPES.includes(file.type)) {
      setLogoError('Use a PNG, JPEG or WebP image.');
      event.target.value = '';
      setValue('logo', undefined);
      setLogoPreview(null);
      return;
    }
    if (file.size > MAX_LOGO_SIZE) {
      setLogoError('Logo must be 5 MB or smaller.');
      event.target.value = '';
      setValue('logo', undefined);
      setLogoPreview(null);
      return;
    }
    setLogoError('');
    logoRegistration.onChange(event);
    setLogoPreview(URL.createObjectURL(file));
  };

  const removeLogo = () => {
    setValue('logo', undefined, { shouldDirty: true });
    if (logoInputRef.current) logoInputRef.current.value = '';
    setLogoPreview(null);
    setLogoError('');
  };

  const saveDraft = () => {
    setNotice('Draft saved on this device. It has not created a company or changed your workspace.');
  };

  const errorMessage = (name: keyof Form) => errors[name]?.message as string | undefined;
  const location = [values.city, values.region, values.country_code].filter(Boolean).join(', ');
  const requiredComplete = [values.legal_name, values.code, values.business_type, values.email, values.phone, values.country_code, values.street_address, values.base_currency, values.timezone].filter(Boolean).length;

  return <section className="page-section company-setup-page">
    <div className="company-setup-heading">
      <div>
        <Link className="back-link" to="/"><ArrowLeft size={15} /> Back to dashboard</Link>
        <p className="eyebrow">Workspace onboarding</p>
        <h1>Set up your company</h1>
        <p className="muted">Create the company profile that powers your authorized ERP workspace.</p>
      </div>
      <div className="setup-security-note"><ShieldCheck size={16} /><span>Tenant-scoped and secure</span></div>
    </div>

    <ol className="setup-progress" aria-label="Company setup progress">
      <li className="is-current"><span>1</span><div><strong>Company identity</strong><small>Tell us about the business</small></div></li>
      <li><span>2</span><div><strong>Contact & address</strong><small>Where the business operates</small></div></li>
      <li><span>3</span><div><strong>Localization & branding</strong><small>Prepare your workspace</small></div></li>
    </ol>

    {notice && <div className="alert alert-warning" role="status">{notice} {createdCompanyId && <Link className="text-link" to={'/?company_id=' + createdCompanyId}>Open dashboard</Link>}</div>}
    {mutation.isError && <div className="alert alert-danger" role="alert">{mutation.error instanceof ApiError && mutation.error.status === 403 ? 'Your account is not authorized to create a company in this workspace. Ask the workspace owner to grant company management access.' : 'Unable to create company. ' + mutation.error.message}</div>}

    <div className="company-setup-layout">
      <form className="company-setup-main" onSubmit={handleSubmit((data) => { setNotice(''); setLogoError(''); mutation.mutate(data); })} noValidate>
        <fieldset className="card setup-card">
          <div className="setup-card-heading"><div className="setup-card-icon"><Building2 size={18} /></div><div><p className="panel-eyebrow">Step 1</p><legend>Company identity</legend><small>Use your registered business details. Fields marked * are required.</small></div></div>
          <div className="form-grid setup-form-grid">
            <label className="required-field">Legal company name *<input autoFocus {...register('legal_name', { required: 'Legal company name is required' })} placeholder="e.g. Acme Trading Co., Ltd." aria-invalid={Boolean(errors.legal_name)} />{errorMessage('legal_name') && <small className="error-text">{errorMessage('legal_name')}</small>}</label>
            <label>Trading name <span className="optional-label">Optional</span><input {...register('trading_name')} placeholder="Customer-facing name" /></label>
            <label className="required-field">Company code *<input {...register('code', { required: 'Company code is required', pattern: { value: /^[A-Za-z0-9_-]+$/, message: 'Use letters, numbers, hyphens or underscores.' } })} placeholder="e.g. ACME" aria-invalid={Boolean(errors.code)} />{errorMessage('code') && <small className="error-text">{errorMessage('code')}</small>}<small className="field-hint">Unique within this workspace.</small></label>
            <label className="required-field">Business type *<select {...register('business_type', { required: 'Business type is required' })} aria-invalid={Boolean(errors.business_type)}><option value="">Select business type</option><option value="Retail">Retail</option><option value="Services">Services</option><option value="Manufacturing">Manufacturing</option><option value="Distribution">Distribution</option><option value="Non-profit">Non-profit</option><option value="Other">Other</option></select>{errorMessage('business_type') && <small className="error-text">{errorMessage('business_type')}</small>}</label>
            <label>Industry <span className="optional-label">Optional</span><input {...register('industry')} placeholder="e.g. Consumer goods" /></label>
            <label>Registration number <span className="optional-label">Optional</span><input {...register('registration_number')} placeholder="Business registration ID" /></label>
            <label>Tax ID <span className="optional-label">Optional</span><input {...register('tax_identifier')} placeholder="Tax identification number" /></label>
            <label>Registration date <span className="optional-label">Optional</span><input type="date" {...register('registration_date')} /></label>
            <label className="form-span-2">Business description <span className="optional-label">Optional</span><textarea rows={3} {...register('description')} placeholder="Briefly describe what your company does." /></label>
          </div>
        </fieldset>

        <fieldset className="card setup-card">
          <div className="setup-card-heading"><div className="setup-card-icon"><MapPin size={18} /></div><div><p className="panel-eyebrow">Step 2</p><legend>Contact & address</legend><small>These details help your team and customers identify the company.</small></div></div>
          <div className="form-grid setup-form-grid">
            <label className="required-field">Company email *<input type="email" {...register('email', { required: 'Company email is required', pattern: { value: /^\S+@\S+\.\S+$/, message: 'Enter a valid email address.' } })} placeholder="office@company.com" aria-invalid={Boolean(errors.email)} />{errorMessage('email') && <small className="error-text">{errorMessage('email')}</small>}</label>
            <label className="required-field">Phone number *<input {...register('phone', { required: 'Phone number is required' })} placeholder="+95 9 000 000 000" aria-invalid={Boolean(errors.phone)} />{errorMessage('phone') && <small className="error-text">{errorMessage('phone')}</small>}</label>
            <label>Alternate phone <span className="optional-label">Optional</span><input {...register('alternative_phone')} placeholder="Secondary contact number" /></label>
            <label>Website <span className="optional-label">Optional</span><input type="url" {...register('website', { pattern: { value: /^$|https?:\/\/.+/, message: 'Use a full URL, e.g. https://company.com' } })} placeholder="https://company.com" />{errorMessage('website') && <small className="error-text">{errorMessage('website')}</small>}</label>
            <label className="required-field">Country *<select {...register('country_code', { required: 'Country is required' })} aria-invalid={Boolean(errors.country_code)}><option value="">Select country</option><option value="MM">Myanmar</option><option value="SG">Singapore</option><option value="TH">Thailand</option><option value="US">United States</option><option value="GB">United Kingdom</option></select>{errorMessage('country_code') && <small className="error-text">{errorMessage('country_code')}</small>}</label>
            <label>State / Region <span className="optional-label">Optional</span><input {...register('region')} placeholder="e.g. Yangon Region" /></label>
            <label>City <span className="optional-label">Optional</span><input {...register('city')} placeholder="e.g. Yangon" /></label>
            <label>Township <span className="optional-label">Optional</span><input {...register('township')} placeholder="e.g. Pabedan" /></label>
            <label>Postal code <span className="optional-label">Optional</span><input {...register('postal_code')} placeholder="Postal code" /></label>
            <label className="required-field form-span-2">Street address *<input {...register('street_address', { required: 'Street address is required' })} placeholder="Building, street and number" aria-invalid={Boolean(errors.street_address)} />{errorMessage('street_address') && <small className="error-text">{errorMessage('street_address')}</small>}</label>
          </div>
        </fieldset>

        <fieldset className="card setup-card">
          <div className="setup-card-heading"><div className="setup-card-icon"><Globe2 size={18} /></div><div><p className="panel-eyebrow">Step 3</p><legend>Localization & branding</legend><small>Set the defaults used for documents, dates and financial reporting.</small></div></div>
          <div className="form-grid setup-form-grid">
            <label className="required-field">Base currency *<select {...register('base_currency', { required: 'Base currency is required' })} aria-invalid={Boolean(errors.base_currency)}><option value="">Select currency</option><option value="MMK">MMK — Myanmar Kyat</option><option value="USD">USD — US Dollar</option><option value="SGD">SGD — Singapore Dollar</option><option value="THB">THB — Thai Baht</option><option value="EUR">EUR — Euro</option></select>{errorMessage('base_currency') && <small className="error-text">{errorMessage('base_currency')}</small>}</label>
            <label className="required-field">Timezone *<select {...register('timezone', { required: 'Timezone is required' })} aria-invalid={Boolean(errors.timezone)}><option value="Asia/Yangon">Asia/Yangon (UTC+06:30)</option><option value="UTC">UTC</option><option value="Asia/Singapore">Asia/Singapore (UTC+08:00)</option><option value="Asia/Bangkok">Asia/Bangkok (UTC+07:00)</option></select>{errorMessage('timezone') && <small className="error-text">{errorMessage('timezone')}</small>}<small className="field-hint">Used for business date boundaries.</small></label>
            <label className="required-field">Default language *<select {...register('default_locale', { required: 'Default language is required' })} aria-invalid={Boolean(errors.default_locale)}><option value="en">English</option><option value="my">မြန်မာ</option></select>{errorMessage('default_locale') && <small className="error-text">{errorMessage('default_locale')}</small>}</label>
            <label className="required-field">Date format *<select {...register('date_format', { required: 'Date format is required' })} aria-invalid={Boolean(errors.date_format)}><option value="YYYY-MM-DD">YYYY-MM-DD</option><option value="DD/MM/YYYY">DD/MM/YYYY</option><option value="MM/DD/YYYY">MM/DD/YYYY</option></select>{errorMessage('date_format') && <small className="error-text">{errorMessage('date_format')}</small>}</label>
            <label>Fiscal year start <span className="optional-label">Optional</span><input type="date" {...register('fiscal_year_start')} /><small className="field-hint">Leave blank if your finance team will configure it later.</small></label>
            <label>Document prefix <span className="optional-label">Optional</span><input maxLength={40} {...register('document_number_prefix')} placeholder="e.g. INV" /><small className="field-hint">Used as a starting prefix for documents.</small></label>
            <div className="logo-upload form-span-2">
              <div className="logo-upload-heading"><span>Company logo <span className="optional-label">Optional</span></span><small>PNG, JPEG or WebP up to 5 MB</small></div>
              <div className="logo-upload-body">
                <div className="logo-preview">{logoPreview ? <img src={logoPreview} alt="Selected company logo preview" /> : <ImagePlus size={26} />}</div>
                <div className="logo-upload-copy"><strong>{logoPreview ? 'Logo ready to upload' : 'Add a recognizable company logo'}</strong><small>It will appear in company profile and workspace branding.</small><div className="logo-upload-actions"><label className="secondary file-button"><Upload size={14} /> {logoPreview ? 'Replace logo' : 'Choose logo'}<input name={logoRegistration.name} onBlur={logoRegistration.onBlur} ref={(element) => { logoInputRef.current = element; logoRegistration.ref(element); }} type="file" accept="image/png,image/jpeg,image/webp" onChange={handleLogoChange} /></label>{logoPreview && <button type="button" className="ghost-button" onClick={removeLogo}><X size={14} /> Remove</button>}</div>{logoError && <small className="error-text">{logoError}</small>}</div>
              </div>
            </div>
          </div>
        </fieldset>

        <div className="setup-form-actions"><button className="secondary" type="button" onClick={saveDraft}><Save size={15} /> Save draft</button><Link className="secondary" to="/">Cancel</Link><button className="primary" type="submit" disabled={mutation.isPending}>{mutation.isPending ? 'Creating company…' : 'Create company & open dashboard'}</button></div>
      </form>

      <aside className="company-setup-sidebar" aria-label="Company setup guidance">
        <div className="setup-side-card setup-checklist"><div className="side-card-heading"><div><p className="panel-eyebrow">Your progress</p><h2>Setup checklist</h2></div><strong>{requiredComplete}/9</strong></div><div className="setup-progress-bar"><span style={{ width: `${Math.round(requiredComplete / 9 * 100)}%` }} /></div><ul>{[['Company identity', Boolean(values.legal_name && values.code && values.business_type)], ['Contact & address', Boolean(values.email && values.phone && values.country_code && values.street_address)], ['Localization', Boolean(values.base_currency && values.timezone && values.default_locale && values.date_format)]].map(([label, complete]) => <li key={String(label)} className={complete ? 'is-complete' : ''}><span>{complete ? <Check size={13} /> : <span />}</span>{label}</li>)}</ul></div>
        <div className="setup-side-card"><div className="side-card-heading"><div><p className="panel-eyebrow">Helpful guidance</p><h2>Before you continue</h2></div><CircleHelp size={17} /></div><ul className="setup-tips"><li>Use the legal name shown on your registration documents.</li><li>Choose the base currency carefully; it becomes the reporting default.</li><li>Your timezone controls date filters and transaction cutoffs.</li><li>Company codes should be short, unique and easy to recognize.</li></ul></div>
        <div className="setup-side-card company-preview-card"><div className="side-card-heading"><div><p className="panel-eyebrow">Live preview</p><h2>Company card</h2></div></div><div className="company-preview-brand"><div className="company-preview-logo">{logoPreview ? <img src={logoPreview} alt="" /> : <Building2 size={20} />}</div><div><strong>{values.trading_name || values.legal_name || 'Your company'}</strong><small>{values.code || 'COMPANY CODE'}</small></div></div><dl><div><dt>Business type</dt><dd>{values.business_type || 'Not set'}</dd></div><div><dt>Location</dt><dd>{location || 'Not set'}</dd></div><div><dt>Currency</dt><dd>{values.base_currency || 'Not set'}</dd></div></dl></div>
        <div className="setup-side-note"><ShieldCheck size={16} /><p>Your company is created inside the currently authorized tenant. No tenant or administrator access is taken from form input.</p></div>
        {companies.data?.data.data.length ? <div className="setup-side-card"><div className="side-card-heading"><div><p className="panel-eyebrow">Existing access</p><h2>Authorized companies</h2></div></div><div className="setup-company-list">{companies.data.data.data.map((company) => <div className="setup-company-row" key={company.id}><span><strong>{company.name}</strong><small>{company.code}</small></span><span className="status-pill success">Active</span></div>)}</div></div> : null}
        {companies.isError && <div className="setup-side-card setup-side-error"><p className="error-text">Unable to load your existing company access. {companies.error.message}</p></div>}
      </aside>
    </div>
  </section>;
}
