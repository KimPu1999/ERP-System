import type { Branch } from '../lib/api';

export function BranchStatusBadge({ status }: { status: Branch['status'] }) {
  return <span className={`status-pill branch-status-${status}`}>{status}</span>;
}
