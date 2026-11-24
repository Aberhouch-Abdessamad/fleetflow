import { ArrowLeft, CalendarClock, Mail, Pencil, Phone, Send, Star, Truck } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { DocumentsPanel } from '@/components/shared/DocumentsPanel'
import {
  Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, DataTable, Field, FormModal, Input, KeyValue, Select, Skeleton, StatCard, StatusBadge, Tabs,
} from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useResource } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { date, dateTime, km, minutes, pct } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver } from '@/types/domain'
import { DriverForm } from '../components/DriverForm'
import { AVAILABILITY } from './DriversPage'

interface Perf {
  total_deliveries: number; completed_deliveries: number; failed_deliveries: number; on_time_deliveries: number; on_time_rate: number | null
  success_rate: number | null; average_delivery_minutes: number | null; distance_km: number | null; average_rating: number | null; ratings_count: number; working_hours: number
}
type Tab = 'overview' | 'deliveries' | 'availability' | 'shifts' | 'vehicles' | 'documents'

function AvailabilityDialog({ driver, open, onOpenChange, onDone }: { driver: Driver; open: boolean; onOpenChange: (o: boolean) => void; onDone: () => void }) {
  const [form, setForm] = useState({ availability: driver.availability, starts_at: '', ends_at: '', reason: '' })
  const [busy, setBusy] = useState(false)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title="Update availability" size="md" submitting={busy} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true)
      try {
        await api.put(`/drivers/${driver.id}/availability`, { ...form, starts_at: form.starts_at || null, ends_at: form.ends_at || null })
        toast.success('Availability updated'); onOpenChange(false); onDone()
      } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
    }}>
      <Field label="Availability" required><Select value={form.availability} onChange={(e) => setForm({ ...form, availability: e.target.value as Driver['availability'] })}>{AVAILABILITY.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}</Select></Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="From" hint="Leave empty for now"><Input type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} /></Field>
        <Field label="Until"><Input type="datetime-local" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} /></Field>
      </div>
      <Field label="Reason"><Input value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder="Annual leave, training…" /></Field>
    </FormModal>
  )
}

