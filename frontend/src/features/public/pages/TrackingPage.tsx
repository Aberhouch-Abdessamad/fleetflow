import { Clock, MapPin, Phone, Star, Truck, XCircle } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Logo } from '@/components/brand/Logo'
import { FleetMap, type MapMarker } from '@/components/map/FleetMap'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Alert, Button, Skeleton, Textarea } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useInterval } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'
import { cn } from '@/lib/utils'

interface Track {
  reference: string; status: string; company: { name: string; phone: string | null }; recipient_name: string
  pickup: { address: string }; destination: { address: string; latitude: number | null; longitude: number | null }
  scheduled_delivery_at: string | null; estimated_arrival: string | null; delivered_at: string | null; failure_reason: string | null
  timeline: { status: string; at: string }[]; driver: { first_name: string } | null
  live_position: { latitude: number; longitude: number; recorded_at: string; freshness: 'live' | 'stale' } | null
  can_rate: boolean; rating: number | null
}

const STEPS = [
  { key: 'pending', label: 'Order received', match: ['pending', 'draft'] },
  { key: 'assigned', label: 'Driver assigned', match: ['assigned', 'accepted'] },
  { key: 'picked_up', label: 'Picked up', match: ['picked_up'] },
  { key: 'in_transit', label: 'On the way', match: ['in_transit', 'arrived'] },
  { key: 'delivered', label: 'Delivered', match: ['delivered'] },
]
const fmt = (v: string | null) => v ? new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '—'

