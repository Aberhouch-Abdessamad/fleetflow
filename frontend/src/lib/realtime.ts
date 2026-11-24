import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useEffect, useRef } from 'react'
import { http } from './http'

/**
 * Laravel Echo over Reverb when VITE_REVERB_APP_KEY is configured. Without it the
 * app polls (pages call useLive with an interval) — real-time is an enhancement,
 * never the source of truth.
 */
let echo: Echo<'reverb'> | null = null

export const realtimeEnabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY)

export function getEcho(): Echo<'reverb'> | null {
  if (!realtimeEnabled) return null
  if (echo) return echo
  ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher
  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, cb: (error: Error | null, data: { auth: string } | null) => void) => {
        http.post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, { baseURL: String(import.meta.env.VITE_API_URL ?? '/api/v1').replace(/\/v1\/?$/, '') })
          .then((r) => cb(null, r.data as { auth: string })).catch((e) => cb(e as Error, null))
      },
    }),
  })
  return echo
}

export function disconnectRealtime() {
  echo?.disconnect()
  echo = null
}

/** Subscribe to events on a private channel; handler gets the payload. No-op without Reverb. */
export function useChannel(channel: string | null, events: string[], handler: (event: string, payload: Record<string, unknown>) => void) {
  const ref = useRef(handler)
  ref.current = handler
  const key = events.join(',')
  useEffect(() => {
    const e = getEcho()
    if (!e || !channel) return
    const ch = e.private(channel)
    for (const ev of key.split(',')) ch.listen(`.${ev}`, (p: Record<string, unknown>) => ref.current(ev, p))
    return () => { e.leave(channel) }
  }, [channel, key])
}
