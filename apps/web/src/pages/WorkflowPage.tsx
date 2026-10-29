import { useQuery } from '@tanstack/react-query';
import { getApprovalInbox, getNotifications, getReportExports } from '../lib/api';

export function WorkflowPage() {
  const inbox = useQuery({ queryKey: ['approval-inbox'], queryFn: getApprovalInbox });
  const notifications = useQuery({ queryKey: ['notifications'], queryFn: getNotifications });
  const exports = useQuery({ queryKey: ['report-exports'], queryFn: getReportExports });
  const error = (inbox.error ?? notifications.error ?? exports.error) as Error | null;
  return <section><p className="eyebrow">Workflow & reporting</p><h1>Approvals, reports & automation</h1><p className="muted">Tenant-scoped approval inbox, secure notifications, private report exports and queued automation.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid"><div className="metric"><span>Approval inbox</span><strong>{inbox.data?.data.length ?? '—'}</strong></div><div className="metric"><span>Unread notifications</span><strong>{notifications.data?.data.data.filter((item) => !item.read_at).length ?? '—'}</strong></div><div className="metric"><span>Report exports</span><strong>{exports.data?.data.data.length ?? '—'}</strong></div></div><div className="panel"><h2>Approval inbox</h2><table><thead><tr><th>Document</th><th>Amount</th><th>Level</th><th>Status</th></tr></thead><tbody>{inbox.data?.data.map((item) => <tr key={item.id}><td>{item.document_type}</td><td>{item.amount}</td><td>{item.current_level}</td><td><span className="badge">{item.state}</span></td></tr>)}</tbody></table>{inbox.data?.data.length === 0 && <div className="empty">No approvals require your action.</div>}</div></section>;
}
