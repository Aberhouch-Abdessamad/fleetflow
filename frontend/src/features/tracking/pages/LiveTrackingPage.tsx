import { Gauge, MapPinOff, Navigation, Radio, Search, Truck } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { FleetMap, escapeHtml, type MapMarker } from '@/components/map/FleetMap'
import { Badge, Input, PageHeader, Segmented, Skeleton, StatusBadge } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useInterval } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { ago, label, num } from '@/lib/format'
import { realtimeEnabled, useChannel } from '@/lib/realtime'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Located } from '@/types/domain'

interface LiveVehicle {
  id: number; registration_number: string; label: string; type: string | null; status: string
  driver: { id: number; name: string; phone: string } | null
  location: (Located & { speed: number | null }) | null
  current_delivery: { id: number; reference_number: string; status: string; recipient: string; destination: string; destination_latitude: number | null; destination_longitude: number | null; scheduled_delivery_at: string | null } | null
  active_deliveries: number
}

/**
 * Live map of the fleet. Positions come exclusively from driver devices; a vehicle
 * whose last update is older than the stale window is shown grey as "last known".
 */
export default function LiveTrackingPage() {
  const user = useAuth((s) => s.user)
  const data = useAsync(() => api.get<LiveVehicle[]>('/tracking/vehicles'), [])
  const [vehicles, setVehicles] = useState<LiveVehicle[] | null>(null)
  const list = vehicles ?? data.data ?? []
  const [filter, setFilter] = useState<'all' | 'live' | 'active'>('all')
  const [q, setQ] = useState('')
  const [selected, setSelected] = useState<number | null>(null)

  const refresh = async () => setVehicles(await api.get<LiveVehicle[]>('/tracking/vehicles'))
  useChannel(user?.current_company ? `company.${user.current_company.id}` : null, ['VehicleLocationUpdated', 'DeliveryStatusUpdated'], (event, p) => {
    if (event === 'VehicleLocationUpdated' && p.vehicle_id) {
      setVehicles((prev) => (prev ?? data.data ?? []).map((v) => v.id === p.vehicle_id ? {
        ...v, location: { latitude: Number(p.latitude), longitude: Number(p.longitude), speed: (p.speed as number) ?? null, heading: (p.heading as number) ?? null, recorded_at: String(p.recorded_at), freshness: 'live' },
      } : v))
    } else void refresh()
  })
  useInterval(() => void refresh(), realtimeEnabled ? 60_000 : 15_000)

  const shown = list.filter((v) => (filter === 'live' ? v.location?.freshness === 'live' : filter === 'active' ? v.active_deliveries > 0 : true)
    && (!q || `${v.registration_number} ${v.driver?.name ?? ''}`.toLowerCase().includes(q.toLowerCase())))
  const located = shown.filter((v) => v.location)
  const sel = list.find((v) => v.id === selected) ?? null

  const markers = useMemo<MapMarker[]>(() => {
    const m: MapMarker[] = located.map((v) => ({
      id: v.id, lat: v.location!.latitude, lng: v.location!.longitude, kind: 'vehicle', tone: v.location!.freshness === 'live' ? 'live' : 'stale',
      heading: v.location!.heading, label: v.registration_number, onClick: () => setSelected(v.id),
      popup: `<b>${escapeHtml(v.registration_number)}</b><br/>${escapeHtml(v.driver?.name ?? 'No driver')}<br/><span style="color:#64748b">${v.location!.freshness === 'live' ? 'Live' : 'Last seen'} ${escapeHtml(ago(v.location!.recorded_at))}</span>`,
    }))
    if (sel?.current_delivery?.destination_latitude && sel.current_delivery.destination_longitude) {
      m.push({ id: 'dest', lat: sel.current_delivery.destination_latitude, lng: sel.current_delivery.destination_longitude, kind: 'dropoff', tone: 'primary', popup: `<b>Destination</b><br/>${escapeHtml(sel.current_delivery.destination)}` })
    }
    return m
  }, [located, sel])

  const liveCount = list.filter((v) => v.location?.freshness === 'live').length

  return (
    <div>
      <PageHeader eyebrow="Operations" title="Live tracking"
        description={`${liveCount} live · ${list.filter((v) => v.location && v.location.freshness !== 'live').length} last-known · ${list.filter((v) => !v.location).length} never reported`}
        actions={<Badge tone={realtimeEnabled ? 'success' : 'neutral'} dot>{realtimeEnabled ? 'Real-time stream' : 'Polling every 15 s'}</Badge>} />
      <div className="grid gap-4 lg:grid-cols-[340px_1fr]">
        <div className="ff-card flex max-h-[calc(100dvh-210px)] flex-col overflow-hidden">
          <div className="space-y-2 border-b border-border p-3">
            <Input className="h-9" leading={<Search />} placeholder="Vehicle or driver" value={q} onChange={(e) => setQ(e.target.value)} />
            <Segmented value={filter} onChange={setFilter} className="w-full [&>button]:flex-1" options={[{ value: 'all', label: 'All' }, { value: 'live', label: 'Live' }, { value: 'active', label: 'On a job' }]} />
          </div>
          <ul className="flex-1 divide-y divide-border overflow-y-auto">
            {data.loading && !list.length ? [...Array(5)].map((_, i) => <li key={i} className="p-3"><Skeleton className="h-12" /></li>)
              : shown.map((v) => (
                <li key={v.id}>
                  <button onClick={() => setSelected(v.id)} className={cn('flex w-full items-start gap-3 p-3 text-left transition-colors hover:bg-surface-2', selected === v.id && 'bg-primary-soft')}>
                    <span className={cn('mt-0.5 grid size-9 shrink-0 place-items-center rounded-full text-white', v.location?.freshness === 'live' ? 'bg-success' : v.location ? 'bg-subtle' : 'bg-surface-3 text-subtle')}>
                      {v.location ? <Navigation className="size-4" style={{ transform: `rotate(${v.location.heading ?? 0}deg)` }} /> : <MapPinOff className="size-4" />}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center justify-between gap-2"><span className="text-sm font-semibold">{v.registration_number}</span><StatusBadge status={v.status} /></span>
                      <span className="block truncate text-xs text-muted">{v.driver?.name ?? 'No driver'}{v.current_delivery ? ` · ${v.current_delivery.reference_number}` : ''}</span>
                      <span className="mt-0.5 block text-[11px] text-subtle">{v.location ? `${v.location.freshness === 'live' ? 'Live' : 'Last known'} · ${ago(v.location.recorded_at)}${v.location.speed !== null ? ` · ${num(v.location.speed)} km/h` : ''}` : 'No GPS data yet'}</span>
                    </span>
                  </button>
                </li>
              ))}
            {!data.loading && shown.length === 0 && <li className="p-6 text-center text-sm text-muted">No vehicles match.</li>}
          </ul>
        </div>
        <div className="relative h-[calc(100dvh-210px)] min-h-[420px]">
          <FleetMap markers={markers} fitKey={selected ?? 'all'} className="h-full rounded-xl ring-1 ring-border" />
          {located.length === 0 && !data.loading && (
            <div className="pointer-events-none absolute inset-0 grid place-items-center">
              <div className="ff-card max-w-sm p-5 text-center">
                <Radio className="mx-auto size-6 text-primary" />
                <p className="mt-2 text-sm font-semibold">No vehicle positions yet</p>
                <p className="mt-1 text-xs text-muted">Positions appear here when drivers start a shift in the driver app and allow location access. Nothing is simulated.</p>
              </div>
            </div>
          )}
          {sel && (
            <div className="absolute bottom-4 left-4 right-4 z-[500] sm:right-auto sm:w-80">
              <div className="ff-card p-4 shadow-pop animate-slide-up">
                <div className="flex items-start justify-between">
                  <div>
                    <Link to={`/app/vehicles/${sel.id}`} className="font-semibold hover:text-primary">{sel.label}</Link>
                    <p className="text-xs text-muted">{sel.type} · {label(sel.status)}</p>
                  </div>
                  <button className="text-xs text-muted hover:text-fg" onClick={() => setSelected(null)}>Close</button>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                  <div className="rounded-lg bg-surface-2 p-2"><p className="text-muted">Driver</p><p className="font-semibold">{sel.driver?.name ?? '—'}</p></div>
                  <div className="rounded-lg bg-surface-2 p-2"><p className="flex items-center gap-1 text-muted"><Gauge className="size-3" /> Speed</p><p className="font-semibold">{sel.location?.speed !== null && sel.location ? `${num(sel.location.speed)} km/h` : '—'}</p></div>
                </div>
                {sel.current_delivery ? (
                  <Link to={`/app/deliveries/${sel.current_delivery.id}`} className="mt-3 block rounded-lg border border-border p-2.5 text-xs hover:border-primary">
                    <p className="flex items-center justify-between font-semibold"><span className="flex items-center gap-1.5"><Truck className="size-3.5" />{sel.current_delivery.reference_number}</span><StatusBadge status={sel.current_delivery.status} /></p>
                    <p className="mt-1 line-clamp-2 text-muted">→ {sel.current_delivery.destination}</p>
                  </Link>
                ) : <p className="mt-3 text-xs text-muted">No active delivery.</p>}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
