import { Route } from 'lucide-react'
import { useMemo, useState } from 'react'
import { FleetMap } from '@/components/map/FleetMap'
import { EmptyState, Field, Input, Skeleton } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { dateTime, km, num } from '@/lib/format'

interface History { points: { latitude: number; longitude: number; speed: number | null; recorded_at: string }[]; distance_km: number }

/** Recorded GPS trail for a vehicle (real device data only). */
export function GpsHistory({ vehicleId }: { vehicleId: number }) {
  const today = new Date().toISOString().slice(0, 10)
  const [day, setDay] = useState(today)
  const from = new Date(`${day}T00:00:00`).toISOString()
  const to = new Date(`${day}T23:59:59`).toISOString()
  const h = useAsync(() => api.get<History>(`/tracking/vehicles/${vehicleId}/history`, { from, to }), [vehicleId, day])
  const line = useMemo(() => (h.data?.points ?? []).map((p) => [p.latitude, p.longitude] as [number, number]), [h.data])
  const pts = h.data?.points ?? []
  const maxSpeed = pts.reduce((m, p) => Math.max(m, p.speed ?? 0), 0)

  return (
    <div className="grid gap-4 p-4 lg:grid-cols-[1fr_260px]">
      <div className="h-[420px]">
        {h.loading ? <Skeleton className="h-full w-full rounded-xl" />
          : pts.length === 0 ? <EmptyState className="h-full border border-dashed border-border" icon={<Route />} title="No GPS points recorded" description="Location history appears here once a driver shares their position from the driver app while on duty." />
          : <FleetMap line={line} fitKey={day} markers={[
              { id: 's', lat: pts[0].latitude, lng: pts[0].longitude, kind: 'pickup', tone: 'accent' },
              { id: 'e', lat: pts[pts.length - 1].latitude, lng: pts[pts.length - 1].longitude, kind: 'point', tone: 'primary', label: '■' },
            ]} />}
      </div>
      <div className="space-y-4">
        <Field label="Day"><Input type="date" max={today} value={day} onChange={(e) => setDay(e.target.value)} /></Field>
        <div className="ff-card divide-y divide-border text-sm">
          {[['Points recorded', num(pts.length)], ['Distance (recorded)', km(h.data?.distance_km)], ['Max speed', pts.length ? `${num(maxSpeed)} km/h` : '—'],
            ['First point', dateTime(pts[0]?.recorded_at)], ['Last point', dateTime(pts[pts.length - 1]?.recorded_at)]].map(([k, v]) => (
            <div key={k} className="flex justify-between gap-3 px-4 py-2.5"><span className="text-muted">{k}</span><span className="font-medium">{v}</span></div>
          ))}
        </div>
      </div>
    </div>
  )
}
