import { Clock, MapPin, Plus, RefreshCw, Truck, UserRound, Weight } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Badge, Button, FilterSelect, Input, PageHeader, SearchInput, Skeleton } from '@/components/ui'
import { PriorityBadge } from '@/features/deliveries/pages/DeliveriesPage'
import { useAsync } from '@/hooks/useAsync'
import { useInterval } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { label, num, time } from '@/lib/format'
import { realtimeEnabled, useChannel } from '@/lib/realtime'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver, Vehicle } from '@/types/domain'
import { AssignDialog } from '../components/AssignDialog'

interface Board { date: string; columns: { key: string; count: number; deliveries: Delivery[] }[] }

const COLS: Record<string, { title: string; accent: string }> = {
  unassigned: { title: 'Unassigned', accent: 'bg-warning' },
  assigned: { title: 'Assigned', accent: 'bg-info' },
  accepted: { title: 'Accepted', accent: 'bg-primary' },
  picked_up: { title: 'Picked up', accent: 'bg-primary' },
  in_transit: { title: 'In transit', accent: 'bg-accent' },
  completed: { title: 'Completed', accent: 'bg-success' },
  failed: { title: 'Failed', accent: 'bg-danger' },
}

function DeliveryCard({ d, onAssign }: { d: Delivery; onAssign?: () => void }) {
  return (
    <div className="group rounded-xl border border-border bg-surface p-3 shadow-xs transition-all hover:-translate-y-px hover:border-border-strong hover:shadow-card">
      <div className="flex items-start justify-between gap-2">
        <Link to={`/app/deliveries/${d.id}`} className="text-[13px] font-semibold text-fg hover:text-primary">{d.reference_number}</Link>
        <div className="flex gap-1"><PriorityBadge p={d.priority} />{d.is_late && <Badge tone="danger">Late</Badge>}</div>
      </div>
      <p className="mt-1.5 line-clamp-2 flex gap-1.5 text-xs text-fg-soft"><MapPin className="mt-0.5 size-3 shrink-0 text-subtle" />{d.dropoff.address}</p>
      <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
        {d.scheduled_delivery_at && <span className="inline-flex items-center gap-1"><Clock className="size-3" />{time(d.scheduled_delivery_at)}</span>}
        <span className="inline-flex items-center gap-1"><Weight className="size-3" />{num(d.total_weight, 1)} kg</span>
        {d.status === 'arrived' && <Badge tone="accent">Arrived</Badge>}
      </div>
      {d.driver ? (
        <div className="mt-2.5 flex items-center justify-between gap-2 border-t border-border pt-2 text-xs">
          <span className="inline-flex items-center gap-1.5 font-medium text-fg-soft"><UserRound className="size-3.5 text-subtle" />{d.driver.name}</span>
          <span className="inline-flex items-center gap-1 text-muted"><Truck className="size-3.5" />{d.vehicle?.registration_number}</span>
        </div>
      ) : onAssign && (
        <Button size="sm" variant="outline" className="mt-2.5 w-full" onClick={onAssign}>Assign</Button>
      )}
      {['assigned', 'accepted'].includes(d.status) && onAssign && <button className="mt-1.5 w-full text-center text-[11px] font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100" onClick={onAssign}>Reassign</button>}
    </div>
  )
}

export default function DispatchBoardPage() {
  const navigate = useNavigate()
  const { can, user } = useAuth()
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [f, setF] = useState({ search: '', priority: '', driver_id: '', vehicle_id: '' })
  const board = useAsync(() => api.get<Board>('/dispatch/board', { date, ...f }), [date, f.search, f.priority, f.driver_id, f.vehicle_id])
  const drivers = useOptions<Driver>('/drivers', { status: 'active' })
  const vehicles = useOptions<Vehicle>('/vehicles')
  const [assigning, setAssigning] = useState<Delivery | null>(null)

  useChannel(user?.current_company ? `company.${user.current_company.id}` : null, ['DeliveryStatusUpdated', 'DriverAssignmentUpdated'], () => void board.reload())
  useInterval(() => void board.reload(), realtimeEnabled ? null : 20_000)

  return (
    <div className="flex h-full flex-col">
      <PageHeader eyebrow="Operations" title="Dispatch board" description={`Live delivery operations · ${realtimeEnabled ? 'real-time' : 'auto-refresh every 20 s'}`}
        actions={<>
          <Button variant="outline" onClick={() => void board.reload()}><RefreshCw className={board.loading ? 'animate-spin' : ''} /> Refresh</Button>
          {can('deliveries.create') && <Button onClick={() => navigate('/app/deliveries/new')}><Plus /> New delivery</Button>}
        </>} />
      <div className="ff-card mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:flex-wrap sm:items-center">
        <SearchInput className="sm:w-64" value={f.search} onChange={(v) => setF({ ...f, search: v })} placeholder="Reference, recipient, address…" />
        <Input type="date" className="h-9 sm:w-40" value={date} onChange={(e) => setDate(e.target.value)} aria-label="Date" />
        <FilterSelect placeholder="Any priority" value={f.priority} onChange={(v) => setF({ ...f, priority: v })} options={['urgent', 'high', 'normal', 'low'].map((p) => ({ value: p, label: label(p) }))} />
        <FilterSelect placeholder="All drivers" value={f.driver_id} onChange={(v) => setF({ ...f, driver_id: v })} options={drivers.map((d) => ({ value: String(d.id), label: d.name }))} />
        <FilterSelect placeholder="All vehicles" value={f.vehicle_id} onChange={(v) => setF({ ...f, vehicle_id: v })} options={vehicles.map((v) => ({ value: String(v.id), label: v.registration_number }))} />
      </div>
      <div className="-mx-4 flex flex-1 gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        {(board.data?.columns ?? Object.keys(COLS).map((key) => ({ key, count: 0, deliveries: [] as Delivery[] }))).map((col) => (
          <section key={col.key} className="flex w-72 shrink-0 flex-col rounded-xl border border-border bg-surface-2/70">
            <header className="flex items-center gap-2 px-3.5 py-3">
              <span className={cn('size-2 rounded-full', COLS[col.key]?.accent)} />
              <h2 className="text-sm font-semibold">{COLS[col.key]?.title ?? label(col.key)}</h2>
              <span className="ml-auto rounded-full bg-surface px-2 py-0.5 text-xs font-semibold text-muted ring-1 ring-border">{col.count}</span>
            </header>
            <div className="flex-1 space-y-2 overflow-y-auto px-2.5 pb-3" style={{ maxHeight: 'calc(100dvh - 300px)' }}>
              {board.loading && !board.data ? [...Array(3)].map((_, i) => <Skeleton key={i} className="h-28 rounded-xl" />)
                : col.deliveries.length === 0 ? <p className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-subtle">Nothing here</p>
                : col.deliveries.map((d) => <DeliveryCard key={d.id} d={d} onAssign={can('deliveries.assign') && ['pending', 'assigned', 'accepted'].includes(d.status) ? () => setAssigning(d) : undefined} />)}
            </div>
          </section>
        ))}
      </div>
      {assigning && <AssignDialog delivery={assigning} open onOpenChange={(o) => !o && setAssigning(null)} onAssigned={() => void board.reload()} />}
    </div>
  )
}
