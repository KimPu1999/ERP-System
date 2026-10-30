import { Check, ChevronDown, Globe2 } from 'lucide-react';
import { useState } from 'react';
import { useI18n, type Locale } from '../lib/i18n';

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
  const { locale, setLocale, t } = useI18n();
  const [open, setOpen] = useState(false);
  const options: Array<{ value: Locale; label: string; short: string }> = [
    { value: 'en', label: t('common.english'), short: t('language.englishShort') },
    { value: 'my-MM', label: t('common.burmese'), short: t('language.burmeseShort') },
  ];
  const selected = options.find((option) => option.value === locale) ?? options[0];
  return <div className="language-switcher">
    <button type="button" className={`language-trigger ${compact ? 'language-trigger-compact' : ''}`} aria-label={t('app.changeLanguage')} aria-expanded={open} onClick={() => setOpen((current) => !current)}>
      <Globe2 size={17} aria-hidden="true" /><span>{selected.short}</span><ChevronDown size={14} aria-hidden="true" />
    </button>
    {open && <div className="language-menu" role="menu">
      <span className="language-menu-label">{t('language.label')}</span>
      {options.map((option) => <button type="button" role="menuitem" key={option.value} className="language-option" onClick={() => { setLocale(option.value); setOpen(false); }}><span>{option.label}</span>{option.value === locale && <Check size={15} aria-hidden="true" />}</button>)}
    </div>}
  </div>;
}
