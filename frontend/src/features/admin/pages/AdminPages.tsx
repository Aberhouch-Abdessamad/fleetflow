import { Activity, Ban, Building2, CheckCircle2, CreditCard, Database, HardDrive, Package, Pencil, Plus, Radio, Server, Truck, Users, Webhook } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { BarsChart, ChartCard, ShareList, TrendChart } from '@/components/charts/Charts'
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, DataTable, Field, FilterSelect, FormModal, Input, KeyValue, PageHeader, SearchInput, Select, Skeleton, StatCard, StatusBadge, Switch, Textarea, Toolbar,
} from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { usePaged } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { dateTime, label, money, num } from '@/lib/format'
import { ApiError, toApiError } from '@/lib/http'

interface Stats {
  total_companies: number; active_companies: number; pending_companies: number; suspended_companies: number; total_users: number
  total_vehicles: number; total_drivers: number; total_deliveries: number; deliveries_last_30_days: number
  active_subscriptions: number; trialing_subscriptions: number; mrr: string; growth: { month: string; companies: number; deliveries: number }[]; plans: Record<string, number>
}
interface AdminCompany { id: string; name: string; status: string; city: string; country: string; contact_email: string; created_at: string; owner: { name: string; email: string } | null; suspension_reason: string | null; subscription: { plan: string; status: string; current: boolean; trial_ends_at: string | null } | null }
interface Plan { id: number; name: string; code: string; description: string | null; price_monthly: string; currency: string; trial_days: number; max_vehicles: number | null; max_drivers: number | null; max_users: number | null; max_monthly_deliveries: number | null; features: string[]; is_active: boolean; is_default: boolean; sort_order: number; subscribers?: number }

const FEATURES = ['gps_tracking', 'route_planning', 'route_optimization', 'advanced_reports', 'api_integrations', 'priority_support']

export function AdminDashboardPage() {
  const s = useAsync(() => api.get<Stats>('/admin/dashboard'), [])
  const d = s.data
  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Platform" title="Platform overview" description="All tenants, usage and subscriptions." />
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-8">
        <StatCard loading={s.loading} label="Companies" value={num(d?.total_companies)} icon={<Building2 />} />
        <StatCard loading={s.loading} label="Active" value={num(d?.active_companies)} icon={<CheckCircle2 />} tone="success" />
        <StatCard loading={s.loading} label="Suspended" value={num(d?.suspended_companies)} icon={<Ban />} tone="danger" hint={`${num(d?.pending_companies)} pending`} />
        <StatCard loading={s.loading} label="Users" value={num(d?.total_users)} icon={<Users />} tone="info" />
        <StatCard loading={s.loading} label="Vehicles" value={num(d?.total_vehicles)} icon={<Truck />} tone="accent" />
        <StatCard loading={s.loading} label="Drivers" value={num(d?.total_drivers)} icon={<Users />} tone="neutral" />
        <StatCard loading={s.loading} label="Deliveries" value={num(d?.total_deliveries)} icon={<Package />} tone="primary" hint={`${num(d?.deliveries_last_30_days)} last 30 days`} />
        <StatCard loading={s.loading} label="MRR" value={d ? money(d.mrr, 'USD') : null} icon={<CreditCard />} tone="success" hint={d ? `${d.active_subscriptions} subscriptions · ${d.trialing_subscriptions} trials` : undefined} />
      </div>
      <div className="grid gap-4 xl:grid-cols-3">
        <ChartCard className="xl:col-span-2" title="New companies per month" loading={s.loading} empty={!d?.growth.some((g) => g.companies)}>
          <BarsChart data={(d?.growth ?? []).map((g) => ({ date: g.month, companies: g.companies }))} x="date" series={[{ key: 'companies', label: 'New companies' }]} />
        </ChartCard>
        <ChartCard title="Subscriptions by plan" loading={s.loading} empty={!d || !Object.keys(d.plans).length}>
          <ShareList items={Object.entries(d?.plans ?? {}).map(([k, v]) => ({ label: k, value: v }))} />
        </ChartCard>
      </div>
      <SystemHealth />
    </div>
  )
}

