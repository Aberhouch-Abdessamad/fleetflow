import { ArrowLeft, Ban, CheckCircle2, Gauge, MapPin, Pencil, Trash2, UserMinus, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { FleetMap } from '@/components/map/FleetMap'
import { DocumentsPanel } from '@/components/shared/DocumentsPanel'
import { GpsHistory } from '@/components/shared/GpsHistory'
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, Field, FormModal, KeyValue, Select, Skeleton, StatCard, StatusBadge, Tabs, Textarea,
} from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useResource } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { ago, date, dateTime, label, money, num } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver, Expense, FuelRecord, Maintenance, Vehicle } from '@/types/domain'
import { VehicleForm } from '../components/VehicleForm'
import { VehicleThumb } from './VehiclesPage'

type Tab = 'overview' | 'assignments' | 'deliveries' | 'maintenance' | 'fuel' | 'expenses' | 'documents' | 'gps'

function HistoryTab({ id, tab }: { id: number; tab: Exclude<Tab, 'overview' | 'documents' | 'gps'> }) {
  const r = useAsync(() => api.get<unknown>(`/vehicles/${id}/history`, { tab }), [id, tab])
  const navigate = useNavigate()
  const loading = r.loading
  if (tab === 'assignments') {
    const rows = (r.data ?? []) as { id: number; driver: { id: number; name: string }; assigned_at: string; unassigned_at: string | null; assigned_by: string | null; active: boolean }[]
    return <DataTable loading={loading} rows={rows} columns={[
      { key: 'd', header: 'Driver', cell: (a) => <Link className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg" to={`/app/drivers/${a.driver.id}`}>{a.driver.name}</Link> },
      { key: 'f', header: 'From', cell: (a) => dateTime(a.assigned_at) },
      { key: 't', header: 'Until', cell: (a) => a.active ? <Badge tone="success" dot>Current</Badge> : dateTime(a.unassigned_at) },
      { key: 'b', header: 'Assigned by', cell: (a) => a.assigned_by ?? '—', hideOnMobile: true },
    ]} />
  }
  if (tab === 'deliveries') {
    return <DataTable loading={loading} rows={(r.data ?? []) as Delivery[]} onRowClick={(d) => navigate(`/app/deliveries/${d.id}`)} columns={[
      { key: 'r', header: 'Reference', cell: (d) => <span className="font-semibold text-fg">{d.reference_number}</span> },
      { key: 's', header: 'Status', cell: (d) => <StatusBadge status={d.status} /> },
      { key: 'dr', header: 'Driver', cell: (d) => d.driver?.name ?? '—', hideOnMobile: true },
      { key: 'to', header: 'Destination', cell: (d) => <span className="line-clamp-1">{d.dropoff.address}</span>, hideOnMobile: true },
      { key: 'c', header: 'Created', cell: (d) => date(d.created_at) },
    ]} />
  }
  if (tab === 'maintenance') {
    return <DataTable loading={loading} rows={(r.data ?? []) as Maintenance[]} columns={[
      { key: 't', header: 'Work', cell: (m) => <div><p className="font-semibold text-fg">{m.title}</p><p className="text-xs text-muted">{label(m.maintenance_type)}</p></div> },
      { key: 's', header: 'Status', cell: (m) => <StatusBadge status={m.overdue ? 'overdue' : m.status} /> },
      { key: 'd', header: 'Date', cell: (m) => date(m.completed_date ?? m.scheduled_date) },
      { key: 'p', header: 'Provider', cell: (m) => m.service_provider ?? '—', hideOnMobile: true },
      { key: 'c', header: 'Cost', cell: (m) => money(m.cost), className: 'text-right' },
    ]} />
  }
  if (tab === 'fuel') {
    const data = r.data as { records: FuelRecord[]; efficiency: { l_per_100km: number | null; cost_per_km: string | null; distance_km: number | null } } | null
    return (
      <div>
        <div className="grid gap-3 border-b border-border p-4 sm:grid-cols-3">
          <StatCard label="Consumption" value={data?.efficiency.l_per_100km ? `${data.efficiency.l_per_100km} L/100km` : '—'} hint="Full-to-full fills" tone="accent" loading={loading} />
          <StatCard label="Fuel cost per km" value={data?.efficiency.cost_per_km ? money(data.efficiency.cost_per_km) : '—'} tone="primary" loading={loading} />
          <StatCard label="Measured distance" value={data?.efficiency.distance_km ? `${num(data.efficiency.distance_km)} km` : '—'} tone="neutral" loading={loading} />
        </div>
        <DataTable loading={loading} rows={data?.records ?? []} columns={[
          { key: 'd', header: 'Date', cell: (f) => dateTime(f.recorded_at) },
          { key: 'q', header: 'Quantity', cell: (f) => `${num(f.quantity, 2)} L${f.full_tank ? '' : ' (partial)'}` },
          { key: 'p', header: 'Unit price', cell: (f) => money(f.unit_price), hideOnMobile: true },
          { key: 'o', header: 'Odometer', cell: (f) => `${num(f.odometer)} km`, hideOnMobile: true },
          { key: 's', header: 'Station', cell: (f) => f.station_name ?? '—', hideOnMobile: true },
          { key: 't', header: 'Total', cell: (f) => <span className="font-semibold">{money(f.total_cost)}</span>, className: 'text-right' },
        ]} />
      </div>
    )
  }
  return <DataTable loading={loading} rows={(r.data ?? []) as Expense[]} columns={[
    { key: 'd', header: 'Date', cell: (e) => date(e.expense_date) },
    { key: 'c', header: 'Category', cell: (e) => label(e.category) },
    { key: 'ds', header: 'Description', cell: (e) => e.description, hideOnMobile: true },
    { key: 's', header: 'Status', cell: (e) => <StatusBadge status={e.status} /> },
    { key: 'a', header: 'Amount', cell: (e) => <span className="font-semibold">{money(e.amount)}</span>, className: 'text-right' },
  ]} />
}

function AssignDriverDialog({ vehicle, open, onOpenChange, onDone }: { vehicle: Vehicle; open: boolean; onOpenChange: (o: boolean) => void; onDone: () => void }) {
  const drivers = useAsync(() => api.page<Driver>('/drivers', { status: 'active', per_page: 100 }), [open])
  const [driverId, setDriverId] = useState('')
  const [notes, setNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title={`Assign a driver to ${vehicle.registration_number}`} size="md" submitting={busy} submitLabel="Assign driver"
      description="Licence category and expiry are validated. With exclusive assignment enabled, a vehicle has one driver at a time."
      onSubmit={async (e) => {
        e.preventDefault()
        setBusy(true); setError(null)
        try {
          await api.post(`/vehicles/${vehicle.id}/assign`, { driver_id: Number(driverId), notes })
          toast.success('Driver assigned')
          onOpenChange(false); onDone()
        } catch (err) { setError(toApiError(err).message) } finally { setBusy(false) }
      }}>
      {error && <Alert tone="danger">{error}</Alert>}
      <Field label="Driver" required>
        <Select value={driverId} onChange={(e) => setDriverId(e.target.value)}>
          <option value="">Select a driver…</option>
          {drivers.data?.data.map((d) => <option key={d.id} value={d.id} disabled={!d.license_valid}>{d.name} · licence {d.license_type}{d.vehicle ? ` · on ${d.vehicle.registration_number}` : ''}{!d.license_valid ? ' (expired)' : ''}</option>)}
        </Select>
      </Field>
      <Field label="Notes"><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
    </FormModal>
  )
}

export default function VehicleDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const { data: v, loading, error, reload } = useResource<Vehicle>(`/vehicles/${id}`)
  const [tab, setTab] = useState<Tab>('overview')
  const [edit, setEdit] = useState(false)
  const [assign, setAssign] = useState(false)

  if (loading && !v) return <div className="space-y-4"><Skeleton className="h-24" /><Skeleton className="h-96" /></div>
  if (error || !v) return <Alert tone="danger" title="Vehicle not found">{error?.message}</Alert>

  const setStatus = async (status: string) => {
    try { await api.patch(`/vehicles/${v.id}/status`, { status }); toast.success('Status updated'); void reload() } catch (e) { toast.error(toApiError(e).message) }
  }
  const expiring = (d: string | null) => d && new Date(d) < new Date(Date.now() + 30 * 864e5)

  return (
    <div className="space-y-5">
      <Link to="/app/vehicles" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Vehicles</Link>

      <Card className="overflow-hidden">
        <div className="relative flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
          <div className="relative [&_img]:size-14 [&_span]:size-14 [&_span_svg]:size-6"><VehicleThumb v={v} /></div>
          <div className="relative min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-xl font-semibold">{v.registration_number}</h1>
              <StatusBadge status={v.status} />
              {v.archived && <Badge tone="danger">Archived</Badge>}
            </div>
            <p className="mt-0.5 text-[0.8125rem] text-muted">{v.make} {v.model}{v.year ? ` · ${v.year}` : ''} · {v.type?.name} · {num(v.capacity)} {v.capacity_unit}</p>
            <p className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.8125rem] text-fg-soft">
              <span className="inline-flex items-center gap-1.5"><Gauge className="size-4 text-subtle" /> {num(v.odometer)} km</span>
              <span className="inline-flex items-center gap-1.5"><UserPlus className="size-4 text-subtle" /> {v.current_driver ? <Link to={`/app/drivers/${v.current_driver.id}`} className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg">{v.current_driver.name}</Link> : 'No driver assigned'}</span>
              {v.location && <span className="inline-flex items-center gap-1.5"><MapPin className="size-4 text-subtle" /> {v.location.freshness === 'live' ? 'Live' : 'Last seen'} {ago(v.location.recorded_at)}</span>}
            </p>
          </div>
          <div className="relative flex flex-wrap gap-2">
            {can('vehicles.assign') && (v.current_driver
              ? <ConfirmDialog title="Unassign driver?" description={`${v.current_driver.name} will no longer be assigned to this vehicle.`} confirmLabel="Unassign"
                  onConfirm={async () => { try { await api.post(`/vehicles/${v.id}/unassign`); toast.success('Driver unassigned'); void reload() } catch (e) { toast.error(toApiError(e).message) } }}
                  trigger={<Button variant="outline"><UserMinus /> Unassign</Button>} />
              : <Button variant="outline" onClick={() => setAssign(true)}><UserPlus /> Assign driver</Button>)}
            {can('vehicles.manage') && <>
              <Button variant="outline" onClick={() => setEdit(true)}><Pencil /> Edit</Button>
              {['out_of_service', 'inactive'].includes(v.status)
                ? <Button variant="outline" onClick={() => setStatus('available')}><CheckCircle2 /> Return to service</Button>
                : <ConfirmDialog title="Mark out of service?" description="The vehicle will not be assignable until returned to service." confirmLabel="Mark out of service" onConfirm={() => setStatus('out_of_service')} trigger={<Button variant="outline"><Ban /> Out of service</Button>} />}
              <ConfirmDialog title="Archive vehicle?" description="Archived vehicles keep their history but no longer appear in the fleet." confirmLabel="Archive"
                onConfirm={async () => { try { await api.delete(`/vehicles/${v.id}`); toast.success('Vehicle archived'); navigate('/app/vehicles') } catch (e) { toast.error(toApiError(e).message) } }}
                trigger={<Button variant="ghost" size="icon" aria-label="Archive"><Trash2 /></Button>} />
            </>}
          </div>
        </div>
        <Tabs className="px-4" value={tab} onChange={setTab} tabs={[
          { value: 'overview', label: 'Overview' }, { value: 'assignments', label: 'Assignments' }, { value: 'deliveries', label: 'Deliveries' },
          { value: 'maintenance', label: 'Maintenance' }, { value: 'fuel', label: 'Fuel' }, { value: 'expenses', label: 'Expenses' },
          { value: 'documents', label: 'Documents' }, { value: 'gps', label: 'GPS history' },
        ]} />
      </Card>

      {tab === 'overview' ? (
        <div className="grid gap-4 lg:grid-cols-3">
          <Card><CardHeader title="Specifications" /><CardBody className="py-2">
            <KeyValue items={[['Type', v.type?.name], ['Make / model', `${v.make} ${v.model}`], ['Year', v.year], ['Color', v.color], ['VIN', v.vin], ['Fuel', label(v.fuel_type)], ['Capacity', `${num(v.capacity)} ${v.capacity_unit}`], ['Odometer', `${num(v.odometer)} km`]]} />
          </CardBody></Card>
          <Card><CardHeader title="Compliance & service" /><CardBody className="py-2">
            <KeyValue items={[
              ['Insurance', v.insurance_provider ?? '—'], ['Policy', v.insurance_policy_number],
              ['Insurance expiry', <span className={expiring(v.insurance_expiry_date) ? 'text-danger' : ''}>{date(v.insurance_expiry_date)}</span>],
              ['Registration expiry', <span className={expiring(v.registration_expiry_date) ? 'text-danger' : ''}>{date(v.registration_expiry_date)}</span>],
              ['Last service', date(v.last_service_date)], ['Next service', date(v.next_service_date)],
              ['Next service at', v.next_service_odometer ? `${num(v.next_service_odometer)} km` : '—'],
            ]} />
          </CardBody></Card>
          <Card className="overflow-hidden"><CardHeader title="Last known position" description={v.location ? `${v.location.freshness === 'live' ? 'Live' : 'Stale'} · ${dateTime(v.location.recorded_at)}` : 'No GPS data yet'} />
            <div className="h-64 p-3">
              {v.location ? <FleetMap markers={[{ id: v.id, lat: v.location.latitude, lng: v.location.longitude, kind: 'vehicle', tone: v.location.freshness === 'live' ? 'live' : 'stale', heading: v.location.heading }]} />
                : <div className="grid h-full place-items-center rounded-xl border border-dashed border-border text-center text-sm text-muted"><p className="max-w-52">Position appears when a driver shares location from the driver app.</p></div>}
            </div>
          </Card>
          {v.notes && <Card className="lg:col-span-3"><CardHeader title="Notes" /><CardBody className="whitespace-pre-line text-sm text-fg-soft">{v.notes}</CardBody></Card>}
        </div>
      ) : (
        <Card className="overflow-hidden">
          {tab === 'documents' ? <DocumentsPanel url={`/vehicles/${v.id}/documents`} canManage={can('vehicles.manage')} deletable />
            : tab === 'gps' ? <GpsHistory vehicleId={v.id} />
            : <HistoryTab key={tab} id={v.id} tab={tab} />}
        </Card>
      )}

      <VehicleForm open={edit} onOpenChange={setEdit} vehicle={v} onSaved={() => void reload()} />
      <AssignDriverDialog vehicle={v} open={assign} onOpenChange={setAssign} onDone={() => void reload()} />
    </div>
  )
}
