import { Archive, CheckCircle2, CircleOff, UserRound } from 'lucide-react';

type Summary = { total: number; active: number; inactive: number; assigned_managers: number };

export function BranchSummaryCards({ summary, isLoading }: { summary?: Summary; isLoading?: boolean }) {
  const cards = [['Total branches', summary?.total ?? 0, Archive], ['Active branches', summary?.active ?? 0, CheckCircle2], ['Inactive branches', summary?.inactive ?? 0, CircleOff], ['Assigned managers', summary?.assigned_managers ?? 0, UserRound]] as const;
  return <div className="branch-summary-grid">{cards.map(([label, value, Icon]) => <article className="metric branch-summary-card" key={label}><span><Icon size={15} aria-hidden="true" /> {label}</span><strong>{isLoading ? '—' : value}</strong></article>)}</div>;
}