export default function DriverDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const { data: d, loading, error, reload } = useResource<Driver>(`/drivers/${id}`)
  const perf = useResource<Perf>(`/drivers/${id}/performance`, { range: 'last_90_days' })
  const [tab, setTab] = useState<Tab>('overview')
  const [edit, setEdit] = useState(false)
  const [avail, setAvail] = useState(false)
  const deliveries = useAsync(() => tab === 'deliveries' ? api.page<Delivery>(`/drivers/${id}/deliveries`, { per_page: 50 }) : Promise.resolve(null), [tab, id])
  const history = useAsync(() => (['availability', 'shifts', 'vehicles'] as Tab[]).includes(tab)
    ? api.get<Record<string, unknown>[]>(`/drivers/${id}/${tab === 'vehicles' ? 'assignments' : tab}`) : Promise.resolve(null), [tab, id])

  if (loading && !d) return <div className="space-y-4"><Skeleton className="h-28" /><Skeleton className="h-96" /></div>
  if (error || !d) return <Alert tone="danger" title="Driver not found">{error?.message}</Alert>
  const p = perf.data

  const invite = async () => {
    if (!d.email) { toast.error('Add an email address to the driver profile first.'); return }
    try {
      await api.post('/team/invitations', { email: d.email, role: 'driver', driver_id: d.id })
      toast.success(`Invitation sent to ${d.email}`)
    } catch (e) { toast.error(toApiError(e).message) }
  }

  return (
    <div className="space-y-5">
      <Link to="/app/drivers" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Drivers</Link>
      <Card className="overflow-hidden">
        <div className="relative flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
          <Avatar name={d.name} src={fileUrl(d.photo_url)} className="relative size-14 text-base" />
          <div className="relative min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-xl font-semibold">{d.name}</h1>
              <StatusBadge status={d.status} />
              <StatusBadge status={d.on_shift ? 'on_duty' : d.availability} />
              {!d.license_valid && <Badge tone="danger">Licence expired</Badge>}
            </div>
            <p className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.8125rem] text-fg-soft">
              <span className="inline-flex items-center gap-1.5"><Phone className="size-4 text-subtle" /> {d.phone}</span>
              {d.email && <span className="inline-flex items-center gap-1.5"><Mail className="size-4 text-subtle" /> {d.email}</span>}
              <span className="inline-flex items-center gap-1.5"><Truck className="size-4 text-subtle" /> {d.vehicle ? <Link className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg" to={`/app/vehicles/${d.vehicle.id}`}>{d.vehicle.label}</Link> : 'No vehicle'}</span>
            </p>
          </div>
          {can('drivers.manage') && (
            <div className="relative flex flex-wrap gap-2">
              {!d.has_account && can('users.invite') && <Button variant="outline" onClick={invite}><Send /> Invite to app</Button>}
              <Button variant="outline" onClick={() => setAvail(true)}><CalendarClock /> Availability</Button>
              <Button variant="outline" onClick={() => setEdit(true)}><Pencil /> Edit</Button>
              <Select className="w-32" value={d.status} onChange={async (e) => {
                try { await api.patch(`/drivers/${d.id}/status`, { status: e.target.value }); toast.success('Status updated'); void reload() } catch (err) { toast.error(toApiError(err).message) }
              }}>
                <option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option>
              </Select>
            </div>
          )}
        </div>
        <Tabs className="px-4" value={tab} onChange={setTab} tabs={[
          { value: 'overview', label: 'Overview' }, { value: 'deliveries', label: 'Deliveries' }, { value: 'availability', label: 'Availability' },
          { value: 'shifts', label: 'Working hours' }, { value: 'vehicles', label: 'Vehicles' }, ...(can('drivers.view_private') ? [{ value: 'documents' as Tab, label: 'Documents' }] : []),
        ]} />
      </Card>

      {tab === 'overview' && (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Deliveries (90 days)" value={p?.total_deliveries} loading={perf.loading} hint={p ? `${p.completed_deliveries} completed · ${p.failed_deliveries} failed` : undefined} />
            <StatCard label="Success rate" value={pct(p?.success_rate)} tone="success" loading={perf.loading} />
            <StatCard label="On-time rate" value={pct(p?.on_time_rate)} tone="accent" loading={perf.loading} hint={p ? `${p.on_time_deliveries} on time` : undefined} />
            <StatCard label="Customer rating" value={p?.average_rating ? <span className="inline-flex items-center gap-1">{p.average_rating}<Star className="size-5 fill-warning text-warning" /></span> : '—'} tone="warning" loading={perf.loading} hint={p ? `${p.ratings_count} ratings` : undefined} />
            <StatCard label="Average delivery time" value={minutes(p?.average_delivery_minutes)} tone="info" loading={perf.loading} />
            <StatCard label="Distance (GPS)" value={km(p?.distance_km)} tone="neutral" loading={perf.loading} />
            <StatCard label="Working hours" value={p ? `${p.working_hours} h` : '—'} tone="primary" loading={perf.loading} />
            <StatCard label="Active deliveries" value={d.active_deliveries_count ?? 0} tone="warning" />
          </div>
          <div className="grid gap-4 lg:grid-cols-2">
            <Card><CardHeader title="Licence & employment" /><CardBody className="py-2">
              <KeyValue items={[['Category', <Badge>{d.license_type}</Badge>], ...(d.license_number ? [['Licence number', d.license_number] as [string, string]] : []),
                ['Licence expiry', <span className={d.license_valid ? '' : 'text-danger'}>{date(d.license_expiry_date)}</span>], ['Employed since', date(d.employment_date)], ['App account', d.has_account ? 'Linked' : 'Not invited']]} />
            </CardBody></Card>
            {d.national_id !== undefined && (
              <Card><CardHeader title="Private information" description="Visible only to authorised managers" /><CardBody className="py-2">
                <KeyValue items={[['National ID', d.national_id], ['Date of birth', date(d.date_of_birth)], ['Address', d.address], ['Emergency contact', d.emergency_contact], ['Emergency phone', d.emergency_phone]]} />
              </CardBody></Card>
            )}
          </div>
        </>
      )}

      {tab !== 'overview' && (
        <Card className="overflow-hidden">
          {tab === 'documents' ? <DocumentsPanel url={`/drivers/${d.id}/documents`} canManage={can('drivers.manage')} />
            : tab === 'deliveries' ? <DataTable loading={deliveries.loading} rows={deliveries.data?.data ?? []} onRowClick={(x) => navigate(`/app/deliveries/${x.id}`)} columns={[
                { key: 'r', header: 'Reference', cell: (x) => <span className="font-semibold">{x.reference_number}</span> },
                { key: 's', header: 'Status', cell: (x) => <StatusBadge status={x.status} /> },
                { key: 'v', header: 'Vehicle', cell: (x) => x.vehicle?.registration_number ?? '—', hideOnMobile: true },
                { key: 'o', header: 'On time', cell: (x) => x.on_time === null ? '—' : x.on_time ? <Badge tone="success">Yes</Badge> : <Badge tone="danger">Late</Badge> },
                { key: 'c', header: 'Date', cell: (x) => date(x.created_at) },
              ]} />
            : tab === 'availability' ? <DataTable loading={history.loading} rows={(history.data ?? []) as { id: number; status: string; starts_at: string; ends_at: string | null; reason: string | null }[]} columns={[
                { key: 's', header: 'Status', cell: (x) => <StatusBadge status={x.status} /> },
                { key: 'f', header: 'From', cell: (x) => dateTime(x.starts_at) },
                { key: 't', header: 'Until', cell: (x) => x.ends_at ? dateTime(x.ends_at) : 'Open' },
                { key: 'r', header: 'Reason', cell: (x) => x.reason ?? '—' },
              ]} />
            : tab === 'shifts' ? <DataTable loading={history.loading} rows={(history.data ?? []) as { id: number; started_at: string; ended_at: string | null; duration_minutes: number | null }[]} columns={[
                { key: 'f', header: 'Started', cell: (x) => dateTime(x.started_at) },
                { key: 't', header: 'Ended', cell: (x) => x.ended_at ? dateTime(x.ended_at) : <Badge tone="success" dot>On shift</Badge> },
                { key: 'd', header: 'Duration', cell: (x) => minutes(x.duration_minutes) },
              ]} />
            : <DataTable loading={history.loading} rows={(history.data ?? []) as { id: number; vehicle: { id: number; label: string }; assigned_at: string; unassigned_at: string | null; active: boolean }[]} columns={[
                { key: 'v', header: 'Vehicle', cell: (x) => <Link className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg" to={`/app/vehicles/${x.vehicle.id}`}>{x.vehicle.label}</Link> },
                { key: 'f', header: 'From', cell: (x) => dateTime(x.assigned_at) },
                { key: 't', header: 'Until', cell: (x) => x.active ? <Badge tone="success" dot>Current</Badge> : dateTime(x.unassigned_at) },
              ]} />}
        </Card>
      )}
      <DriverForm open={edit} onOpenChange={setEdit} driver={d} onSaved={() => void reload()} />
      <AvailabilityDialog driver={d} open={avail} onOpenChange={setAvail} onDone={() => void reload()} />
    </div>
  )
}
