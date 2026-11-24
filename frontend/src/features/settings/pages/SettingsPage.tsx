import { Copy, KeyRound, Plug, Plus, RefreshCw, Save, Trash2, Webhook } from 'lucide-react'
import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, Field, FormModal, Input, Modal, PageHeader, Select, Skeleton, StatusBadge, Switch, Tabs,
} from '@/components/ui'
import { companyApi } from '@/features/auth/api/authApi'
import { CompanyForm } from '@/features/onboarding/components/CompanyForm'
import { useAsync } from '@/hooks/useAsync'
import { usePaged } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { dateTime, label, money, num } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'

type Tab = 'company' | 'operations' | 'subscription' | 'integrations' | 'activity'
type Settings = Record<string, boolean | number>

function CompanyTab() {
  const refresh = useAuth((s) => s.refresh)
  const can = useAuth((s) => s.can)
  const { data: company, loading } = useAsync(() => companyApi.current(), [])
  if (loading || !company) return <Skeleton className="h-96" />
  return (
    <Card className="p-6 sm:p-8">
      <CompanyForm defaultValues={company} onSubmit={async (values) => { await companyApi.update(values); await refresh(); toast.success('Company settings saved') }}
        footer={({ isSubmitting, isDirty }) => can('company.update') && <div className="mt-8 flex justify-end border-t border-border pt-6"><Button type="submit" loading={isSubmitting} disabled={!isDirty}><Save /> Save changes</Button></div>} />
    </Card>
  )
}

function OperationsTab() {
  const can = useAuth((s) => s.can)
  const { data, loading, setData } = useAsync(() => api.get<Settings>('/companies/current/settings'), [])
  const [draft, setDraft] = useState<Settings | null>(null)
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (data) setDraft(data) }, [data])
  if (loading || !draft) return <Skeleton className="h-96" />
  const editable = can('company.settings')
  const sw = (k: string, l: string, d: string) => <Switch disabled={!editable} checked={Boolean(draft[k])} onChange={(v) => setDraft({ ...draft, [k]: v })} label={l} description={d} />
  const n = (k: string, l: string, hint: string, step = 1) => <Field label={l} hint={hint}><Input type="number" step={step} disabled={!editable} value={String(draft[k])} onChange={(e) => setDraft({ ...draft, [k]: Number(e.target.value) })} /></Field>
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card><CardHeader title="Dispatch rules" /><CardBody className="divide-y divide-border py-1">
        {sw('exclusive_vehicle_assignment', 'Exclusive vehicle assignment', 'A vehicle can be assigned to only one driver at a time')}
        {sw('drivers_can_reject_assignments', 'Drivers can reject assignments', 'Otherwise drivers can only accept')}
        <div className="py-3">{n('max_active_deliveries_per_driver', 'Max active deliveries per driver', 'Prevents overloading a driver')}</div>
      </CardBody></Card>
      <Card><CardHeader title="Proof of delivery" /><CardBody className="divide-y divide-border py-1">
        {sw('require_proof_of_delivery', 'Require photo or signature', 'Default for new deliveries')}
        {sw('require_delivery_otp', 'Require delivery code (OTP)', 'Recipient gives a 6-digit code to the driver')}
      </CardBody></Card>
      <Card><CardHeader title="Customer tracking page" /><CardBody className="divide-y divide-border py-1">
        {sw('public_tracking_enabled', 'Public tracking links', 'Customers can follow deliveries with a secure link')}
        {sw('tracking_show_driver_name', "Show driver's first name", 'No phone number or surname is ever shown')}
        {sw('tracking_show_live_position', 'Show live vehicle position', 'Only while the parcel is on the road')}
      </CardBody></Card>
      <Card><CardHeader title="Finance & data" /><CardBody className="grid gap-4 sm:grid-cols-2">
        {n('default_tax_rate', 'Default tax rate (%)', 'Applied to new invoices', 0.01)}
        {n('invoice_due_days', 'Invoice due (days)', 'Payment terms')}
        {n('maintenance_reminder_days', 'Service reminder (days ahead)', 'Scheduler warning window')}
        {n('document_expiry_warning_days', 'Document expiry warning (days)', 'Insurance, licences, permits')}
        {n('location_retention_days', 'GPS history retention (days)', 'Older points are pruned nightly')}
      </CardBody></Card>
      {editable && <div className="flex justify-end lg:col-span-2"><Button loading={busy} onClick={async () => {
        setBusy(true)
        try { setData(await api.put<Settings>('/companies/current/settings', draft)); toast.success('Settings saved') } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(false) }
      }}><Save /> Save settings</Button></div>}
    </div>
  )
}