export function AdminAnalyticsPage() {
  const s = useAsync(() => api.get<Stats>('/admin/dashboard'), [])
  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Platform" title="Platform analytics" description="Growth of tenants and delivery volume over 12 months." />
      <div className="grid gap-4 xl:grid-cols-2">
        <ChartCard title="Deliveries processed per month" loading={s.loading} empty={!s.data?.growth.some((g) => g.deliveries)}>
          <TrendChart data={(s.data?.growth ?? []).map((g) => ({ date: g.month, deliveries: g.deliveries }))} series={[{ key: 'deliveries', label: 'Deliveries' }]} height={280} />
        </ChartCard>
        <ChartCard title="Company sign-ups per month" loading={s.loading} empty={!s.data?.growth.some((g) => g.companies)}>
          <BarsChart data={(s.data?.growth ?? []).map((g) => ({ date: g.month, companies: g.companies }))} x="date" series={[{ key: 'companies', label: 'Companies' }]} height={280} />
        </ChartCard>
      </div>
    </div>
  )
}

function SystemHealth() {
  const h = useAsync(() => api.get<Record<string, Record<string, unknown> | string>>('/admin/health'), [])
  const items: [string, typeof Database, string][] = [['database', Database, 'Database'], ['queue', Server, 'Queue'], ['scheduler', Activity, 'Scheduler'], ['storage', HardDrive, 'Storage'], ['broadcasting', Radio, 'Real-time'], ['webhooks', Webhook, 'Webhooks']]
  return (
    <Card><CardHeader title="System health" icon={<Server />} description={h.data ? `PHP ${h.data.php} · Laravel ${h.data.laravel}` : undefined} action={<Button size="sm" variant="ghost" onClick={() => void h.reload()}>Refresh</Button>} />
      <div className="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-3">
        {items.map(([k, Icon, l]) => {
          const v = h.data?.[k] as Record<string, unknown> | undefined
          const ok = k === 'scheduler' ? Boolean(v?.ok_recent) : Boolean(v?.ok)
          return (
            <div key={k} className="flex items-start gap-3 bg-surface p-4">
              <Icon className="mt-0.5 size-4 text-subtle" />
              <div className="min-w-0 flex-1 text-sm">
                <div className="flex items-center justify-between font-semibold">{l} {h.loading ? <Skeleton className="h-4 w-12" /> : <Badge dot tone={ok ? 'success' : 'warning'}>{ok ? 'OK' : 'Check'}</Badge>}</div>
                <p className="mt-0.5 truncate text-xs text-muted">{v ? Object.entries(v).filter(([kk]) => !['ok', 'ok_recent'].includes(kk)).map(([kk, vv]) => `${label(kk)}: ${vv ?? '—'}`).join(' · ') : ''}</p>
              </div>
            </div>
          )
        })}
      </div>
    </Card>
  )
}

export function AdminCompaniesPage() {
  const navigate = useNavigate()
  const { rows, meta, filters, set, loading } = usePaged<AdminCompany>('/admin/companies', { search: '', status: '' })
  return (
    <>
      <PageHeader eyebrow="Platform" title="Companies" description="Every tenant workspace on FleetFlow." />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Name or email" />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['active', 'pending', 'suspended'].map((s) => ({ value: s, label: label(s) }))} />
        </Toolbar>
        <DataTable rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(c) => navigate(`/admin/companies/${c.id}`)} columns={[
          { key: 'n', header: 'Company', cell: (c) => <div><p className="font-semibold">{c.name}</p><p className="text-xs text-muted">{c.city}, {c.country} · {c.contact_email}</p></div> },
          { key: 'o', header: 'Owner', cell: (c) => c.owner?.name ?? '—', hideOnMobile: true },
          { key: 'p', header: 'Plan', cell: (c) => c.subscription ? <span>{c.subscription.plan} <span className="text-xs text-muted">· {c.subscription.status}</span></span> : <span className="text-subtle">None</span> },
          { key: 's', header: 'Status', cell: (c) => <StatusBadge status={c.status} /> },
          { key: 'c', header: 'Created', cell: (c) => dateTime(c.created_at), hideOnMobile: true },
        ]} />
      </Card>
    </>
  )
}

