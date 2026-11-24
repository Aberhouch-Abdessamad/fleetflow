import { useEffect, useRef, useState } from 'react'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'

export type GpsState = 'idle' | 'requesting' | 'active' | 'denied' | 'unavailable' | 'error'

const QUEUE_KEY = 'ff-gps-queue'
const MIN_INTERVAL_MS = 15_000

interface Ping { latitude: number; longitude: number; speed: number | null; heading: number | null; accuracy: number | null; recorded_at: string }

function loadQueue(): Ping[] {
  try { return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]') as Ping[] } catch { return [] }
}
function saveQueue(q: Ping[]) {
  try { localStorage.setItem(QUEUE_KEY, JSON.stringify(q.slice(-300))) } catch { /* storage full/unavailable */ }
}

/**
 * Shares the device's real GPS position while `enabled` (on shift or carrying a delivery).
 * Never fabricates positions. Pings captured while offline are queued with their original
 * timestamps and flushed in order when connectivity returns (the API de-duplicates).
 */
export function useGpsTracker(enabled: boolean) {
  const [state, setState] = useState<GpsState>('idle')
  const [last, setLast] = useState<{ at: string; accuracy: number | null } | null>(null)
  const [queued, setQueued] = useState(loadQueue().length)
  const [message, setMessage] = useState<string | null>(null)
  const lastSent = useRef(0)
  const flushing = useRef(false)

  const flush = async () => {
    if (flushing.current || !navigator.onLine) return
    flushing.current = true
    try {
      let q = loadQueue()
      while (q.length) {
        try {
          await api.post('/tracking/location', q[0])
        } catch (e) {
          const err = toApiError(e)
          if (err.kind === 'network' || err.status >= 500 || err.status === 429) break // retry later
          setMessage(err.message) // 4xx: drop the point (invalid / not on duty / stale)
        }
        q = q.slice(1)
        saveQueue(q)
        setQueued(q.length)
      }
    } finally { flushing.current = false }
  }

  useEffect(() => {
    if (!enabled) { setState('idle'); return }
    if (!('geolocation' in navigator)) { setState('unavailable'); return }
    setState('requesting')
    const id = navigator.geolocation.watchPosition(
      (pos) => {
        setState('active')
        setMessage(null)
        const now = Date.now()
        if (now - lastSent.current < MIN_INTERVAL_MS) return
        lastSent.current = now
        const ping: Ping = {
          latitude: pos.coords.latitude, longitude: pos.coords.longitude,
          speed: pos.coords.speed !== null ? Math.round(pos.coords.speed * 3.6 * 10) / 10 : null,
          heading: pos.coords.heading !== null && !Number.isNaN(pos.coords.heading) ? Math.round(pos.coords.heading) : null,
          accuracy: pos.coords.accuracy !== null ? Math.round(pos.coords.accuracy) : null,
          recorded_at: new Date(pos.timestamp).toISOString(),
        }
        setLast({ at: ping.recorded_at, accuracy: ping.accuracy })
        const q = [...loadQueue(), ping]
        saveQueue(q); setQueued(q.length)
        void flush()
      },
      (err) => {
        if (err.code === err.PERMISSION_DENIED) setState('denied')
        else { setState('error'); setMessage(err.message) }
      },
      { enableHighAccuracy: true, maximumAge: 10_000, timeout: 30_000 },
    )
    const onOnline = () => void flush()
    window.addEventListener('online', onOnline)
    void flush()
    return () => { navigator.geolocation.clearWatch(id); window.removeEventListener('online', onOnline) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled])

  return { state, last, queued, message, flush }
}
