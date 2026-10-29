import { Link } from 'react-router-dom';

export function Brand({ compact = false }: { compact?: boolean }) {
  return (
    <Link className={`brand-lockup${compact ? ' brand-lockup-compact' : ''}`} to="/">
      <span className="brand-mark" aria-hidden="true">E</span>
      {!compact && <span><strong>ERP Cloud</strong><small>Enterprise workspace</small></span>}
    </Link>
  );
}