export function AdminCompanyPage() {
  const { id } = useParams()
  const c = useAsync(() => (id && id !== 'null'
    ? api.get<AdminCompany & { usage: Record<string, number | string | null>; subscriptions: { id: number; plan: string; status: string; starts_at: string; trial_ends_at: string | null; ends_at: string | null; notes: string | null }[]; recent_activity: { id: number; action: string; user: string | null; created_at: string }[] }>(`/admin/companies/${id}`)
    : Promise.reject(new ApiError('Company not found.', 'not_found', 404))), [id])
  const plans = useAsync(() => api.get<Plan[]>('/admin/plans'), [])
  const [suspend, setSuspend] = useState(false)
  const [plan, setPlan] = useState(false)
  const [reason, setReason] = useState('')
  const [p, setP] = useState({ plan: '', status: 'active', ends_at: '', notes: '' })
  const [busy, setBusy] = useState(false)
  if (c.loading && !c.data) return <Skeleton className="h-96" />
  if (c.error || !c.data) return <Alert tone="danger">{c.error?.message}</Alert>
  const d = c.data
  return (
    <div className="space-y-5">
      <Link to="/admin/companies" className="text-sm text-muted hover:text-fg">← Companies</Link>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><div className="flex items-center gap-2"><h1 className="text-xl font-semibold">{d.name}</h1><StatusBadge status={d.status} /></div><p className="text-sm text-muted">{d.city}, {d.country} · owner {d.owner?.name} ({d.owner?.email})</p></div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setPlan(true)}><CreditCard /> Change plan</Button>
          {d.status === 'suspended' || d.status === 'pending'
            ? <Button onClick={async () => { await api.patch(`/admin/companies/${d.id}/activate`); toast.success('Company activated'); void c.reload() }}><CheckCircle2 /> Activate</Button>
            : <Button variant="danger" onClick={() => setSuspend(true)}><Ban /> Suspend</Button>}
        </div>
      </div>
      {d.suspension_reason && <Alert tone="danger" title="Suspended">{d.suspension_reason}</Alert>}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        {Object.entries(d.usage).filter(([k]) => k !== 'last_activity_at').map(([k, v]) => <StatCard key={k} label={label(k)} value={num(v as number)} />)}
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card><CardHeader title="Subscription history" /><DataTable rows={d.subscriptions} columns={[
          { key: 'p', header: 'Plan', cell: (s) => s.plan }, { key: 's', header: 'Status', cell: (s) => <StatusBadge status={s.status === 'trialing' ? 'scheduled' : s.status} label={s.status} /> },
          { key: 'f', header: 'From', cell: (s) => dateTime(s.starts_at) }, { key: 'e', header: 'Ends', cell: (s) => dateTime(s.trial_ends_at ?? s.ends_at) },
        ]} /></Card>
        <Card><CardHeader title="Recent activity" description="Viewing this page is itself audit-logged" /><DataTable rows={d.recent_activity} columns={[
          { key: 'a', header: 'Action', cell: (x) => <code className="text-xs">{x.action}</code> }, { key: 'u', header: 'User', cell: (x) => x.user ?? 'System' }, { key: 't', header: 'When', cell: (x) => dateTime(x.created_at) },
        ]} /></Card>
      </div>
      <FormModal open={suspend} onOpenChange={setSuspend} title={`Suspend ${d.name}`} size="sm" danger submitLabel="Suspend" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.patch(`/admin/companies/${d.id}/suspend`, { reason }); toast.success('Company suspended'); setSuspend(false); void c.reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <p className="text-sm text-muted">All members lose access to operations immediately. Data is preserved.</p>
        <Field label="Reason" required><Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      </FormModal>
      <FormModal open={plan} onOpenChange={setPlan} title="Change subscription" size="sm" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.put(`/admin/companies/${d.id}/subscription`, { ...p, ends_at: p.ends_at || null }); toast.success('Subscription updated'); setPlan(false); void c.reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <p className="text-xs text-muted">No payment is taken here — record the plan agreed with the customer.</p>
        <Field label="Plan" required><Select value={p.plan} onChange={(e) => setP({ ...p, plan: e.target.value })}><option value="">Select…</option>{plans.data?.filter((x) => x.is_active).map((x) => <option key={x.code} value={x.code}>{x.name} · {money(x.price_monthly, x.currency)}</option>)}</Select></Field>
        <Field label="Status"><Select value={p.status} onChange={(e) => setP({ ...p, status: e.target.value })}><option value="active">Active (paid)</option><option value="trialing">Trial</option></Select></Field>
        <Field label="Ends at" hint="Leave empty for open-ended"><Input type="date" value={p.ends_at} onChange={(e) => setP({ ...p, ends_at: e.target.value })} /></Field>
        <Field label="Notes"><Input value={p.notes} onChange={(e) => setP({ ...p, notes: e.target.value })} /></Field>
      </FormModal>
    </div>
  )
}

