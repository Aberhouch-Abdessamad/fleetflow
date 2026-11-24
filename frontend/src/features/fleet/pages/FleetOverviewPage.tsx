import { CheckCircle2, Radio, Truck, Wrench, XCircle } from 'lucide-react'
import { Link } from 'react-router-dom'
import { BarsChart, ChartCard, ShareList } from '@/components/charts/Charts'
import { Card, CardHeader, PageHeader, StatCard, StatusBadge } from '@/components/ui'
import { VehicleThumb } from '@/features/vehicles/pages/VehiclesPage'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { ago, date, label, num } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { Vehicle } from '@/types/domain'

export default function FleetOverviewPage() {
  const can = useAuth((s) => s.can)
  const vehicles = useAsync(() => api.page<Vehicle>('/vehicles', { per_page: 100 }), [])
  const report = useAsync(() => can('reports.fleet') ? api.get<{ utilization: { date: string; rate: number | null }[]; rows: { registration_number: string; utilization_rate: number }[] }>('/reports/fleet', { range: 'last_30_days' }) : Promise.resolve(null), [])
  const list = vehicles.data?.data ?? []
  const count = (s: string) => list.filter((v) => v.status === s).length
  const soon = (d: string | null) => d && new Date(d) < new Date(Date.now() + 30 * 864e5)
  const attention = list.filter((v) => soon(v.next_service_date) || soon(v.insurance_expiry_date) || soon(v.registration_expiry_date))
  const byType = Object.entries(list.reduce<Record<string, number>>((a, v) => ({ ...a, [v.type?.name ?? 'Other']: (a[v.type?.name ?? 'Other'] ?? 0) + 1 }), {}))

  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Fleet" title="Fleet overview" description="Availability, utilisation and compliance across the fleet." />
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatCard loading={vehicles.loading} label="Vehicles" value={num(list.length)} icon={<Truck />} />
        <StatCard loading={vehicles.loading} label="Available" value={num(count('available'))} icon={<CheckCircle2 />} tone="success" />
        <StatCard loading={vehicles.loading} label="Assigned / on delivery" value={num(count('assigned') + count('on_delivery'))} icon={<Radio />} tone="accent" />
        <StatCard loading={vehicles.loading} label="In maintenance" value={num(count('under_maintenance'))} icon={<Wrench />} tone="warning" />
        <StatCard loading={vehicles.loading} label="Out of service" value={num(count('out_of_service') + count('inactive'))} icon={<XCircle />} tone="danger" />
      </div>
      <div className="grid gap-4 xl:grid-cols-3">
        <ChartCard title="Status" loading={vehicles.loading} empty={!list.length}><ShareList items={['available', 'assigned', 'on_delivery', 'under_maintenance', 'out_of_service', 'inactive'].map((s) => ({ label: label(s), value: count(s) })).filter((x) => x.value)} /></ChartCard>
        <ChartCard title="By type" loading={vehicles.loading} empty={!list.length}><ShareList items={byType.map(([l, v]) => ({ label: l, value: v }))} /></ChartCard>
        {report.data ? (
          <ChartCard title="Utilisation by vehicle" description="Days with deliveries, last 30 days"><BarsChart layout="horizontal" data={report.data.rows} x="registration_number" labelFormat={(l) => l} series={[{ key: 'utilization_rate', label: 'Utilisation' }]} format={(v) => `${v}%`} /></ChartCard>
        ) : <ChartCard title="Utilisation" empty>{null}</ChartCard>}
      </div>
      <Card>
        <CardHeader title="Needs attention" description="Service or documents due within 30 days" />
        <ul className="divide-y divide-border">
          {attention.length === 0 && <li className="px-5 py-8 text-center text-sm text-muted">Everything is up to date.</li>}
          {attention.map((v) => (
            <li key={v.id}><Link to={`/app/vehicles/${v.id}`} className="flex flex-wrap items-center gap-4 px-5 py-3 hover:bg-surface-2">
              <VehicleThumb v={v} />
              <div className="min-w-0 flex-1"><p className="font-semibold">{v.registration_number}</p><p className="text-xs text-muted">{v.make} {v.model}{v.location ? ` · seen ${ago(v.location.recorded_at)}` : ''}</p></div>
              <div className="flex flex-wrap gap-3 text-xs">
                {soon(v.next_service_date) && <span className="text-warning">Service {date(v.next_service_date)}</span>}
                {soon(v.insurance_expiry_date) && <span className="text-danger">Insurance {date(v.insurance_expiry_date)}</span>}
                {soon(v.registration_expiry_date) && <span className="text-danger">Registration {date(v.registration_expiry_date)}</span>}
              </div>
              <StatusBadge status={v.status} />
            </Link></li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
