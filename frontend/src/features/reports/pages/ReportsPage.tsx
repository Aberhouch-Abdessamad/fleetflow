import { AlertTriangle, Download, FileSpreadsheet, FileText } from 'lucide-react'
import { useMemo, useState } from 'react'
import { BarsChart, ChartCard, ShareList, TrendChart } from '@/components/charts/Charts'
import { DateRangeSelect, type RangeValue } from '@/components/shared/DateRangeSelect'
import { Alert, Button, Card, DataTable, DropdownContent, DropdownItem, DropdownMenu, DropdownTrigger, PageHeader, StatCard, StatusBadge, Tabs, type Column } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { exportPdf } from '@/lib/export'
import { km, label, minutes, money, num, pct } from '@/lib/format'
import { useAuth } from '@/stores/auth'

type Type = 'deliveries' | 'fleet' | 'drivers' | 'financial'
type Row = Record<string, unknown> & { id: string | number }

function ExportMenu({ type, range, rows, title }: { type: Type; range: RangeValue; rows: Row[]; title: string }) {
  return (
    <DropdownMenu>
      <DropdownTrigger asChild><Button variant="outline"><Download /> Export</Button></DropdownTrigger>
      <DropdownContent align="end">
        <DropdownItem onSelect={() => api.download(`/reports/${type}`, { ...range, export: 'csv' })}><FileText /> CSV</DropdownItem>
        <DropdownItem onSelect={() => api.download(`/reports/${type}`, { ...range, export: 'xlsx' })}><FileSpreadsheet /> Excel</DropdownItem>
        <DropdownItem onSelect={() => exportPdf(title, rows.map(({ cost_breakdown: _c, ...r }) => r))}><FileText /> PDF</DropdownItem>
      </DropdownContent>
    </DropdownMenu>
  )
}

function DeliveriesReport({ d }: { d: any }) { // eslint-disable-line @typescript-eslint/no-explicit-any
  const s = d.summary
  return (
    <>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatCard label="Deliveries" value={num(s.total)} />
        <StatCard label="Delivered" value={num(s.delivered)} tone="success" />
        <StatCard label="Failed / returned" value={num(s.failed)} tone="danger" />
        <StatCard label="Success rate" value={pct(s.success_rate)} tone="accent" />
        <StatCard label="On-time rate" value={pct(s.on_time_rate)} tone="info" hint={`${s.on_time_measured} with a deadline · avg ${minutes(s.average_delivery_minutes)}`} />
      </div>
      <div className="grid gap-4 xl:grid-cols-3">
        <ChartCard className="xl:col-span-2" title="Deliveries by date" empty={!d.by_date.some((x: Row) => x.created)}><TrendChart data={d.by_date} series={[{ key: 'created', label: 'Created' }, { key: 'delivered', label: 'Delivered' }, { key: 'failed', label: 'Failed', color: 'var(--chart-5)' }]} /></ChartCard>
        <ChartCard title="By status" empty={!d.by_status.length}><ShareList items={d.by_status.map((x: Row) => ({ label: label(String(x.status)), value: Number(x.count) }))} /></ChartCard>
        <ChartCard title="Volume by city" empty={!d.by_city.length}><BarsChart layout="horizontal" data={d.by_city} x="city" labelFormat={(l) => l} series={[{ key: 'count', label: 'Deliveries' }]} /></ChartCard>
        <ChartCard className="xl:col-span-2" title="Failure reasons" empty={!d.failure_reasons.length}><BarsChart layout="horizontal" data={d.failure_reasons} x="reason" labelFormat={(l) => l} series={[{ key: 'count', label: 'Failures', color: 'var(--chart-5)' }]} /></ChartCard>
      </div>
    </>
  )
}