export function AdminUsersPage() {
  const { rows, meta, filters, set, loading, reload } = usePaged<{ id: number; name: string; email: string; status: string; is_super_admin: boolean; email_verified: boolean; companies: number; last_login_at: string | null; created_at: string }>('/admin/users', { search: '', status: '' })
  return (
    <>
      <PageHeader eyebrow="Platform" title="Users" description="All accounts across tenants." />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={[{ value: 'active', label: 'Active' }, { value: 'suspended', label: 'Suspended' }]} />
        </Toolbar>
        <DataTable rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} columns={[
          { key: 'n', header: 'User', cell: (u) => <div><p className="flex items-center gap-1.5 font-semibold">{u.name}{u.is_super_admin && <Badge tone="primary">Admin</Badge>}</p><p className="text-xs text-muted">{u.email}{!u.email_verified && ' · unverified'}</p></div> },
          { key: 'c', header: 'Companies', cell: (u) => u.companies },
          { key: 's', header: 'Status', cell: (u) => <StatusBadge status={u.status} /> },
          { key: 'l', header: 'Last sign-in', cell: (u) => dateTime(u.last_login_at), hideOnMobile: true },
          { key: 'a', header: '', className: 'text-right', cell: (u) => !u.is_super_admin && (
            <Button size="sm" variant="ghost" className={u.status === 'active' ? 'text-danger' : ''} onClick={async () => {
              try { await api.patch(`/admin/users/${u.id}/status`, { status: u.status === 'active' ? 'suspended' : 'active' }); toast.success('User updated'); void reload() } catch (e) { toast.error(toApiError(e).message) }
            }}>{u.status === 'active' ? 'Suspend' : 'Reactivate'}</Button>
          ) },
        ]} />
      </Card>
    </>
  )
}

