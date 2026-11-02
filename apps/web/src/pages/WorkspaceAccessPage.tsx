import { AlertCircle, ArrowRight, CheckCircle2, Clock3, ShieldCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Navigate } from 'react-router-dom';
import { ApiError, createTenant, type WorkspaceAccess } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

type Props = { access: WorkspaceAccess; onWorkspaceCreated: (tenantId: string) => void };

const slugify = (value: string) => {
  const slug = value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 80);
  if (slug) return slug;
  let hash = 0;
  for (const character of value.trim()) hash = ((hash << 5) - hash + character.charCodeAt(0)) | 0;
  return `workspace-${Math.abs(hash).toString(36)}`;
};

type StatusTone = 'complete' | 'ready' | 'pending' | 'attention';

function StatusRow({ label, detail, tone }: { label: string; detail: string; tone: StatusTone }) {
  const Icon = tone === 'complete' ? CheckCircle2 : tone === 'pending' ? Clock3 : tone === 'attention' ? AlertCircle : ShieldCheck;
  return <div className={`workspace-status-row is-${tone}`}>
    <span className="workspace-status-icon" aria-hidden="true"><Icon size={16} /></span>
    <span className="workspace-status-copy"><strong>{label}</strong><small>{detail}</small></span>
  </div>;
}

