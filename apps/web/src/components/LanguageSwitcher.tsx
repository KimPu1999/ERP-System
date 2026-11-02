import { useI18n, type Locale } from '../lib/i18n';

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
  const { locale, setLocale, t } = useI18n();
  const options: Array<{ value: Locale; short: string; label: string }> = [
    { value: 'en', short: 'EN', label: t('common.english') },
    { value: 'my-MM', short: 'MM', label: t('common.burmese') },
  ];
  return <div className={`language-switcher language-segmented ${compact ? 'language-switcher-compact' : ''}`} role="group" aria-label={t('app.changeLanguage')}>
    {options.map((option) => <button type="button" key={option.value} className={`language-segment ${locale === option.value ? 'is-active' : ''}`} aria-label={option.label} aria-pressed={locale === option.value} title={option.label} onClick={() => setLocale(option.value)}>{option.short}</button>)}
  </div>;
}
