import {
  AlertTriangle, Banknote, FileWarning, Package, Plus, Radio, Users, Wrench,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { BarsChart, ChartCard, ShareList, TrendChart } from '@/components/charts/Charts'
import { FleetMap, escapeHtml } from '@/components/map/FleetMap'
import { DateRangeSelect, type RangeValue } from '@/components/shared/DateRangeSelect'
import { Badge, Button, Card, CardHeader, EmptyState, StatStrip, StatusBadge } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useInterval } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { ago, date, label, minutes, money, num, pct } from '@/lib/format'
import { useAuth } from '@/stores/auth'

interface Dashboard {
  range: { preset: string; bucket: string }
  kpis: Record<string, number | string | null>
  charts: {
    deliveries_over_time: { date: string; created: number; delivered: number; failed: number }[]
    status_distribution: { status: string; count: number }[]
    fleet_utilization: { date: string; vehicles_used: number; rate: number | null }[]
    revenue_over_time: { date: string; amount: string }[]
    fuel_expenses: { date: string; amount: string }[]
    maintenance_expenses: { date: string; amount: string }[]
    driver_performance: { driver_id: number; name: string; delivered: number; failed: number }[]
    average_delivery_minutes: number | null
  }
  widgets: {
    recent_deliveries: { id: number; reference: string; status: string; recipient: string; city: string | null; driver: string | null; created_at: string }[]
    active_drivers: { id: number; name: string; vehicle: string | null; last_seen: string | null; active_deliveries: number }[]
    upcoming_maintenance: { id: number; title: string; vehicle: string; vehicle_id: number; date: string; overdue: boolean }[]
    expiring_documents: { type: string; subject: string; link: string; expires: string; expired: boolean }[]
    recent_expenses: { id: number; description: string; category: string; amount: string; status: string; date: string }[]
    delivery_exceptions: { id: number; reference: string; reason: string; at: string }[]
    live_fleet: { id: number; registration_number: string; status: string; latitude: number; longitude: number; located_at: string; freshness: string }[]
  }
}

function greeting(tz?: string) {
  const h = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hour12: false, timeZone: tz }).format(new Date()))
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
}

