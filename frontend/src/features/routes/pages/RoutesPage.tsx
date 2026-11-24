import { Plus, Route as RouteIcon } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Button, Card, DataTable, EmptyState, Field, FilterSelect, FormModal, Input, PageHeader, SearchInput, Select, StatusBadge, Toolbar, type Column } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { date, km, minutes } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver, RoutePlan, Vehicle } from '@/types/domain'

export function NewRouteDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const navigate = useNavigate()
  const drivers = useOptions<Driver>('/drivers', { status: 'active' })
  const vehicles = useOptions<Vehicle>('/vehicles')
  const deliveries = useOptions<Delivery>('/deliveries', { status: 'pending,assigned,accepted', unrouted: 1 })
  const [f, setF] = useState({ name: '', route_date: new Date().toISOString().slice(0, 10), driver_id: '', vehicle_id: '', start_location: '' })
  const [picked, setPicked] = useState<number[]>([])
  const [busy, setBusy] = useState(false)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title="Plan a route" submitLabel="Create route" submitting={busy} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true)
      try {
        const r = await api.post<RoutePlan>('/routes', { ...f, driver_id: f.driver_id || null, vehicle_id: f.vehicle_id || null, delivery_ids: picked })
        toast.success('Route created'); navigate(`/app/routes/${r.id}`)
      } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
    }}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Name" required><Input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} placeholder="Rabat morning run" /></Field>
        <Field label="Date" required><Input type="date" value={f.route_date} onChange={(e) => setF({ ...f, route_date: e.target.value })} /></Field>
        <Field label="Driver"><Select value={f.driver_id} onChange={(e) => setF({ ...f, driver_id: e.target.value })}><option value="">Later</option>{drivers.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}</Select></Field>
        <Field label="Vehicle"><Select value={f.vehicle_id} onChange={(e) => setF({ ...f, vehicle_id: e.target.value })}><option value="">Later</option>{vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_number}</option>)}</Select></Field>
      </div>
      <Field label={`Stops (${picked.length} selected)`} hint="Open deliveries not yet on a route">
        <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-border p-1.5">
          {deliveries.length === 0 && <p className="p-3 text-center text-xs text-muted">No open deliveries to route.</p>}
          {deliveries.map((d) => (
            <label key={d.id} className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-surface-2">
              <input type="checkbox" className="size-4 accent-[var(--primary)]" checked={picked.includes(d.id)} onChange={(e) => setPicked(e.target.checked ? [...picked, d.id] : picked.filter((x) => x !== d.id))} />
              <span className="font-semibold">{d.reference_number}</span>
              <span className="min-w-0 flex-1 truncate text-muted">{d.dropoff.address}</span>
              {!d.dropoff.latitude && <span className="text-[10px] font-semibold text-warning">no pin</span>}
            </label>
          ))}
        </div>
      </Field>
    </FormModal>
  )
}

export default function RoutesPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const { rows, meta, filters, set, loading } = usePaged<RoutePlan>('/routes', { search: '', status: '', date: '' })
  const columns: Column<RoutePlan>[] = [
    { key: 'n', header: 'Route', cell: (r) => <div><p className="font-semibold text-fg">{r.name}</p><p className="text-xs text-muted">{r.reference_number}</p></div> },
    { key: 'd', header: 'Date', cell: (r) => date(r.route_date) },
    { key: 's', header: 'Status', cell: (r) => <StatusBadge status={r.status === 'planned' ? 'scheduled' : r.status} label={r.status.replace('_', ' ')} /> },
    { key: 'dr', header: 'Driver / vehicle', cell: (r) => <span className="text-fg-soft">{r.driver?.name ?? '—'} · {r.vehicle?.registration_number ?? '—'}</span>, hideOnMobile: true },
    { key: 'st', header: 'Stops', cell: (r) => r.stops_count ?? 0 },
    { key: 'km', header: 'Distance', cell: (r) => <span>{km(r.planned_distance)}{r.distance_source === 'straight_line' && <span className="ml-1 text-[10px] text-subtle">(straight line)</span>}</span>, hideOnMobile: true },
    { key: 't', header: 'Duration', cell: (r) => minutes(r.estimated_duration), hideOnMobile: true },
  ]
  return (
    <>
      <PageHeader eyebrow="Operations" title="Routes" description="Multi-stop route plans with distance, ETAs and provider-backed optimisation."
        actions={can('routes.manage') && <Button onClick={() => setOpen(true)}><Plus /> Plan route</Button>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-64" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} />
          <Input type="date" className="h-9 sm:w-40" value={String(filters.date ?? '')} onChange={(e) => set({ date: e.target.value })} />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['planned', 'in_progress', 'completed', 'cancelled'].map((s) => ({ value: s, label: s.replace('_', ' ') }))} />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(r) => navigate(`/app/routes/${r.id}`)}
          empty={<EmptyState icon={<RouteIcon />} title="No routes yet" description="Group deliveries into a route to plan the day for a driver." />} />
      </Card>
      {open && <NewRouteDialog open={open} onOpenChange={setOpen} />}
    </>
  )
}
