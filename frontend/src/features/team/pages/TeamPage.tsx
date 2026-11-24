import { Copy, Crown, MailPlus, ShieldCheck, X } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Avatar, Badge, Button, Card, CardHeader, ConfirmDialog, DataTable, Field, FormModal, Input, PageHeader, Select, StatusBadge, type Column } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useOptions } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { ago, dateTime } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { ROLE_LABELS } from '@/layouts/AppLayout'
import { useAuth } from '@/stores/auth'
import type { Driver } from '@/types/domain'

interface Member { id: number; name: string; email: string; phone: string | null; roles: string[]; status: string; is_owner: boolean; joined_at: string | null; last_login_at: string | null; driver_id: number | null }
interface Team { members: Member[]; invitations: { id: number; email: string; role: string; invited_by: string | null; expires_at: string }[]; roles: { value: string; label: string; permissions: string[] }[] }

export default function TeamPage() {
  const { can, user } = useAuth()
  const team = useAsync(() => api.get<Team>('/team'), [])
  const drivers = useOptions<Driver>(can('drivers.view') ? '/drivers' : null)
  const [open, setOpen] = useState(false)
  const [f, setF] = useState({ email: '', role: 'dispatcher', driver_id: '' })
  const [link, setLink] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const t = team.data

  const update = async (m: Member, body: Record<string, string>) => {
    try { await api.patch(`/team/members/${m.id}`, body); toast.success('Member updated'); void team.reload() } catch (e) { toast.error(toApiError(e).message) }
  }
  const columns: Column<Member>[] = [
    { key: 'n', header: 'Member', cell: (m) => <div className="flex items-center gap-3"><Avatar name={m.name} /><div><p className="flex items-center gap-1.5 font-semibold text-fg">{m.name}{m.is_owner && <Crown className="size-3.5 text-warning" />}</p><p className="text-xs text-muted">{m.email}</p></div></div> },
    { key: 'r', header: 'Role', cell: (m) => can('roles.manage') && !m.is_owner && m.id !== user?.id
      ? <Select className="h-8 w-40 text-xs" value={m.roles[0] ?? ''} onChange={(e) => update(m, { role: e.target.value })}>{t?.roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}</Select>
      : <Badge tone="primary">{ROLE_LABELS[m.roles[0] ?? ''] ?? '—'}</Badge> },
    { key: 's', header: 'Status', cell: (m) => <StatusBadge status={m.status === 'disabled' ? 'inactive' : 'active'} label={m.status} /> },
    { key: 'l', header: 'Last sign-in', cell: (m) => m.last_login_at ? ago(m.last_login_at) : 'Never', hideOnMobile: true },
    { key: 'a', header: '', className: 'text-right', cell: (m) => can('users.manage') && !m.is_owner && m.id !== user?.id ? (
      m.status === 'active'
        ? <ConfirmDialog title={`Disable ${m.name}?`} description="They lose access to this workspace immediately and their sessions/tokens are revoked." confirmLabel="Disable" onConfirm={() => update(m, { status: 'disabled' })} trigger={<Button size="sm" variant="ghost" className="text-danger">Disable</Button>} />
        : <Button size="sm" variant="ghost" onClick={() => update(m, { status: 'active' })}>Re-enable</Button>
    ) : null },
  ]

  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Workspace" title="Team" description="Members of this company and their roles. Roles are per company."
        actions={can('users.invite') && <Button onClick={() => { setOpen(true); setLink(null) }}><MailPlus /> Invite member</Button>} />
      <Card className="overflow-hidden"><DataTable loading={team.loading} rows={t?.members ?? []} columns={columns} /></Card>
      {(t?.invitations.length ?? 0) > 0 && (
        <Card><CardHeader title="Pending invitations" />
          <ul className="divide-y divide-border">{t!.invitations.map((i) => (
            <li key={i.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
              <div><p className="font-semibold">{i.email}</p><p className="text-xs text-muted">{ROLE_LABELS[i.role]} · invited by {i.invited_by} · expires {dateTime(i.expires_at)}</p></div>
              {can('users.invite') && <Button size="icon-sm" variant="ghost" aria-label="Revoke" onClick={async () => { await api.delete(`/team/invitations/${i.id}`); void team.reload() }}><X /></Button>}
            </li>))}</ul>
        </Card>
      )}
      <Card><CardHeader title="Roles & permissions" icon={<ShieldCheck />} description="Defined by the platform; assign one role per member." />
        <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
          {t?.roles.map((r) => (
            <div key={r.value} className="rounded-xl border border-border p-4">
              <p className="font-semibold">{r.label} <span className="text-xs font-normal text-muted">· {r.permissions.length} permissions</span></p>
              <div className="mt-2 flex flex-wrap gap-1">{r.permissions.slice(0, 14).map((p) => <span key={p} className="rounded bg-surface-3 px-1.5 py-0.5 text-[10.5px] text-fg-soft">{p}</span>)}{r.permissions.length > 14 && <span className="text-[10.5px] text-muted">+{r.permissions.length - 14}</span>}</div>
            </div>
          ))}
        </div>
      </Card>
      <FormModal open={open} onOpenChange={setOpen} title="Invite a member" size="md" submitting={busy} submitLabel={link ? 'Invite another' : 'Send invitation'} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try {
          const r = await api.post<{ invitation_url: string }>('/team/invitations', { ...f, driver_id: f.role === 'driver' ? f.driver_id : null })
          setLink(r.invitation_url); toast.success(`Invitation sent to ${f.email}`); setF({ ...f, email: '' }); void team.reload()
        } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label="Email" required><Input type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></Field>
        <Field label="Role" required><Select value={f.role} onChange={(e) => setF({ ...f, role: e.target.value })}>{t?.roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}</Select></Field>
        {f.role === 'driver' && <Field label="Driver profile" required hint="Links the account to an existing driver"><Select value={f.driver_id} onChange={(e) => setF({ ...f, driver_id: e.target.value })}><option value="">Select…</option>{drivers.filter((d) => !d.has_account).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}</Select></Field>}
        {link && (
          <div className="rounded-xl border border-success/30 bg-success-soft p-3 text-xs">
            <p className="font-semibold text-success">Invitation emailed. You can also share this link directly:</p>
            <div className="mt-2 flex gap-2"><code className="min-w-0 flex-1 truncate rounded bg-surface px-2 py-1.5">{link}</code><Button type="button" size="sm" variant="outline" onClick={() => { void navigator.clipboard.writeText(link); toast.success('Copied') }}><Copy /></Button></div>
          </div>
        )}
      </FormModal>
    </div>
  )
}