export default function ReportsPage() {
  const can = useAuth((s) => s.can)
  const tabs = ([['deliveries', 'Deliveries', 'reports.deliveries'], ['fleet', 'Fleet', 'reports.fleet'], ['drivers', 'Drivers', 'reports.drivers'], ['financial', 'Financial', 'reports.financial']] as const)
    .filter(([, , p]) => can(p))
  const [type, setType] = useState<Type>((tabs[0]?.[0] ?? 'deliveries') as Type)
  const [range, setRange] = useState<RangeValue>({ range: 'last_30_days' })
  const r = useAsync(() => api.get<any>(`/reports/${type}`, { ...range }), [type, range.range, range.from, range.to]) // eslint-disable-line @typescript-eslint/no-explicit-any
  const d = r.data

  const fleetCols: Column<Row>[] = [
    { key: 'v', header: 'Vehicle', cell: (x) => <div><p className="font-semibold">{String(x.registration_number)}</p><p className="text-xs text-muted">{String(x.vehicle)}</p></div> },
    { key: 's', header: 'Status', cell: (x) => <StatusBadge status={String(x.status)} /> },
    { key: 'd', header: 'Deliveries', cell: (x) => num(x.deliveries as number) },
    { key: 'u', header: 'Utilisation', cell: (x) => pct(x.utilization_rate as number) },
    { key: 'f', header: 'Fuel', cell: (x) => `${num(x.fuel_litres as number, 1)} L · ${money(x.fuel_cost as string)}`, hideOnMobile: true },
    { key: 'c', header: 'L/100km', cell: (x) => (x.l_per_100km as number | null) ?? '—', hideOnMobile: true },
    { key: 'k', header: 'Cost / km', cell: (x) => money(x.cost_per_km as string | null), hideOnMobile: true },
    { key: 'o', header: 'Approved costs', cell: (x) => <span className="font-semibold">{money(x.approved_costs as string)}</span>, className: 'text-right' },
  ]
  const driverCols: Column<Row>[] = [
    { key: 'n', header: 'Driver', cell: (x) => <span className="font-semibold">{String(x.name)}</span> },
    { key: 't', header: 'Deliveries', cell: (x) => num(x.total_deliveries as number) },
    { key: 'c', header: 'Completed', cell: (x) => num(x.completed_deliveries as number) },
    { key: 'f', header: 'Failed', cell: (x) => num(x.failed_deliveries as number) },
    { key: 'o', header: 'On time', cell: (x) => pct(x.on_time_rate as number | null) },
    { key: 's', header: 'Success', cell: (x) => pct(x.success_rate as number | null), hideOnMobile: true },
    { key: 'a', header: 'Avg time', cell: (x) => minutes(x.average_delivery_minutes as number | null), hideOnMobile: true },
    { key: 'k', header: 'Distance', cell: (x) => km(x.distance_km as number | null), hideOnMobile: true },
    { key: 'h', header: 'Hours', cell: (x) => `${x.working_hours} h`, hideOnMobile: true },
    { key: 'r', header: 'Rating', cell: (x) => (x.average_rating as number | null) ?? '—', hideOnMobile: true },
  ]
  const exportRows: Row[] = useMemo(() => !d ? [] : type === 'financial' ? d.revenue_by_customer : d.rows, [d, type])

  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Analytics" title="Reports" description="Every figure is computed from your records for the selected period."
        actions={<><DateRangeSelect value={range} onChange={setRange} />{d && <ExportMenu type={type} range={range} rows={exportRows} title={`${label(type)} report`} />}</>} />
      <Tabs value={type} onChange={setType} tabs={tabs.map(([v, l]) => ({ value: v as Type, label: l }))} />
      {r.loading && !d ? <div className="grid gap-4 sm:grid-cols-4">{[...Array(4)].map((_, i) => <StatCard key={i} label="…" value="" loading />)}</div>
        : r.error ? <Alert tone="danger">{r.error.message}</Alert>
        : d && type === 'deliveries' ? <DeliveriesReport d={d} />
        : d && type === 'fleet' ? (
          <>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
              <StatCard label="Vehicles" value={num(d.summary.vehicles)} />
              <StatCard label="Avg utilisation" value={pct(d.summary.average_utilization)} tone="accent" />
              <StatCard label="Fuel" value={`${num(d.summary.fuel_litres)} L`} tone="info" hint={money(d.summary.fuel_cost)} />
              <StatCard label="Operating costs" value={money(d.summary.operating_costs)} tone="primary" hint="Approved expenses" />
              <StatCard label="Pending costs" value={money(d.summary.pending_costs)} tone="warning" hint="Not yet approved" />
            </div>
            <ChartCard title="Fleet utilisation" description="Share of the fleet used per day"><BarsChart data={d.utilization.map((u: Row) => ({ date: u.date, rate: u.rate ?? 0 }))} x="date" series={[{ key: 'rate', label: 'Utilisation' }]} format={(v) => `${v}%`} /></ChartCard>
            <Card className="overflow-hidden"><DataTable rows={d.rows} columns={fleetCols} /></Card>
          </>
        )
        : d && type === 'drivers' ? (
          <>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
              <StatCard label="Drivers" value={num(d.summary.drivers)} />
              <StatCard label="Completed" value={num(d.summary.completed)} tone="success" />
              <StatCard label="Failed" value={num(d.summary.failed)} tone="danger" />
              <StatCard label="Working hours" value={`${num(d.summary.working_hours, 1)} h`} tone="info" />
              <StatCard label="GPS distance" value={km(d.summary.distance_km)} tone="accent" />
            </div>
            <ChartCard title="Completed vs failed by driver"><BarsChart layout="horizontal" height={Math.max(220, d.rows.length * 34)} data={d.rows} x="name" labelFormat={(l) => l} series={[{ key: 'completed_deliveries', label: 'Completed' }, { key: 'failed_deliveries', label: 'Failed', color: 'var(--chart-5)' }]} /></ChartCard>
            <Card className="overflow-hidden"><DataTable rows={d.rows} columns={driverCols} /></Card>
          </>
        )
        : d && type === 'financial' ? (
          <>
            {d.warnings.length > 0 && <Alert tone="warning" title="Figures are incomplete">{d.warnings.map((w: string) => <p key={w} className="flex gap-1.5"><AlertTriangle className="mt-0.5 size-3.5 shrink-0" />{w}</p>)}</Alert>}
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              <StatCard label="Delivery revenue" value={money(d.summary.delivery_revenue)} />
              <StatCard label="Approved expenses" value={money(d.summary.approved_expenses)} tone="danger" hint={`${money(d.summary.pending_expenses)} pending`} />
              <StatCard label="Net operating margin" value={money(d.summary.net_operating_margin)} tone={Number(d.summary.net_operating_margin) >= 0 ? 'success' : 'danger'} hint={d.summary.margin_rate !== null ? `${d.summary.margin_rate}% of revenue` : undefined} />
              <StatCard label="Outstanding invoices" value={money(d.summary.outstanding)} tone="warning" hint={`${money(d.summary.collected)} collected`} />
              <StatCard label="Invoiced" value={money(d.summary.invoiced)} tone="info" />
              <StatCard label="COD expected" value={money(d.summary.cod_expected)} tone="neutral" />
              <StatCard label="COD collected" value={money(d.summary.cod_collected)} tone="accent" />
              <StatCard label="COD variance" value={money(d.summary.cod_variance)} tone={Number(d.summary.cod_variance) < 0 ? 'danger' : 'success'} />
            </div>
            <div className="grid gap-4 xl:grid-cols-3">
              <ChartCard className="xl:col-span-2" title="Revenue vs approved expenses">
                <TrendChart data={d.revenue_over_time.map((x: Row, i: number) => ({ date: x.date, revenue: Number(x.amount), expenses: Number(d.expenses_over_time[i]?.amount ?? 0) }))}
                  series={[{ key: 'revenue', label: 'Revenue' }, { key: 'expenses', label: 'Expenses', color: 'var(--chart-3)' }]} format={(v) => money(v).replace(/\.00$/, '')} />
              </ChartCard>
              <ChartCard title="Expenses by category" empty={!d.expenses_by_category.length}><ShareList items={d.expenses_by_category.map((x: Row) => ({ label: label(String(x.category)), value: Number(x.amount) }))} format={(v) => money(v)} /></ChartCard>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
              <Card className="overflow-hidden"><div className="border-b border-border px-5 py-3 font-semibold">Revenue by customer</div>
                <DataTable rows={d.revenue_by_customer.map((x: Row) => ({ ...x, id: x.customer_id }))} columns={[
                  { key: 'c', header: 'Customer', cell: (x: Row) => String(x.customer ?? '—') }, { key: 'd', header: 'Deliveries', cell: (x: Row) => num(x.deliveries as number) },
                  { key: 'r', header: 'Revenue', cell: (x: Row) => <span className="font-semibold">{money(x.revenue as string)}</span>, className: 'text-right' }]} /></Card>
              <Card className="overflow-hidden"><div className="border-b border-border px-5 py-3 font-semibold">Profitability by delivery <span className="text-xs font-normal text-muted">(revenue − directly attributed approved costs)</span></div>
                <DataTable rows={d.profitability_by_delivery} columns={[
                  { key: 'r', header: 'Delivery', cell: (x: Row) => <span className="font-semibold">{String(x.reference)}</span> }, { key: 'v', header: 'Revenue', cell: (x: Row) => money(x.revenue as string) },
                  { key: 'c', header: 'Direct costs', cell: (x: Row) => money(x.direct_costs as string) },
                  { key: 'm', header: 'Margin', cell: (x: Row) => <span className="font-semibold">{money(x.margin as string)}{x.incomplete ? ' *' : ''}</span>, className: 'text-right' }]} />
              </Card>
              <Card className="overflow-hidden"><div className="border-b border-border px-5 py-3 font-semibold">Customer balances</div>
                <DataTable rows={d.customer_balances.map((x: Row, i: number) => ({ ...x, id: i }))} columns={[{ key: 'c', header: 'Customer', cell: (x: Row) => String(x.customer) }, { key: 'i', header: 'Open invoices', cell: (x: Row) => num(x.invoices as number) }, { key: 'b', header: 'Balance', cell: (x: Row) => <span className="font-semibold">{money(x.balance as string)}</span>, className: 'text-right' }]} />
              </Card>
              <Card className="overflow-hidden"><div className="border-b border-border px-5 py-3 font-semibold">Cost per vehicle</div>
                <DataTable rows={d.cost_per_vehicle.map((x: Row, i: number) => ({ ...x, id: i }))} columns={[{ key: 'v', header: 'Vehicle', cell: (x: Row) => String(x.vehicle) }, { key: 'k', header: 'Cost / km', cell: (x: Row) => money(x.cost_per_km as string | null) }, { key: 'a', header: 'Approved costs', cell: (x: Row) => <span className="font-semibold">{money(x.approved_costs as string)}</span>, className: 'text-right' }]} />
              </Card>
            </div>
          </>
        ) : null}
    </div>
  )
}
