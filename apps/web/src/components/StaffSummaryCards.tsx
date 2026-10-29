import { Mail, ShieldAlert, UserCheck, Users } from 'lucide-react';

export function StaffSummaryCards({ summary, loading }: { summary?: { total_members: number; active_members: number; pending_invitations: number; suspended_members: number }; loading?: boolean }) {
  const cards = [['Total members', summary?.total_members, Users], ['Active members', summary?.active_members, UserCheck], ['Pending invitations', summary?.pending_invitations, Mail], ['Suspended members', summary?.suspended_members, ShieldAlert]] as const;
  return <div className="organization-kpi-grid staff-summary-grid">{cards.map(([label, value, Icon]) => <div className="metric organization-metric" key={label}><span><Icon size={15} aria-hidden="true" /> {label}</span><strong>{loading ? '—' : value ?? '—'}</strong></div>)}</div>;
}
