import type { ReactNode } from 'react'
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { cn } from '@/lib/utils'
import { Skeleton } from '../ui'

/** Categorical colours in fixed order (never cycled). Values validated for light & dark. */
export const SERIES = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)']

export interface Series { key: string; label: string; color?: string }

const axis = { stroke: 'var(--subtle)', fontSize: 11, tickLine: false, axisLine: false, fontFamily: 'var(--font-mono)' } as const

function TooltipBox({ active, payload, label, format, labelFormat }: {
  active?: boolean; payload?: { name: string; value: number; color: string }[]; label?: string
  format?: (v: number) => string; labelFormat?: (l: string) => string
}) {
  if (!active || !payload?.length) return null
  return (
    <div className="min-w-36 rounded-lg border border-border bg-surface px-2.5 py-2 text-xs shadow-pop">
      <p className="mb-1.5 font-medium text-muted">{labelFormat ? labelFormat(String(label)) : label}</p>
      {payload.map((p) => (
        <p key={p.name} className="flex items-center gap-2 text-fg-soft">
          <span className="h-2.5 w-[3px] rounded-full" style={{ background: p.color }} />
          <span className="flex-1">{p.name}</span>
          <span className="font-mono font-medium tabular-nums text-fg">{format ? format(Number(p.value)) : p.value}</span>
        </p>
      ))}
    </div>
  )
}

export function shortDate(d: string): string {
  if (/^\d{4}-\d{2}$/.test(d)) return new Date(`${d}-01T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
  return new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

export function ChartCard({ title, description, action, children, className, loading, empty }: {
  title: ReactNode; description?: ReactNode; action?: ReactNode; children: ReactNode; className?: string; loading?: boolean; empty?: boolean
}) {
  return (
    <div className={cn('ff-card flex flex-col', className)}>
      <div className="flex items-start justify-between gap-3 px-4 pt-3.5">
        <div><h3 className="text-[0.8125rem] font-semibold">{title}</h3>{description && <p className="text-xs text-muted">{description}</p>}</div>
        {action}
      </div>
      <div className="relative min-h-56 flex-1 px-2 pb-3 pt-2">
        {loading ? <Skeleton className="mx-3 mt-2 h-52" />
          : empty ? <div className="ff-grid-bg mx-3 mt-2 grid h-52 place-items-center rounded-lg text-center text-xs text-muted [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"><span className="rounded-md border border-border bg-surface px-2 py-1">No data for this period</span></div>
          : children}
      </div>
    </div>
  )
}

/** Change over time: 2px lines, crosshair tooltip, legend for ≥ 2 series. */
export function TrendChart({ data, x = 'date', series, format, height = 220 }: { data: Record<string, unknown>[]; x?: string; series: Series[]; format?: (v: number) => string; height?: number }) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <LineChart data={data} margin={{ top: 8, right: 12, bottom: 0, left: 0 }}>
        <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
        <XAxis dataKey={x} {...axis} tickFormatter={shortDate} minTickGap={24} />
        <YAxis {...axis} width={format ? 64 : 36} tickFormatter={(v) => (format ? format(v) : String(v))} allowDecimals={false} />
        <Tooltip content={<TooltipBox format={format} labelFormat={shortDate} />} cursor={{ stroke: 'var(--border-strong)', strokeDasharray: '3 3' }} />
        {series.length > 1 && <Legend iconType="circle" iconSize={7} wrapperStyle={{ fontSize: 12, paddingTop: 6, color: 'var(--muted)' }} />}
        {series.map((s, i) => (
          <Line key={s.key} type="monotone" dataKey={s.key} name={s.label} stroke={s.color ?? SERIES[i]} strokeWidth={2} dot={false}
            activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--surface)' }} />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
}

/** Magnitude per category/period: thin bars, 4px rounded tops, 2px gap. */
export function BarsChart({ data, x, series, format, height = 220, layout = 'vertical-bars', labelFormat }: {
  data: Record<string, unknown>[]; x: string; series: Series[]; format?: (v: number) => string; height?: number; layout?: 'vertical-bars' | 'horizontal'; labelFormat?: (l: string) => string
}) {
  const horizontal = layout === 'horizontal'
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ top: 8, right: 12, bottom: 0, left: horizontal ? 8 : 0 }} barGap={2} barCategoryGap="28%">
        <CartesianGrid vertical={horizontal} horizontal={!horizontal} stroke="var(--chart-grid)" />
        {horizontal ? (
          <>
            <XAxis type="number" {...axis} allowDecimals={false} tickFormatter={(v) => (format ? format(v) : String(v))} />
            <YAxis type="category" dataKey={x} {...axis} width={110} tickFormatter={labelFormat} />
          </>
        ) : (
          <>
            <XAxis dataKey={x} {...axis} tickFormatter={labelFormat ?? shortDate} minTickGap={16} />
            <YAxis {...axis} width={format ? 64 : 36} allowDecimals={false} tickFormatter={(v) => (format ? format(v) : String(v))} />
          </>
        )}
        <Tooltip content={<TooltipBox format={format} labelFormat={labelFormat ?? (horizontal ? undefined : shortDate)} />} cursor={{ fill: 'var(--surface-3)', opacity: 0.6 }} />
        {series.length > 1 && <Legend iconType="circle" iconSize={7} wrapperStyle={{ fontSize: 12, paddingTop: 6, color: 'var(--muted)' }} />}
        {series.map((s, i) => (
          <Bar key={s.key} dataKey={s.key} name={s.label} fill={s.color ?? SERIES[i]} radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]} maxBarSize={28} stackId={undefined} />
        ))}
      </BarChart>
    </ResponsiveContainer>
  )
}

/** Share-of-total as a labelled bar list (preferred over pies). */
export function ShareList({ items, format }: { items: { label: string; value: number; color?: string }[]; format?: (v: number) => string }) {
  const total = items.reduce((s, i) => s + i.value, 0) || 1
  return (
    <ul className="space-y-2.5 px-3 pt-2">
      {items.map((it, i) => (
        <li key={it.label}>
          <div className="mb-1 flex justify-between text-xs"><span className="font-medium text-fg-soft">{it.label}</span><span className="font-mono tabular-nums text-muted">{format ? format(it.value) : it.value} · {Math.round((it.value / total) * 100)}%</span></div>
          <div className="h-1.5 overflow-hidden rounded-full bg-surface-3"><div className="h-full rounded-full" style={{ width: `${(it.value / total) * 100}%`, background: it.color ?? SERIES[i % SERIES.length] }} /></div>
        </li>
      ))}
    </ul>
  )
}
