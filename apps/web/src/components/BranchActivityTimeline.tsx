import type { BranchDetails } from '../lib/api';

export function BranchActivityTimeline({ activity }: { activity: BranchDetails['activity'] }) {
  return <div className="branch-activity-timeline">{activity.length ? activity.map((item) => <div className="branch-activity-item" key={item.id}><span className="status-dot" aria-hidden="true" /><div><strong>{item.action.replaceAll('.', ' ')}</strong><time>{new Date(item.created_at).toLocaleString()}</time></div></div>) : <p className="muted">No branch activity has been recorded yet.</p>}</div>;
}