export function WorkspaceAccessPage({ access, onWorkspaceCreated }: Props) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [name, setName] = useState('');
  const [nameError, setNameError] = useState('');
  const mutation = useMutation({
    mutationFn: () => createTenant({ name: name.trim(), slug: slugify(name) }),
    onSuccess: (result) => onWorkspaceCreated(result.data.id),
  });

  if (access.platform_admin) return <Navigate to="/platform" replace />;

  const hasActiveWorkspace = access.active_workspaces.length > 0;
  const hasPendingInvitation = access.pending_invitations.length > 0;
  const hasRestrictedWorkspace = access.restricted_workspaces.length > 0;
  const canCreate = access.can_create_workspace && Boolean(user?.email_verified_at);
  const firstName = user?.name?.trim().split(/\s+/)[0] ?? '';
  const hour = new Date().getHours();
  const greeting = hour < 12 ? t('dashboard.goodMorning') : hour < 18 ? t('dashboard.goodAfternoon') : t('dashboard.goodEvening');
  const apiError = mutation.error instanceof ApiError ? mutation.error : null;
  const serverNameError = apiError?.errors.name?.[0];

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const trimmed = name.trim();
    if (!trimmed) {
      setNameError(t('workspace.nameRequired'));
      return;
    }
    setNameError('');
    mutation.mutate();
  };

  return <section className="workspace-onboarding" aria-labelledby="workspace-onboarding-title">
    <div className="workspace-onboarding-welcome">
      <div>
        <p className="eyebrow">{t('workspace.accessEyebrow')}</p>
        <h1 id="workspace-onboarding-title">{greeting}{firstName ? `, ${firstName}` : ''}</h1>
        <p className="workspace-onboarding-subtitle">{t('workspace.welcome')}</p>
      </div>
      <div className="workspace-user-chip">
        <span className="workspace-user-avatar" aria-hidden="true">{user?.name?.slice(0, 1).toUpperCase() ?? 'U'}</span>
        <span><strong>{user?.name ?? t('app.account')}</strong><small>{user?.email}</small></span>
      </div>
    </div>

    <div className="workspace-onboarding-grid">
      <article className="workspace-onboarding-panel">
        <div className="workspace-panel-heading">
          <div><span className="workspace-panel-kicker">{t('workspace.setupLabel')}</span><h2>{t('workspace.setupTitle')}</h2></div>
          <span className="workspace-step-count">{canCreate ? '2/3' : hasActiveWorkspace ? '3/3' : '1/3'}</span>
        </div>
        <p className="workspace-panel-description">{canCreate ? t('workspace.createDescription') : hasPendingInvitation ? t('workspace.invitationDescription') : t('workspace.accessDescription')}</p>

        <ol className="workspace-progress" aria-label={t('workspace.progressLabel')}>
          <li className="is-complete"><span><CheckCircle2 size={14} /></span>{t('workspace.accountVerified')}</li>
          <li className={canCreate ? 'is-current' : hasActiveWorkspace ? 'is-complete' : ''}><span>{hasActiveWorkspace ? <CheckCircle2 size={14} /> : <span>2</span>}</span>{t('workspace.createWorkspace')}</li>
          <li className={hasActiveWorkspace ? 'is-current is-complete' : ''}><span>{hasActiveWorkspace ? <CheckCircle2 size={14} /> : <span>3</span>}</span>{t('workspace.completeSetup')}</li>
        </ol>

        {canCreate && <form className="workspace-create-form-modern" onSubmit={submit} noValidate>
          <div className="workspace-form-field">
            <label htmlFor="workspace-name">{t('workspace.name')} <span aria-hidden="true">*</span></label>
            <input id="workspace-name" value={name} onChange={(event) => { setName(event.target.value); setNameError(''); if (mutation.isError) mutation.reset(); }} maxLength={150} placeholder={t('workspace.namePlaceholder')} aria-invalid={Boolean(nameError || serverNameError)} aria-describedby={nameError || serverNameError ? 'workspace-name-error' : undefined} autoFocus />
            {(nameError || serverNameError) && <small className="field-error" id="workspace-name-error">{nameError || serverNameError}</small>}
          </div>
          {apiError && !serverNameError && <div className="alert alert-danger workspace-form-error" role="alert">{apiError.status === 403 ? t('workspace.notAuthorized') : apiError.message}</div>}
          <button className="primary workspace-create-button" type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? t('workspace.creating') : t('workspace.createButton')} {!mutation.isPending && <ArrowRight size={15} />}
          </button>
        </form>}

        {hasPendingInvitation && <div className="workspace-state-message is-pending"><Clock3 size={17} /><div><strong>{t('workspace.invitationTitle')}</strong><p>{t('workspace.invitationDescription')}</p><ul>{access.pending_invitations.map((invitation) => <li key={invitation.id}>{invitation.tenant_name ?? t('workspace.unnamedWorkspace')}{invitation.company_name ? ` · ${invitation.company_name}` : ''}<small>{t('workspace.expires')} {new Date(invitation.expires_at).toLocaleDateString()}</small></li>)}</ul></div></div>}
        {hasRestrictedWorkspace && <div className="workspace-state-message is-attention"><AlertCircle size={17} /><div><strong>{t('workspace.restrictedTitle')}</strong><p>{t('workspace.restrictedDescription')}</p><ul>{access.restricted_workspaces.map((workspace) => <li key={workspace.id}><strong>{workspace.name}</strong><span className="status-pill">{workspace.status.replaceAll('_', ' ')}</span></li>)}</ul></div></div>}
        {!canCreate && !hasPendingInvitation && !hasRestrictedWorkspace && !hasActiveWorkspace && <div className="workspace-state-message is-attention"><AlertCircle size={17} /><div><strong>{t('workspace.noAccessTitle')}</strong><p>{t('workspace.noAccessDescription')}</p></div></div>}
      </article>

      <aside className="workspace-overview-panel" aria-labelledby="workspace-overview-title">
        <div className="workspace-panel-heading"><div><span className="workspace-panel-kicker">{t('workspace.overviewLabel')}</span><h2 id="workspace-overview-title">{t('workspace.overviewTitle')}</h2></div><ShieldCheck size={20} className="workspace-overview-icon" /></div>
        <div className="workspace-status-list-modern">
          <StatusRow label={t('workspace.accountVerification')} detail={user?.email_verified_at ? t('workspace.verified') : t('workspace.verificationRequired')} tone={user?.email_verified_at ? 'complete' : 'attention'} />
          <StatusRow label={t('workspace.workspaceLinkage')} detail={hasActiveWorkspace ? t('workspace.linked') : hasPendingInvitation ? t('workspace.invitationPending') : hasRestrictedWorkspace ? t('workspace.accessReview') : t('workspace.notLinked')} tone={hasActiveWorkspace ? 'complete' : hasPendingInvitation ? 'pending' : hasRestrictedWorkspace ? 'attention' : 'ready'} />
          <StatusRow label={t('workspace.setupReadiness')} detail={hasActiveWorkspace ? t('workspace.setupComplete') : canCreate ? t('workspace.readyToCreate') : t('workspace.setupRequired')} tone={hasActiveWorkspace ? 'complete' : canCreate ? 'ready' : 'attention'} />
        </div>
        <div className="workspace-overview-note"><strong>{t('workspace.secureSetup')}</strong><p>{t('workspace.secureSetupDetail')}</p></div>
      </aside>
    </div>
  </section>;
}
