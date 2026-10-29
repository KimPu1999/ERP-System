export function MembershipStatusBadge({ status }: { status: string }) {
  const tone = status === 'active' ? 'success' : status === 'suspended' ? 'warning' : status === 'pending' ? 'info' : 'danger';
  return <span className={`status-pill ${tone}`}>{status}</span>;
}
