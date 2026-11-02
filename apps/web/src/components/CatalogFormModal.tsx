import { type ReactNode, useEffect, useRef } from 'react';
import { X } from 'lucide-react';

type CatalogFormModalProps = {
  open: boolean;
  title: string;
  subtitle?: string;
  eyebrow?: string;
  onClose: () => void;
  children: ReactNode;
  footer: ReactNode;
};

export function CatalogFormModal({ open, title, subtitle, eyebrow = 'Product catalog', onClose, children, footer }: CatalogFormModalProps) {
  const dialogRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';
    const focusFirst = () => dialogRef.current?.querySelector<HTMLElement>(focusableSelector)?.focus();
    const timer = window.setTimeout(focusFirst, 0);
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') { event.preventDefault(); onClose(); return; }
      if (event.key !== 'Tab' || !dialogRef.current) return;
      const focusable = dialogRef.current.querySelectorAll<HTMLElement>(focusableSelector);
      if (!focusable.length) return;
      const first = focusable[0]; const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => { window.clearTimeout(timer); document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeyDown); };
  }, [onClose, open]);

  if (!open) return null;
  return <div className="catalog-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><div className="catalog-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="catalog-modal-title"><header className="catalog-modal-header"><div><p className="eyebrow">{eyebrow}</p><h2 id="catalog-modal-title">{title}</h2>{subtitle && <p className="muted">{subtitle}</p>}</div><button className="icon-button" type="button" aria-label={`Close ${title}`} onClick={onClose}><X size={17} /></button></header><div className="catalog-modal-body">{children}</div><footer className="catalog-modal-footer">{footer}</footer></div></div>;
}
