import { ArrowDown, ArrowLeft, ArrowUp, CheckCircle2, Flag, Play, Plus, Sparkles, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { FleetMap, escapeHtml, type MapMarker } from '@/components/map/FleetMap'
import { Alert, Badge, Button, Card, CardBody, CardHeader, Field, FormModal, KeyValue, Select, Skeleton, StatCard, StatusBadge, Tooltip } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useOptions, useResource } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { date, dateTime, km, minutes, num, time } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver, RoutePlan, Vehicle } from '@/types/domain'

function geometryLine(r: RoutePlan): [number, number][] | undefined {
  const g = r.geometry as { type?: string; coordinates?: [number, number][] } | null
  if (g?.type === 'LineString' && g.coordinates?.length) return g.coordinates.map(([lng, lat]) => [lat, lng])
  return undefined
}

export default function RoutePlannerPage() {
  const { id } = useParams()
  const can = useAuth((s) => s.can)
  const { data: r, loading, error, reload, setData } = useResource<RoutePlan>(`/routes/${id}`)
  const maps = useAsync(() => api.get<{ optimization: boolean; provider: string | null }>('/maps/config'), [])
  const drivers = useOptions<Driver>('/drivers', { status: 'active' })
  const vehicles = useOptions<Vehicle>('/vehicles')
  const open = useOptions<Delivery>('/deliveries', { status: 'pending,assigned,accepted', unrouted: 1 })
  const [busy, setBusy] = useState<string | null>(null)
  const [adding, setAdding] = useState(false)
  const [picked, setPicked] = useState<number[]>([])

  const run = async (key: string, fn: () => Promise<RoutePlan>, msg: string) => {
    setBusy(key)
    try { setData(await fn()); toast.success(msg) } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(null) }
  }

  const markers = useMemo<MapMarker[]>(() => {
    if (!r) return []
    const m: MapMarker[] = (r.stops ?? []).filter((s) => s.latitude && s.longitude).map((s) => ({
      id: s.id, lat: s.latitude!, lng: s.longitude!, kind: 'stop', label: String(s.stop_order),
      tone: s.status === 'completed' ? 'live' : s.status === 'skipped' ? 'muted' : s.priority === 'urgent' ? 'danger' : 'primary',
      popup: `<b>${s.stop_order}. ${escapeHtml(s.delivery?.reference_number ?? '')}</b><br/>${escapeHtml(s.address)}`,
    }))
    if (r.start.latitude && r.start.longitude) m.unshift({ id: 'start', lat: r.start.latitude, lng: r.start.longitude, kind: 'pickup', tone: 'accent', popup: 'Start' })
    return m
  }, [r])
  const line = useMemo(() => r ? (geometryLine(r) ?? markers.map((m) => [m.lat, m.lng] as [number, number])) : [], [r, markers])

  if (loading && !r) return <Skeleton className="h-[600px]" />
  if (error || !r) return <Alert tone="danger" title="Route not found">{error?.message}</Alert>
  const editable = can('routes.manage') && ['planned', 'in_progress'].includes(r.status)
  const stops = r.stops ?? []
  const move = (i: number, dir: -1 | 1) => {
    const ids = stops.map((s) => s.id)
    ;[ids[i], ids[i + dir]] = [ids[i + dir], ids[i]]
    void run('order', () => api.put<RoutePlan>(`/routes/${r.id}`, { stop_order: ids }), 'Stops reordered')
  }
  const capacity = Number(r.vehicle?.capacity ?? 0)
  const overweight = capacity > 0 && (r.total_weight ?? 0) > capacity

  return (
    <div className="space-y-5">
      <Link to="/app/routes" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Routes</Link>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div className="flex items-center gap-2.5"><h1 className="text-xl font-semibold">{r.name}</h1><StatusBadge status={r.status === 'planned' ? 'scheduled' : r.status} label={r.status.replace('_', ' ')} /></div>
          <p className="mt-1 text-sm text-muted">{r.reference_number} · {date(r.route_date)}</p>
        </div>
        {can('routes.manage') && (
          <div className="flex flex-wrap gap-2">
            {r.status === 'planned' && (
              maps.data?.optimization
                ? <Button variant="outline" loading={busy === 'opt'} onClick={() => run('opt', () => api.post<RoutePlan>(`/routes/${r.id}/optimize`), 'Route optimised')}><Sparkles /> Optimise ({maps.data.provider})</Button>
                : <Tooltip content="Road routing uses the built-in OSRM/OpenStreetMap endpoint by default (override OSRM_URL, or set MAPS_PROVIDER=mapbox/google)"><span><Button variant="outline" disabled><Sparkles /> Optimise</Button></span></Tooltip>
            )}
            {r.status === 'planned' && <Button loading={busy === 'start'} onClick={() => run('start', () => api.post<RoutePlan>(`/routes/${r.id}/start`), 'Route started')}><Play /> Start route</Button>}
            {r.status === 'in_progress' && <Button loading={busy === 'done'} onClick={() => run('done', () => api.post<RoutePlan>(`/routes/${r.id}/complete`), 'Route completed')}><Flag /> Complete</Button>}
          </div>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Planned distance" value={km(r.planned_distance)} hint={r.distance_source === 'straight_line' ? 'Straight-line estimate (no routing provider)' : r.distance_source ? `Road distance · ${r.distance_source}` : undefined} />
        <StatCard label="Estimated duration" value={minutes(r.estimated_duration)} tone="accent" hint={r.estimated_duration === null ? 'Requires a routing provider' : undefined} />
        <StatCard label="Stops" value={`${r.completed_stops ?? 0} / ${stops.length}`} tone="success" hint="completed" />
        <StatCard label="Load" value={`${num(r.total_weight ?? 0, 1)} kg`} tone={overweight ? 'danger' : 'neutral'} hint={capacity ? `of ${num(capacity)} kg capacity` : 'No vehicle selected'} />
      </div>
      {overweight && <Alert tone="danger">The total load exceeds the vehicle capacity.</Alert>}

      <div className="grid gap-4 xl:grid-cols-[1fr_420px]">
        <Card className="overflow-hidden"><div className="h-[520px] p-3"><FleetMap markers={markers} line={line} /></div></Card>
        <div className="space-y-5">
          <Card><CardHeader title="Driver & vehicle" /><CardBody className="grid gap-3 sm:grid-cols-2">
            <Field label="Driver"><Select disabled={!editable} value={r.driver_id ?? ''} onChange={(e) => run('dv', () => api.put<RoutePlan>(`/routes/${r.id}`, { driver_id: e.target.value || null }), 'Driver updated')}>
              <option value="">—</option>{drivers.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}</Select></Field>
            <Field label="Vehicle"><Select disabled={!editable} value={r.vehicle_id ?? ''} onChange={(e) => run('dv', () => api.put<RoutePlan>(`/routes/${r.id}`, { vehicle_id: e.target.value || null }), 'Vehicle updated')}>
              <option value="">—</option>{vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_number}</option>)}</Select></Field>
          </CardBody></Card>
          <Card>
            <CardHeader title="Stop sequence" action={editable && <Button size="sm" variant="outline" onClick={() => setAdding(true)}><Plus /> Add stops</Button>} />
            <ol className="divide-y divide-border">
              {stops.length === 0 && <li className="p-6 text-center text-sm text-muted">No stops yet.</li>}
              {stops.map((s, i) => (
                <li key={s.id} className="flex items-start gap-3 px-4 py-3">
                  <span className={cn('grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold text-white', s.status === 'completed' ? 'bg-success' : s.status === 'skipped' ? 'bg-subtle' : 'bg-primary')}>
                    {s.status === 'completed' ? <CheckCircle2 className="size-4" /> : s.stop_order}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-1.5 text-sm font-semibold">
                      {s.delivery ? <Link className="hover:text-primary" to={`/app/deliveries/${s.delivery.id}`}>{s.delivery.reference_number}</Link> : 'Stop'}
                      {s.priority !== 'normal' && <Badge tone={s.priority === 'urgent' ? 'danger' : s.priority === 'high' ? 'warning' : 'neutral'}>{s.priority}</Badge>}
                      {!s.latitude && <Badge tone="warning">No pin</Badge>}
                    </p>
                    <p className="line-clamp-1 text-xs text-muted">{s.address}</p>
                    <p className="text-[11px] text-subtle">{s.estimated_arrival ? `ETA ${time(s.estimated_arrival)}` : 'ETA needs routing provider'}{s.actual_arrival ? ` · arrived ${time(s.actual_arrival)}` : ''}{s.delivery ? ` · ${num(s.delivery.weight, 1)} kg` : ''}</p>
                  </div>
                  {editable && r.status === 'planned' && (
                    <div className="flex shrink-0 gap-0.5">
                      <Button size="icon-sm" variant="ghost" disabled={i === 0 || busy !== null} onClick={() => move(i, -1)} aria-label="Move up"><ArrowUp /></Button>
                      <Button size="icon-sm" variant="ghost" disabled={i === stops.length - 1 || busy !== null} onClick={() => move(i, 1)} aria-label="Move down"><ArrowDown /></Button>
                      <Button size="icon-sm" variant="ghost" onClick={() => run('rm', () => api.delete<RoutePlan>(`/routes/${r.id}/stops/${s.id}`), 'Stop removed')} aria-label="Remove"><Trash2 /></Button>
                    </div>
                  )}
                </li>
              ))}
            </ol>
          </Card>
          {(r.optimizations?.length ?? 0) > 0 && (
            <Card><CardHeader title="Optimisation log" /><CardBody className="py-2">
              <KeyValue items={r.optimizations!.map((o) => [dateTime(o.at), `${o.provider}: ${km(o.distance_before)} → ${km(o.distance_after)} · ${minutes(o.duration_before)} → ${minutes(o.duration_after)}`])} />
            </CardBody></Card>
          )}
        </div>
      </div>

      <FormModal open={adding} onOpenChange={setAdding} title="Add stops" submitLabel={`Add ${picked.length || ''} stops`} submitting={busy === 'add'} onSubmit={async (e) => {
        e.preventDefault()
        await run('add', () => api.post<RoutePlan>(`/routes/${r.id}/stops`, { delivery_ids: picked }), 'Stops added')
        setPicked([]); setAdding(false); void reload()
      }}>
        <div className="max-h-80 space-y-1 overflow-y-auto">
          {open.length === 0 && <p className="p-4 text-center text-sm text-muted">No unrouted open deliveries.</p>}
          {open.map((d) => (
            <label key={d.id} className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-surface-2">
              <input type="checkbox" className="size-4 accent-[var(--primary)]" checked={picked.includes(d.id)} onChange={(e) => setPicked(e.target.checked ? [...picked, d.id] : picked.filter((x) => x !== d.id))} />
              <span className="font-semibold">{d.reference_number}</span><span className="min-w-0 flex-1 truncate text-muted">{d.dropoff.address}</span>
            </label>
          ))}
        </div>
      </FormModal>
    </div>
  )
}