export default function DashboardPage() {
  const { user, can } = useAuth()
  const navigate = useNavigate()
  const company = user!.current_company!
  const [range, setRange] = useState<RangeValue>({ range: 'last_30_days' })
  const { data, loading, reload } = useAsync(() => api.get<Dashboard>('/dashboard', { ...range }), [range.range, range.from, range.to])
  useInterval(() => void reload(), 60_000)
  const k = data?.kpis ?? {}
  const c = data?.charts
  const w = data?.widgets
  const financial = k.delivery_revenue !== null && k.delivery_revenue !== undefined

  const costs = useMemo(() => c ? c.fuel_expenses.map((f, i) => ({ date: f.date, fuel: Number(f.amount), maintenance: Number(c.maintenance_expenses[i]?.amount ?? 0) })) : [], [c])
  const revenue = useMemo(() => c?.revenue_over_time.map((r) => ({ date: r.date, revenue: Number(r.amount) })) ?? [], [c])
  const fmtMoney = (v: number) => money(v).replace(/\.00$/, '')

  return (
    <div className="space-y-5">
      <section className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between animate-slide-up">
        <div>
          <p className="font-mono text-xs text-subtle">{new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', timeZone: company.timezone }).format(new Date())}</p>
          <h1 className="mt-1 text-[1.5rem] font-semibold leading-tight">{greeting(company.timezone)}, {user!.name.split(' ')[0]}</h1>
          <p className="mt-1 text-[0.8125rem] text-muted">
            {k.active_deliveries !== undefined
              ? <><span className="font-medium text-fg">{String(k.active_deliveries)} deliveries</span> in progress, <span className="font-medium text-fg">{String(k.vehicles_on_road)} vehicles</span> on the road and <span className="font-medium text-fg">{String(k.on_duty_drivers)} drivers</span> on duty.</>
              : 'Loading operations…'}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <DateRangeSelect value={range} onChange={setRange} />
          {can('deliveries.create') && <Button onClick={() => navigate('/app/deliveries/new')}><Plus /> New delivery</Button>}
        </div>
      </section>

      <section className="space-y-2">
        <p className="text-xs font-medium text-muted">Fleet right now</p>
        <StatStrip loading={loading} items={[
          { label: 'Vehicles', value: num(k.total_vehicles as number), hint: `${num(k.vehicles_in_maintenance as number)} in maintenance` },
          { label: 'Available', value: num(k.available_vehicles as number), tone: 'success' },
          { label: 'On the road', value: num(k.vehicles_on_road as number), tone: 'accent' },
          { label: 'Active drivers', value: num(k.active_drivers as number), hint: `${num(k.on_duty_drivers as number)} on duty` },
          { label: 'Drivers available', value: num(k.available_drivers as number), tone: 'success' },
          { label: 'Deliveries today', value: num(k.deliveries_today as number) },
        ]} />
      </section>

      <section className="space-y-2">
        <p className="text-xs font-medium text-muted">Deliveries in period</p>
        <StatStrip loading={loading} items={[
          { label: 'Created', value: num(k.total_deliveries as number) },
          { label: 'Pending', value: num(k.pending_deliveries as number), tone: 'warning', hint: 'Awaiting assignment' },
          { label: 'Completed', value: num(k.completed_deliveries as number), tone: 'success' },
          { label: 'Failed', value: num(k.failed_deliveries as number), tone: 'danger' },
          { label: 'On-time rate', value: pct(k.on_time_rate as number | null), hint: c ? `Avg ${minutes(c.average_delivery_minutes)} per delivery` : undefined },
          { label: 'Revenue', value: financial ? money(k.delivery_revenue as string) : '—', hint: financial ? `Success rate ${pct(k.success_rate as number | null)}` : 'Requires finance access' },
        ]} />
      </section>

      <div className="grid gap-4 xl:grid-cols-3">
        <ChartCard className="xl:col-span-2" title="Deliveries over time" description="Created, delivered and failed per day" loading={loading} empty={!c?.deliveries_over_time.some((x) => x.created || x.delivered || x.failed)}>
          <TrendChart data={c?.deliveries_over_time ?? []} series={[{ key: 'created', label: 'Created' }, { key: 'delivered', label: 'Delivered' }, { key: 'failed', label: 'Failed', color: 'var(--chart-5)' }]} />
        </ChartCard>
        <ChartCard title="Status distribution" description="Deliveries created in the period" loading={loading} empty={!c?.status_distribution.length}>
          <ShareList items={(c?.status_distribution ?? []).sort((a, b) => b.count - a.count).map((s) => ({ label: label(s.status), value: s.count }))} />
        </ChartCard>
      </div>

      <div className="grid gap-4 xl:grid-cols-3">
        <ChartCard title="Fleet utilisation" description="Share of active vehicles that ran at least one delivery" loading={loading} empty={!c?.fleet_utilization.some((x) => x.vehicles_used)}>
          <BarsChart data={(c?.fleet_utilization ?? []).map((u) => ({ date: u.date, rate: u.rate ?? 0 }))} x="date" series={[{ key: 'rate', label: 'Utilisation' }]} format={(v) => `${v}%`} />
        </ChartCard>
        {financial && (
          <ChartCard title="Delivery revenue" description="Fees of delivered orders" loading={loading} empty={!revenue.some((r) => r.revenue)}>
            <BarsChart data={revenue} x="date" series={[{ key: 'revenue', label: 'Revenue' }]} format={fmtMoney} />
          </ChartCard>
        )}
        <ChartCard title="Fuel & maintenance costs" description="Recorded fuel purchases and completed maintenance" loading={loading} empty={!costs.some((x) => x.fuel || x.maintenance)}>
          <BarsChart data={costs} x="date" series={[{ key: 'fuel', label: 'Fuel', color: 'var(--chart-2)' }, { key: 'maintenance', label: 'Maintenance', color: 'var(--chart-3)' }]} format={fmtMoney} />
        </ChartCard>
        {!financial && (
          <ChartCard title="Driver performance" description="Delivered vs failed" loading={loading} empty={!c?.driver_performance.length}>
            <BarsChart layout="horizontal" data={c?.driver_performance ?? []} x="name" labelFormat={(l) => l} series={[{ key: 'delivered', label: 'Delivered' }, { key: 'failed', label: 'Failed', color: 'var(--chart-5)' }]} />
          </ChartCard>
        )}
      </div>

      <div className="grid gap-4 xl:grid-cols-3">
        <Card className="overflow-hidden xl:col-span-2">
          <CardHeader title="Live fleet" description={`${w?.live_fleet.filter((v) => v.freshness === 'live').length ?? 0} live · ${w?.live_fleet.length ?? 0} with a known position`} action={can('tracking.view') && <Button size="sm" variant="ghost" onClick={() => navigate('/app/tracking')}>Open map</Button>} />
          <div className="h-80 p-3">
            {w && w.live_fleet.length === 0
              ? <EmptyState className="ff-grid-bg h-full rounded-lg border border-dashed border-border" icon={<Radio />} title="No GPS positions yet" description="Vehicles appear once drivers share their location from the driver app." />
              : <FleetMap markers={(w?.live_fleet ?? []).map((v) => ({ id: v.id, lat: v.latitude, lng: v.longitude, kind: 'vehicle', tone: v.freshness === 'live' ? 'live' : 'stale', label: v.registration_number, popup: `<b>${escapeHtml(v.registration_number)}</b><br/>${escapeHtml(label(v.status))} · ${escapeHtml(ago(v.located_at))}` }))} />}
          </div>
        </Card>
        {financial ? (
          <ChartCard title="Driver performance" description="Delivered vs failed in the period" loading={loading} empty={!c?.driver_performance.length}>
            <BarsChart layout="horizontal" height={300} data={c?.driver_performance ?? []} x="name" labelFormat={(l) => l} series={[{ key: 'delivered', label: 'Delivered' }, { key: 'failed', label: 'Failed', color: 'var(--chart-5)' }]} />
          </ChartCard>
        ) : (
          <Card><CardHeader title="Active drivers" icon={<Users />} />
            <ul className="divide-y divide-border">{(w?.active_drivers ?? []).map((d) => <li key={d.id} className="flex items-center justify-between px-4 py-2.5 text-sm"><Link to={`/app/drivers/${d.id}`} className="font-medium hover:underline">{d.name}</Link><span className="text-xs text-muted">{d.vehicle ?? '—'} · {d.active_deliveries} active</span></li>)}
              {w?.active_drivers.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">No drivers on duty</li>}</ul>
          </Card>
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <Card className="xl:col-span-2"><CardHeader title="Recent deliveries" icon={<Package />} action={<Button size="sm" variant="ghost" onClick={() => navigate('/app/deliveries')}>View all</Button>} />
          <ul className="divide-y divide-border">
            {(w?.recent_deliveries ?? []).map((d) => (
              <li key={d.id}><Link to={`/app/deliveries/${d.id}`} className="flex items-center gap-4 px-4 py-2.5 text-sm transition-colors hover:bg-surface-2">
                <span className="w-36 shrink-0 font-mono text-xs font-medium text-fg">{d.reference}</span>
                <span className="min-w-0 flex-1 truncate text-fg-soft">{d.recipient}{d.city ? ` · ${d.city}` : ''}</span>
                <span className="hidden text-xs text-muted sm:block">{d.driver ?? 'Unassigned'}</span>
                <StatusBadge status={d.status} />
              </Link></li>
            ))}
            {w?.recent_deliveries.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">No deliveries yet</li>}
          </ul>
        </Card>
        <Card><CardHeader title="Delivery exceptions" icon={<AlertTriangle />} />
          <ul className="divide-y divide-border">
            {(w?.delivery_exceptions ?? []).map((x, i) => (
              <li key={`${x.id}-${i}`}><Link to={`/app/deliveries/${x.id}`} className="block px-4 py-2.5 text-sm hover:bg-surface-2"><p className="font-mono text-xs font-medium">{x.reference}</p><p className="text-xs text-danger">{x.reason}</p></Link></li>
            ))}
            {w?.delivery_exceptions.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">No exceptions</li>}
          </ul>
        </Card>
        <Card><CardHeader title="Upcoming maintenance" icon={<Wrench />} action={can('maintenance.view') && <Button size="sm" variant="ghost" onClick={() => navigate('/app/maintenance')}>All</Button>} />
          <ul className="divide-y divide-border">
            {(w?.upcoming_maintenance ?? []).map((m) => (
              <li key={m.id} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                <div className="min-w-0"><p className="truncate font-medium">{m.title}</p><Link to={`/app/vehicles/${m.vehicle_id}`} className="font-mono text-xs text-muted hover:text-fg">{m.vehicle}</Link></div>
                <Badge tone={m.overdue ? 'danger' : 'info'}>{date(m.date)}</Badge>
              </li>
            ))}
            {w?.upcoming_maintenance.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">Nothing scheduled</li>}
          </ul>
        </Card>
        <Card><CardHeader title="Expiring documents" icon={<FileWarning />} description="Next 30 days" />
          <ul className="divide-y divide-border">
            {(w?.expiring_documents ?? []).map((x, i) => (
              <li key={i}><Link to={x.link} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-surface-2">
                <div className="min-w-0"><p className="truncate font-medium">{x.subject}</p><p className="text-xs text-muted">{x.type}</p></div>
                <Badge tone={x.expired ? 'danger' : 'warning'}>{x.expired ? 'Expired' : date(x.expires)}</Badge>
              </Link></li>
            ))}
            {w?.expiring_documents.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">All documents valid</li>}
          </ul>
        </Card>
        <Card><CardHeader title="Recent expenses" icon={<Banknote />} action={can('expenses.view') && <Button size="sm" variant="ghost" onClick={() => navigate('/app/expenses')}>All</Button>} />
          <ul className="divide-y divide-border">
            {(w?.recent_expenses ?? []).map((e) => (
              <li key={e.id} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                <div className="min-w-0"><p className="truncate font-medium">{e.description}</p><p className="text-xs text-muted">{label(e.category)} · {date(e.date)}</p></div>
                <div className="text-right"><p className="font-medium tabular-nums">{money(e.amount)}</p><StatusBadge status={e.status} /></div>
              </li>
            ))}
            {w?.recent_expenses.length === 0 && <li className="px-4 py-8 text-center text-sm text-muted">No expenses</li>}
          </ul>
        </Card>
      </div>
    </div>
  )
}