export function AdminPlansPage() {
  const plans = useAsync(() => api.get<Plan[]>('/admin/plans'), [])
  const [edit, setEdit] = useState<Plan | null | 'new'>(null)
  const blank = { name: '', code: '', description: '', price_monthly: '0', currency: 'USD', trial_days: '14', max_vehicles: '', max_drivers: '', max_users: '', max_monthly_deliveries: '', features: [] as string[], is_active: true, is_default: false, sort_order: '0' }
  const [f, setF] = useState(blank)
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    if (edit && edit !== 'new') setF({ ...edit, description: edit.description ?? '', trial_days: String(edit.trial_days), sort_order: String(edit.sort_order), max_vehicles: String(edit.max_vehicles ?? ''), max_drivers: String(edit.max_drivers ?? ''), max_users: String(edit.max_users ?? ''), max_monthly_deliveries: String(edit.max_monthly_deliveries ?? '') })
    if (edit === 'new') setF(blank)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [edit])
  const s = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  return (
    <>
      <PageHeader eyebrow="Platform" title="Subscription plans" description="Limits and features are enforced by the API." actions={<Button onClick={() => setEdit('new')}><Plus /> New plan</Button>} />
      <div className="grid gap-4 lg:grid-cols-3">
        {plans.loading && !plans.data ? [...Array(3)].map((_, i) => <Skeleton key={i} className="h-64" />) : plans.data?.map((p) => (
          <div key={p.id} className="ff-card flex flex-col p-5">
            <div className="flex items-center justify-between"><p className="text-lg font-semibold">{p.name}</p><div className="flex gap-1">{p.is_default && <Badge tone="primary">Default</Badge>}{!p.is_active && <Badge tone="danger">Inactive</Badge>}</div></div>
            <p className="text-xs text-muted">{p.code} · {p.subscribers ?? 0} active subscribers</p>
            <p className="mt-3 text-2xl font-semibold">{money(p.price_monthly, p.currency)}<span className="text-sm font-medium text-muted"> /mo · {p.trial_days}-day trial</span></p>
            <KeyValue className="mt-3" items={[['Vehicles', p.max_vehicles ?? '∞'], ['Drivers', p.max_drivers ?? '∞'], ['Users', p.max_users ?? '∞'], ['Deliveries / month', p.max_monthly_deliveries ?? '∞']]} />
            <div className="mt-3 flex flex-wrap gap-1">{p.features.map((x) => <Badge key={x} tone="success">{label(x)}</Badge>)}</div>
            <Button variant="outline" className="mt-auto pt-0" style={{ marginTop: 16 }} onClick={() => setEdit(p)}><Pencil /> Edit</Button>
          </div>
        ))}
      </div>
      <FormModal open={edit !== null} onOpenChange={(o) => !o && setEdit(null)} title={edit === 'new' ? 'New plan' : 'Edit plan'} submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        const body = { ...f, max_vehicles: f.max_vehicles || null, max_drivers: f.max_drivers || null, max_users: f.max_users || null, max_monthly_deliveries: f.max_monthly_deliveries || null }
        try { if (edit === 'new') await api.post('/admin/plans', body); else await api.put(`/admin/plans/${(edit as Plan).id}`, body); toast.success('Plan saved'); setEdit(null); void plans.reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Name" required><Input value={f.name} onChange={s('name')} /></Field>
          <Field label="Code" required><Input value={f.code} onChange={s('code')} /></Field>
          <Field label="Price / month" required><Input type="number" step="0.01" value={f.price_monthly} onChange={s('price_monthly')} /></Field>
          <Field label="Currency"><Input maxLength={3} value={f.currency} onChange={s('currency')} /></Field>
          <Field label="Trial days"><Input type="number" value={f.trial_days} onChange={s('trial_days')} /></Field>
          <Field label="Sort order"><Input type="number" value={f.sort_order} onChange={s('sort_order')} /></Field>
          <Field label="Max vehicles" hint="Empty = unlimited"><Input type="number" value={f.max_vehicles} onChange={s('max_vehicles')} /></Field>
          <Field label="Max drivers"><Input type="number" value={f.max_drivers} onChange={s('max_drivers')} /></Field>
          <Field label="Max users"><Input type="number" value={f.max_users} onChange={s('max_users')} /></Field>
          <Field label="Max deliveries / month"><Input type="number" value={f.max_monthly_deliveries} onChange={s('max_monthly_deliveries')} /></Field>
          <Field label="Description" className="sm:col-span-2"><Input value={f.description} onChange={s('description')} /></Field>
        </div>
        <Field label="Features"><div className="flex flex-wrap gap-2">{FEATURES.map((x) => <label key={x} className="flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-xs"><input type="checkbox" checked={f.features.includes(x)} onChange={() => setF({ ...f, features: f.features.includes(x) ? f.features.filter((y) => y !== x) : [...f.features, x] })} />{label(x)}</label>)}</div></Field>
        <div className="divide-y divide-border"><Switch checked={f.is_active} onChange={(v) => setF({ ...f, is_active: v })} label="Active" description="Available for new subscriptions" /><Switch checked={f.is_default} onChange={(v) => setF({ ...f, is_default: v })} label="Default plan" description="New companies start a trial on this plan" /></div>
      </FormModal>
    </>
  )
}

