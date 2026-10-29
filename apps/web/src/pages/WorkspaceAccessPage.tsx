import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { createTenant, type WorkspaceAccess } from '../lib/api';

type Props = { access: WorkspaceAccess; onWorkspaceCreated: (tenantId: string) => void };

const slugify = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 80);

export function WorkspaceAccessPage({ access, onWorkspaceCreated }: Props) {
  const [name, setName] = useState('');
  const mutation = useMutation({
    mutationFn: () => createTenant({ name: name.trim(), slug: slugify(name) }),
    onSuccess: (result) => onWorkspaceCreated(result.data.id),
  });

  if (access.platform_admin) {
    return <div className="dashboard-loading" role="status">This account is a platform administrator. Open the Platform Console to manage platform operations.</div>;
  }

  return <section className="workspace-access card" aria-live="polite">
    <p className="eyebrow">Workspace access</p>
    <h2>Choose how to continue</h2>
    {access.pending_invitations.length > 0 && <div className="workspace-access-block">
      <h3>Invitation pending</h3>
      <p className="muted">An invitation is waiting for this account. Open the invitation email and accept it while signed in with this email address.</p>
      <ul className="workspace-status-list">{access.pending_invitations.map((invitation) => <li key={invitation.id}>{invitation.tenant_name ?? 'Workspace'}{invitation.company_name ? ` · ${invitation.company_name}` : ''} <span className="muted">expires {new Date(invitation.expires_at).toLocaleDateString()}</span></li>)}</ul>
    </div>}
    {access.restricted_workspaces.length > 0 && <div className="workspace-access-block">
      <h3>Workspace access needs attention</h3>
      <p className="muted">These workspaces cannot be opened from this account right now. Contact a workspace administrator; suspension is not bypassed here.</p>
      <ul className="workspace-status-list">{access.restricted_workspaces.map((workspace) => <li key={workspace.id}><strong>{workspace.name}</strong> <span className="status-pill">{workspace.status.replaceAll('_', ' ')}</span></li>)}</ul>
    </div>}
    {access.can_create_workspace && <div className="workspace-access-block">
      <h3>Create your workspace</h3>
      <p className="muted">No workspace is linked to this verified account yet. Create one to begin the legitimate setup flow.</p>
      <form className="workspace-create-form" onSubmit={(event) => { event.preventDefault(); if (name.trim() && slugify(name)) mutation.mutate(); }}>
        <label>Workspace name<input value={name} onChange={(event) => setName(event.target.value)} required maxLength={150} placeholder="Acme Operations" /></label>
        <button className="primary" type="submit" disabled={mutation.isPending}>{mutation.isPending ? 'Creating…' : 'Create workspace'}</button>
      </form>
      {mutation.isError && <p className="error-text" role="alert">{mutation.error.message}</p>}
    </div>}
    {!access.can_create_workspace && access.pending_invitations.length === 0 && access.restricted_workspaces.length === 0 && <div className="workspace-access-block"><p className="muted">No active workspace is available for this account. Ask a workspace administrator to invite you, or verify your email before creating a workspace.</p></div>}
  </section>;
}
