import { useAuth } from '@/stores/auth'

export function currency(): string {
  return useAuth.getState().user?.current_company?.currency ?? 'USD'
}
export function timezone(): string | undefined {
  return useAuth.getState().user?.current_company?.timezone
}

export function money(v: string | number | null | undefined, cur = currency()): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = typeof v === 'string' ? Number(v) : v
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur, maximumFractionDigits: 2 }).format(n)
  } catch {
    return `${n.toFixed(2)} ${cur}`
  }
}

export function num(v: string | number | null | undefined, digits = 0): string {
  if (v === null || v === undefined || v === '') return '—'
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: digits }).format(Number(v))
}

export function pct(v: number | null | undefined): string {
  return v === null || v === undefined ? '—' : `${num(v, 1)}%`
}

export function date(v: string | null | undefined): string {
  if (!v) return '—'
  const d = v.length === 10 ? new Date(`${v}T00:00:00`) : new Date(v)
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeZone: v.length === 10 ? undefined : timezone() }).format(d)
}

export function dateTime(v: string | null | undefined): string {
  if (!v) return '—'
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone() }).format(new Date(v))
}

export function time(v: string | null | undefined): string {
  if (!v) return '—'
  return new Intl.DateTimeFormat(undefined, { timeStyle: 'short', timeZone: timezone() }).format(new Date(v))
}

export function ago(v: string | null | undefined): string {
  if (!v) return '—'
  const s = Math.round((Date.now() - new Date(v).getTime()) / 1000)
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
  const abs = Math.abs(s)
  if (abs < 60) return rtf.format(-s, 'second')
  if (abs < 3600) return rtf.format(-Math.round(s / 60), 'minute')
  if (abs < 86400) return rtf.format(-Math.round(s / 3600), 'hour')
  return rtf.format(-Math.round(s / 86400), 'day')
}

export function minutes(v: number | null | undefined): string {
  if (v === null || v === undefined) return '—'
  const h = Math.floor(v / 60)
  const m = Math.round(v % 60)
  return h ? `${h}h ${m}m` : `${m} min`
}

export function km(v: string | number | null | undefined): string {
  return v === null || v === undefined ? '—' : `${num(v, 1)} km`
}

export const label = (s: string | null | undefined) => (s ? s.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()) : '—')

/** datetime-local value from ISO */
export function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`
}
export function fromLocalInput(v: string): string | null {
  return v ? new Date(v).toISOString() : null
}
