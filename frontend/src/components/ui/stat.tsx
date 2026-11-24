import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { Skeleton } from './feedback'

const TONES = {
  primary: 'text-primary',
  accent: 'text-accent',
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-danger',
  info: 'text-info',
  neutral: 'text-muted',
}

/** KPI tile. `value` must come from real data; pass null to show "—". */
export function StatCard({ label, value, icon, tone = 'neutral', hint, loading, className }: {
  label: ReactNode; value: ReactNode; icon?: ReactNode; tone?: keyof typeof TONES; hint?: ReactNode; loading?: boolean; className?: string
}) {
  return (
    <div className={cn('ff-card relative p-4 transition-colors hover:border-border-strong', className)}>
      <div className="flex items-center justify-between gap-3">
        <p className="flex items-center gap-1.5 text-xs font-medium text-muted">
          {tone !== 'neutral' && tone !== 'primary' && <span className={cn('size-1.5 rounded-full bg-current', TONES[tone])} />}
          {label}
        </p>
        {icon && <span className="text-subtle [&_svg]:size-3.5">{icon}</span>}
      </div>
      {loading ? <Skeleton className="mt-2.5 h-7 w-20" /> : <p className="mt-1.5 text-2xl font-semibold tracking-tight text-fg tabular-nums">{value ?? '—'}</p>}
      {hint && <p className="mt-0.5 truncate text-xs text-subtle">{hint}</p>}
    </div>
  )
}

/** A row of KPIs inside one bordered strip, separated by hairlines. */
export function StatStrip({ items, loading, className }: {
  items: { label: ReactNode; value: ReactNode; hint?: ReactNode; tone?: keyof typeof TONES }[]; loading?: boolean; className?: string
}) {
  return (
    <div className={cn('ff-card grid grid-cols-2 overflow-hidden sm:grid-cols-3', items.length >= 6 ? 'lg:grid-cols-6' : items.length === 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4', className)}>
      {items.map((it, i) => (
        <div key={i} className="-mb-px -mr-px border-b border-r border-border p-4">
          <p className="flex items-center gap-1.5 text-xs font-medium text-muted">
            {it.tone && it.tone !== 'neutral' && <span className={cn('size-1.5 rounded-full bg-current', TONES[it.tone])} />}
            {it.label}
          </p>
          {loading ? <Skeleton className="mt-2.5 h-7 w-16" /> : <p className="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums">{it.value ?? '—'}</p>}
          {it.hint && <p className="mt-0.5 truncate text-xs text-subtle">{it.hint}</p>}
        </div>
      ))}
    </div>
  )
}