interface Sub { status: string; plan: { code: string; name: string; features: string[] } | null; trial_ends_at: string | null; ends_at: string | null; usage: Record<string, { used: number; limit: number | null }>; plans: { code: string; name: string; price_monthly: string; currency: string; max_vehicles: number | null; max_drivers: number | null; max_users: number | null; max_monthly_deliveries: number | null; features: string[] }[] }

function SubscriptionTab() {
  const { data: s, loading } = useAsync(() => api.get<Sub>('/companies/current/subscription'), [])
  if (loading || !s) return <Skeleton className="h-96" />
  return (
    <div className="space-y-5">
      <Card><CardBody className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-subtle">Current plan</p>
          <p className="mt-1 text-2xl font-semibold">{s.plan?.name ?? 'No active plan'} <StatusBadge status={s.status === 'trialing' ? 'scheduled' : s.status} label={s.status} /></p>
          {s.trial_ends_at && <p className="text-sm text-muted">Trial ends {dateTime(s.trial_ends_at)}</p>}
        </div>
        <p className="max-w-sm text-sm text-muted">Plan changes are handled by the FleetFlow team. Online payment is not enabled on this platform yet.</p>
      </CardBody></Card>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Object.entries(s.usage).map(([k, u]) => {
          const p = u.limit ? Math.min(100, (u.used / u.limit) * 100) : 0
          return (
            <div key={k} className="ff-card p-4">
              <p className="text-sm text-muted">{label(k)}</p>
              <p className="mt-1 text-2xl font-semibold">{num(u.used)} <span className="text-sm font-medium text-muted">/ {u.limit ?? '∞'}</span></p>
              <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-3"><div className={`h-full rounded-full ${p > 90 ? 'bg-danger' : p > 70 ? 'bg-warning' : 'bg-primary'}`} style={{ width: `${u.limit ? p : 4}%` }} /></div>
            </div>
          )
        })}
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        {s.plans.map((p) => (
          <div key={p.code} className={`ff-card p-5 ${s.plan?.code === p.code ? 'ring-2 ring-primary' : ''}`}>
            <div className="flex items-center justify-between"><p className="text-lg font-semibold">{p.name}</p>{s.plan?.code === p.code && <Badge tone="primary">Current</Badge>}</div>
            <p className="mt-1 text-2xl font-semibold">{money(p.price_monthly, p.currency)}<span className="text-sm font-medium text-muted"> /month</span></p>
            <ul className="mt-3 space-y-1 text-sm text-fg-soft">
              <li>{p.max_vehicles ?? 'Unlimited'} vehicles · {p.max_drivers ?? 'Unlimited'} drivers</li>
              <li>{p.max_users ?? 'Unlimited'} users · {p.max_monthly_deliveries ?? 'Unlimited'} deliveries/month</li>
              {p.features.map((f) => <li key={f} className="text-success">✓ {label(f)}</li>)}
            </ul>
          </div>
        ))}
      </div>
    </div>
  )
}

interface Integrations { feature_available: boolean; scopes: string[]; events: string[]; clients: { id: number; name: string; type: string; scopes: string[]; is_active: boolean; last_used_at: string | null }[]; webhooks: { id: number; url: string; events: string[]; is_active: boolean }[] }