export function AdminSubscriptionsPage() {
  const navigate = useNavigate()
  const { rows, meta, filters, set, loading } = usePaged<{ id: number; company: string | null; company_id: string | null; plan: string; price_monthly: string; currency: string; status: string; current: boolean; starts_at: string; trial_ends_at: string | null; ends_at: string | null }>('/admin/subscriptions', { status: '' })
  return (
    <>
      <PageHeader eyebrow="Platform" title="Subscriptions" description="Recorded plan history. Payment gateways are not integrated — no payments are processed." />
      <Card>
        <Toolbar><FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['trialing', 'active', 'expired', 'cancelled'].map((s) => ({ value: s, label: label(s) }))} /></Toolbar>
        <DataTable rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(s) => { if (s.company_id) navigate(`/admin/companies/${s.company_id}`) }} columns={[
          { key: 'c', header: 'Company', cell: (s) => <span className="font-semibold">{s.company ?? '—'}</span> },
          { key: 'p', header: 'Plan', cell: (s) => `${s.plan} · ${money(s.price_monthly, s.currency)}` },
          { key: 's', header: 'Status', cell: (s) => <span className="flex gap-1.5"><StatusBadge status={s.status === 'trialing' ? 'scheduled' : s.status} label={s.status} />{s.current && <Badge tone="success">Current</Badge>}</span> },
          { key: 'f', header: 'Started', cell: (s) => dateTime(s.starts_at), hideOnMobile: true },
          { key: 'e', header: 'Ends', cell: (s) => dateTime(s.trial_ends_at ?? s.ends_at) },
        ]} />
      </Card>
    </>
  )
}

export function AdminActivityPage() {
  const { rows, meta, filters, set, loading } = usePaged<{ id: number; action: string; user: string | null; company: string | null; properties: Record<string, unknown> | null; ip_address: string | null; created_at: string }>('/admin/activity-logs', { action: '' })
  return (
    <>
      <PageHeader eyebrow="System" title="Activity logs" description="Audit trail of important actions across the platform." />
      <Card>
        <Toolbar><SearchInput className="sm:w-72" value={String(filters.action ?? '')} onChange={(v) => set({ action: v })} placeholder="Action prefix, e.g. admin. or delivery." /></Toolbar>
        <DataTable rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} columns={[
          { key: 't', header: 'When', cell: (x) => dateTime(x.created_at) },
          { key: 'a', header: 'Action', cell: (x) => <code className="text-xs font-semibold">{x.action}</code> },
          { key: 'c', header: 'Company', cell: (x) => x.company ?? 'Platform' },
          { key: 'u', header: 'User', cell: (x) => x.user ?? 'System' },
          { key: 'p', header: 'Details', cell: (x) => <span className="line-clamp-1 max-w-sm text-xs text-muted">{x.properties ? JSON.stringify(x.properties) : '—'}</span>, hideOnMobile: true },
        ]} />
      </Card>
    </>
  )
}

export function AdminSettingsPage() {
  const s = useAsync(() => api.get<{ registration_enabled: boolean; require_company_approval: boolean; support_email: string | null; maintenance_banner: string | null }>('/admin/settings'), [])
  const [f, setF] = useState<typeof s.data>(null)
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (s.data) setF(s.data) }, [s.data])
  return (
    <div className="space-y-5">
      <PageHeader eyebrow="System" title="Platform settings" />
      {!f ? <Skeleton className="h-64" /> : (
        <Card><CardHeader title="Registration & onboarding" /><CardBody>
          <div className="divide-y divide-border">
            <Switch checked={f.registration_enabled} onChange={(v) => setF({ ...f, registration_enabled: v })} label="Public registration" description="Allow new users to sign up" />
            <Switch checked={f.require_company_approval} onChange={(v) => setF({ ...f, require_company_approval: v })} label="Require company approval" description="New companies start as pending until a super admin activates them" />
          </div>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <Field label="Support email"><Input type="email" value={f.support_email ?? ''} onChange={(e) => setF({ ...f, support_email: e.target.value })} /></Field>
            <Field label="Maintenance banner"><Input value={f.maintenance_banner ?? ''} onChange={(e) => setF({ ...f, maintenance_banner: e.target.value })} /></Field>
          </div>
          <div className="mt-4 flex justify-end"><Button loading={busy} onClick={async () => { setBusy(true); try { await api.put('/admin/settings', { ...f, support_email: f.support_email || null, maintenance_banner: f.maintenance_banner || null }); toast.success('Saved') } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(false) } }}>Save</Button></div>
        </CardBody></Card>
      )}
      <SystemHealth />
    </div>
  )
}