/** Public customer tracking — no login, unguessable token, only approved fields. */
export default function TrackingPage() {
  const { token } = useParams()
  const { data: t, loading, error, reload } = useAsync(() => api.get<Track>(`/tracking/public/${token}`), [token])
  const [stars, setStars] = useState(0)
  const [comment, setComment] = useState('')
  const [rated, setRated] = useState(false)
  const [rateError, setRateError] = useState<string | null>(null)
  useInterval(() => void reload(), t && !['delivered', 'cancelled', 'failed', 'returned'].includes(t.status) ? 20_000 : null)

  const stepIdx = t ? STEPS.findIndex((s) => s.match.includes(t.status)) : -1
  const failed = t && ['failed', 'cancelled', 'returned'].includes(t.status)
  const markers: MapMarker[] = []
  if (t?.destination.latitude && t.destination.longitude) markers.push({ id: 'd', lat: t.destination.latitude, lng: t.destination.longitude, kind: 'dropoff', tone: 'primary' })
  if (t?.live_position) markers.push({ id: 'v', lat: t.live_position.latitude, lng: t.live_position.longitude, kind: 'vehicle', tone: t.live_position.freshness === 'live' ? 'live' : 'stale' })

  return (
    <div className="min-h-dvh bg-canvas">
      <header className="border-b border-border bg-bg/80 backdrop-blur"><div className="mx-auto flex h-14 max-w-2xl items-center justify-between px-5"><Logo /><ThemeToggle /></div></header>
      <main className="mx-auto max-w-2xl space-y-4 px-5 py-8">
        {loading && !t ? <Skeleton className="h-96" /> : error ? <Alert tone="danger" title="Tracking link not found">This link is invalid or tracking has been disabled by the sender.</Alert> : t && (
          <>
            <div className="ff-card overflow-hidden">
              <div className="px-6 pb-4 pt-6">
                <p className="font-mono text-xs text-muted">{t.company.name} · {t.reference}</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-[1.75rem]">
                  {t.status === 'delivered' ? 'Delivered' : failed ? (t.status === 'cancelled' ? 'Delivery cancelled' : 'Delivery attempt unsuccessful') : STEPS[stepIdx]?.label ?? 'In progress'}
                </h1>
                <p className="mt-1 text-[0.8125rem] text-muted">
                  {t.status === 'delivered' ? `Delivered ${fmt(t.delivered_at)}` : failed ? 'Please contact the sender for next steps.' : t.estimated_arrival ? `Expected ${fmt(t.estimated_arrival)}` : 'We\'ll update this page as your delivery progresses.'}
                </p>
              </div>
              <ol className="grid grid-cols-5 gap-1.5 px-6 pb-6">
                {STEPS.map((s, i) => {
                  const done = !failed && i <= stepIdx
                  const current = !failed && i === stepIdx
                  return (
                    <li key={s.key} className="min-w-0">
                      <span className={cn('block h-1 rounded-full', done ? (current ? 'bg-primary' : 'bg-fg') : 'bg-surface-3')} />
                      <span className={cn('mt-2 block truncate text-[11px] font-medium', current ? 'text-primary' : done ? 'text-fg' : 'text-subtle')}>{s.label}</span>
                    </li>
                  )
                })}
              </ol>
            </div>

            {(markers.length > 0) && (
              <div className="ff-card overflow-hidden">
                <div className="h-72 p-2"><FleetMap markers={markers} /></div>
                {t.live_position && <p className="border-t border-border px-4 py-2 text-xs text-muted">{t.live_position.freshness === 'live' ? 'Live vehicle position' : 'Last known vehicle position'} · updated {fmt(t.live_position.recorded_at)}</p>}
              </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="ff-card space-y-3 p-5 text-[0.8125rem]">
                <p className="flex items-start gap-2"><MapPin className="mt-0.5 size-4 text-muted" /><span><span className="block text-xs text-muted">Delivering to {t.recipient_name}</span>{t.destination.address}</span></p>
                {t.driver && <p className="flex items-center gap-2"><Truck className="size-4 text-muted" /><span>Your driver: <b>{t.driver.first_name}</b></span></p>}
                {t.scheduled_delivery_at && <p className="flex items-center gap-2"><Clock className="size-4 text-subtle" />Scheduled {fmt(t.scheduled_delivery_at)}</p>}
                {t.company.phone && <a href={`tel:${t.company.phone}`} className="flex items-center gap-2 font-medium text-fg underline decoration-border-strong underline-offset-4"><Phone className="size-4" />Contact {t.company.name}</a>}
              </div>
              <div className="ff-card p-5">
                <p className="text-xs font-medium text-subtle">Updates</p>
                <ol className="mt-3 space-y-3 border-l border-border pl-4">
                  {[...t.timeline].reverse().map((e, i) => (
                    <li key={i} className="relative text-sm"><span className={cn('absolute -left-[20.5px] top-1.5 size-2 rounded-full ring-4 ring-surface', i === 0 ? 'bg-primary' : 'bg-border-strong')} />
                      <span className="font-medium">{e.status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())}</span><span className="block text-xs text-muted">{fmt(e.at)}</span></li>
                  ))}
                </ol>
              </div>
            </div>

            {t.status === 'failed' && <Alert tone="danger" title="We couldn't complete your delivery"><XCircle className="hidden" />{t.failure_reason}</Alert>}

            {(t.can_rate && !rated) ? (
              <div className="ff-card p-5">
                <p className="font-semibold">How was your delivery?</p>
                <div className="mt-2 flex gap-1">{[1, 2, 3, 4, 5].map((n) => <button key={n} aria-label={`${n} stars`} onClick={() => setStars(n)}><Star className={cn('size-8 transition-colors', n <= stars ? 'fill-warning text-warning' : 'text-border-strong')} /></button>)}</div>
                {stars > 0 && <>
                  <Textarea className="mt-3" rows={2} placeholder="Anything we should know? (optional)" value={comment} onChange={(e) => setComment(e.target.value)} />
                  {rateError && <p className="mt-2 text-sm text-danger">{rateError}</p>}
                  <Button className="mt-3" onClick={async () => { try { await api.post(`/tracking/public/${token}/rating`, { rating: stars, comment }); setRated(true) } catch (e) { setRateError(toApiError(e).message) } }}>Send feedback</Button>
                </>}
              </div>
            ) : (rated || t.rating) ? <Alert tone="success">Thank you for your feedback!</Alert> : null}
            <p className="text-center text-xs text-subtle">Tracking powered by FleetFlow</p>
          </>
        )}
      </main>
    </div>
  )
}