function IntegrationsTab() {
  const { data, loading, reload } = useAsync(() => api.get<Integrations>('/integrations'), [])
  const log = usePaged<{ id: number; event: string; url: string; status: string; attempts: number; response_status: number | null; created_at: string }>('/integrations/webhook-deliveries')
  const [secret, setSecret] = useState<{ title: string; value: string } | null>(null)
  const [newClient, setNewClient] = useState(false)
  const [newHook, setNewHook] = useState(false)
  const [c, setC] = useState({ name: '', type: 'ecommerce', scopes: ['deliveries:create', 'deliveries:read'] as string[] })
  const [h, setH] = useState({ url: 'https://', events: ['delivery.status_updated'] as string[] })
  const [busy, setBusy] = useState(false)
  if (loading || !data) return <Skeleton className="h-96" />
  if (!data.feature_available) return <Alert tone="info" title="API integrations are part of the Enterprise plan">Connect e-commerce stores and warehouse systems with scoped API tokens, idempotent order intake and signed webhooks.</Alert>
  const toggle = (arr: string[], v: string) => arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v]
  return (
    <div className="space-y-5">
      <Card><CardHeader icon={<KeyRound />} title="API clients" description="Machine credentials for e-commerce & WMS. Send orders to POST /api/v1/integrations/deliveries with an Idempotency-Key header." action={<Button size="sm" onClick={() => setNewClient(true)}><Plus /> New client</Button>} />
        <DataTable rows={data.clients} columns={[
          { key: 'n', header: 'Client', cell: (x) => <div><p className="font-semibold">{x.name}</p><p className="text-xs text-muted">{label(x.type)}</p></div> },
          { key: 's', header: 'Scopes', cell: (x) => <div className="flex flex-wrap gap-1">{x.scopes.map((s) => <Badge key={s}>{s}</Badge>)}</div> },
          { key: 'u', header: 'Last used', cell: (x) => x.last_used_at ? dateTime(x.last_used_at) : 'Never', hideOnMobile: true },
          { key: 'a', header: '', className: 'text-right', cell: (x) => x.is_active ? <div className="flex justify-end gap-1">
            <Button size="sm" variant="ghost" onClick={async () => { const r = await api.post<{ token: string }>(`/integrations/clients/${x.id}/rotate`); setSecret({ title: 'New API token', value: r.token }) }}><RefreshCw /> Rotate</Button>
            <ConfirmDialog title="Revoke client?" description="Its tokens stop working immediately." confirmLabel="Revoke" onConfirm={async () => { await api.delete(`/integrations/clients/${x.id}`); void reload() }} trigger={<Button size="icon-sm" variant="ghost" aria-label="Revoke"><Trash2 /></Button>} />
          </div> : <Badge tone="danger">Revoked</Badge> },
        ]} />
      </Card>
      <Card><CardHeader icon={<Webhook />} title="Webhooks" description="Signed with HMAC-SHA256 (X-FleetFlow-Signature: t=…,v1=…). Failed deliveries are retried with backoff." action={<Button size="sm" onClick={() => setNewHook(true)}><Plus /> Add endpoint</Button>} />
        <DataTable rows={data.webhooks} columns={[
          { key: 'u', header: 'URL', cell: (x) => <code className="text-xs">{x.url}</code> },
          { key: 'e', header: 'Events', cell: (x) => <div className="flex flex-wrap gap-1">{x.events.map((s) => <Badge key={s} tone="info">{s}</Badge>)}</div> },
          { key: 'a', header: '', className: 'text-right', cell: (x) => <ConfirmDialog title="Remove webhook?" confirmLabel="Remove" onConfirm={async () => { await api.delete(`/integrations/webhooks/${x.id}`); void reload() }} trigger={<Button size="icon-sm" variant="ghost" aria-label="Remove"><Trash2 /></Button>} /> },
        ]} />
      </Card>
      <Card className="overflow-hidden"><CardHeader icon={<Plug />} title="Webhook delivery log" />
        <DataTable rows={log.rows} loading={log.loading} meta={log.meta} onPage={(p) => log.set({ page: p })} columns={[
          { key: 'e', header: 'Event', cell: (x) => x.event }, { key: 'u', header: 'Endpoint', cell: (x) => <span className="text-xs text-muted">{x.url}</span>, hideOnMobile: true },
          { key: 's', header: 'Status', cell: (x) => <StatusBadge status={x.status === 'delivered' ? 'completed' : x.status === 'failed' ? 'failed' : 'pending'} label={`${x.status}${x.response_status ? ` · ${x.response_status}` : ''}`} /> },
          { key: 'a', header: 'Attempts', cell: (x) => x.attempts }, { key: 't', header: 'Created', cell: (x) => dateTime(x.created_at) },
          { key: 'r', header: '', cell: (x) => x.status !== 'delivered' && <Button size="sm" variant="ghost" onClick={async () => { await api.post(`/integrations/webhook-deliveries/${x.id}/retry`); toast.success('Retry queued'); void log.reload() }}>Retry</Button> },
        ]} />
      </Card>
      <FormModal open={newClient} onOpenChange={setNewClient} title="New API client" size="md" submitting={busy} submitLabel="Create" onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { const r = await api.post<{ token: string }>('/integrations/clients', c); setNewClient(false); setSecret({ title: 'API token', value: r.token }); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label="Name" required><Input value={c.name} onChange={(e) => setC({ ...c, name: e.target.value })} placeholder="Shopify store" /></Field>
        <Field label="Type"><Select value={c.type} onChange={(e) => setC({ ...c, type: e.target.value })}><option value="ecommerce">E-commerce</option><option value="warehouse">Warehouse (WMS)</option><option value="custom">Custom</option></Select></Field>
        <Field label="Scopes"><div className="flex flex-wrap gap-2">{data.scopes.map((s) => <label key={s} className="flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-xs"><input type="checkbox" checked={c.scopes.includes(s)} onChange={() => setC({ ...c, scopes: toggle(c.scopes, s) })} />{s}</label>)}</div></Field>
      </FormModal>
      <FormModal open={newHook} onOpenChange={setNewHook} title="Add webhook endpoint" size="md" submitting={busy} submitLabel="Add" onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { const r = await api.post<{ secret: string }>('/integrations/webhooks', h); setNewHook(false); setSecret({ title: 'Signing secret', value: r.secret }); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label="HTTPS URL" required><Input value={h.url} onChange={(e) => setH({ ...h, url: e.target.value })} /></Field>
        <Field label="Events"><div className="flex flex-wrap gap-2">{['*', ...data.events].map((s) => <label key={s} className="flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-xs"><input type="checkbox" checked={h.events.includes(s)} onChange={() => setH({ ...h, events: toggle(h.events, s) })} />{s === '*' ? 'All events' : s}</label>)}</div></Field>
      </FormModal>
      <Modal open={Boolean(secret)} onOpenChange={() => setSecret(null)} title={secret?.title ?? ''} description="Copy it now — it will not be shown again." footer={<Button onClick={() => { void navigator.clipboard.writeText(secret!.value); toast.success('Copied'); setSecret(null) }}><Copy /> Copy & close</Button>}>
        <code className="block break-all rounded-lg bg-surface-2 p-3 text-xs">{secret?.value}</code>
      </Modal>
    </div>
  )
}

function ActivityTab() {
  const { rows, meta, set, loading } = usePaged<{ id: number; action: string; user: string | null; properties: Record<string, unknown> | null; ip_address: string | null; created_at: string }>('/companies/current/activity')
  return (
    <Card className="overflow-hidden"><DataTable rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} columns={[
      { key: 'w', header: 'When', cell: (x) => dateTime(x.created_at) },
      { key: 'a', header: 'Action', cell: (x) => <code className="text-xs font-semibold">{x.action}</code> },
      { key: 'u', header: 'User', cell: (x) => x.user ?? 'System' },
      { key: 'p', header: 'Details', cell: (x) => <span className="line-clamp-1 max-w-md text-xs text-muted">{x.properties ? JSON.stringify(x.properties) : '—'}</span>, hideOnMobile: true },
      { key: 'i', header: 'IP', cell: (x) => x.ip_address ?? '—', hideOnMobile: true },
    ]} /></Card>
  )
}

export default function SettingsPage() {
  const can = useAuth((s) => s.can)
  const [tab, setTab] = useState<Tab>('company')
  const tabs: { value: Tab; label: string }[] = [
    { value: 'company', label: 'Company' }, { value: 'operations', label: 'Operations' }, { value: 'subscription', label: 'Subscription' },
    ...(can('integrations.manage') ? [{ value: 'integrations' as Tab, label: 'Integrations' }] : []),
    ...(can('company.settings') ? [{ value: 'activity' as Tab, label: 'Audit log' }] : []),
  ]
  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Workspace" title="Settings" description="Company profile, operational rules, subscription and integrations." />
      <Tabs value={tab} onChange={setTab} tabs={tabs} />
      {tab === 'company' ? <CompanyTab /> : tab === 'operations' ? <OperationsTab /> : tab === 'subscription' ? <SubscriptionTab /> : tab === 'integrations' ? <IntegrationsTab /> : <ActivityTab />}
    </div>
  )
}
