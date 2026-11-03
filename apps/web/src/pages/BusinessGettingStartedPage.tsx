import { CheckCircle2, CircleAlert, LockKeyhole } from 'lucide-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { completeBusinessOnboardingStep, getBusinessOnboarding } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function BusinessGettingStartedPage() {
  const queryClient = useQueryClient();
  const { t } = useI18n();
  const onboarding = useQuery({ queryKey: ['business-onboarding'], queryFn: getBusinessOnboarding });
  const complete = useMutation({
    mutationFn: completeBusinessOnboardingStep,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['business-onboarding'] }),
  });
  const data = onboarding.data?.data;

  return <section className="page-section" aria-labelledby="getting-started-title">
    <div className="page-header"><div><p className="eyebrow">{t('onboarding.eyebrow')}</p><h1 id="getting-started-title">{t('onboarding.title')}</h1><p className="muted">{t('onboarding.subtitle')}</p></div></div>
    {onboarding.isLoading && <div className="panel" role="status">{t('onboarding.loading')}</div>}
    {onboarding.isError && <div className="alert alert-danger" role="alert">{t('onboarding.error')}</div>}
    {data && <><div className="panel" style={{ marginBottom: 16 }}><div className="table-card-heading"><div><p className="eyebrow">{t('onboarding.readiness')}</p><h2>{data.progress_percent}% {t('onboarding.complete')}</h2></div><span className="status-badge">{data.status.replaceAll('_', ' ')}</span></div><div aria-label={t('onboarding.readiness')} style={{ height: 8, background: 'var(--surface-hover)', borderRadius: 999 }}><div style={{ width: String(data.progress_percent) + '%', height: '100%', background: 'var(--blue)', borderRadius: 999 }} /></div>{data.next_step && <p className="muted" style={{ marginBottom: 0 }}>{t('onboarding.next')}: <strong>{data.next_step.label}</strong></p>}</div>
      <div className="panel"><div className="table-card-heading"><div><p className="eyebrow">{t('onboarding.checklist')}</p><h2>{t('onboarding.verifiedRequirements')}</h2></div></div><div className="workspace-status-list-modern">{data.steps.map((step) => <div className={'workspace-status-row is-' + (step.complete ? 'complete' : step.required ? 'attention' : 'ready')} key={step.key}><span className="workspace-status-icon" aria-hidden="true">{step.complete ? <CheckCircle2 size={16} /> : step.required ? <CircleAlert size={16} /> : <LockKeyhole size={16} />}</span><span className="workspace-status-copy"><strong>{step.label}{step.required ? ' · ' + t('onboarding.required') : ' · ' + t('onboarding.optional')}</strong><small>{step.complete ? t('onboarding.verifiedFromData') : step.required ? t('onboarding.requiredAction') : t('onboarding.optionalStep')}</small></span>{!step.complete && step.action_path && <Link className="button button-secondary button-small" to={step.action_path}>{t('onboarding.continue')}</Link>}{step.complete && <button className="button button-secondary button-small" type="button" onClick={() => complete.mutate(step.key)} disabled={complete.isPending}>{t('onboarding.record')}</button>}</div>)}</div></div>
    </>}
  </section>;
}
