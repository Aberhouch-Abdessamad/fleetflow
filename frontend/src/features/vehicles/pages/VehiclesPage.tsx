import { Plus, Truck } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Button, Card, DataTable, EmptyState, FilterSelect, PageHeader, SearchInput, StatusBadge, Toolbar, type Column } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { useAsync } from '@/hooks/useAsync'
import { api, fileUrl } from '@/lib/api'
import { date, num } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { Vehicle, VehicleType } from '@/types/domain'
import { VehicleForm } from '../components/VehicleForm'

export const VEHICLE_STATUSES = ['available', 'assigned', 'on_delivery', 'under_maintenance', 'out_of_service', 'inactive']

export function VehicleThumb({ v }: { v: Pick<Vehicle, 'image_url' | 'registration_number'> }) {
  const src = fileUrl(v.image_url)
  return src
    ? <img src={src} alt="" className="size-9 shrink-0 rounded-lg object-cover ring-1 ring-border" />
    : <span className="grid size-9 shrink-0 place-items-center rounded-lg border border-border bg-surface-2 text-muted"><Truck className="size-4" /></span>
}

export default function VehiclesPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const types = useAsync(() => api.get<VehicleType[]>('/vehicle-types'), [])
  const { rows, meta, filters, set, loading, reload } = usePaged<Vehicle>('/vehicles', { search: '', status: '', vehicle_type_id: '' })

  const columns: Column<Vehicle>[] = [
    {
      key: 'vehicle', header: 'Vehicle', cell: (v) => (
        <div className="flex items-center gap-3">
          <VehicleThumb v={v} />
          <div className="min-w-0">
            <p className="font-semibold text-fg">{v.registration_number}</p>
            <p className="truncate text-xs text-muted">{v.make} {v.model}{v.year ? ` · ${v.year}` : ''}</p>
          </div>
        </div>
      ),
    },
    { key: 'type', header: 'Type', cell: (v) => v.type?.name, hideOnMobile: true },
    { key: 'status', header: 'Status', cell: (v) => <StatusBadge status={v.status} /> },
    { key: 'driver', header: 'Driver', cell: (v) => v.current_driver?.name ?? <span className="text-subtle">Unassigned</span>, hideOnMobile: true },
    { key: 'capacity', header: 'Capacity', cell: (v) => `${num(v.capacity)} ${v.capacity_unit}`, hideOnMobile: true },
    { key: 'odometer', header: 'Odometer', cell: (v) => `${num(v.odometer)} km`, hideOnMobile: true },
    {
      key: 'service', header: 'Next service', hideOnMobile: true, cell: (v) => {
        if (!v.next_service_date) return <span className="text-subtle">—</span>
        const overdue = new Date(v.next_service_date) < new Date(new Date().toDateString())
        return <span className={overdue ? 'font-semibold text-danger' : ''}>{date(v.next_service_date)}</span>
      },
    },
  ]

  return (
    <>
      <PageHeader eyebrow="Fleet" title="Vehicles" description="Every vehicle in your fleet, its status, assignment and compliance."
        actions={can('vehicles.manage') && <Button onClick={() => setOpen(true)}><Plus /> Add vehicle</Button>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Registration, make, VIN…" />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={VEHICLE_STATUSES.map((s) => ({ value: s, label: s.replace(/_/g, ' ') }))} />
          <FilterSelect placeholder="All types" value={String(filters.vehicle_type_id ?? '')} onChange={(v) => set({ vehicle_type_id: v })} options={(types.data ?? []).map((t) => ({ value: String(t.id), label: t.name }))} />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(v) => navigate(`/app/vehicles/${v.id}`)}
          empty={<EmptyState icon={<Truck />} title="No vehicles yet" description="Add your first vehicle to start assigning drivers and deliveries."
            action={can('vehicles.manage') && <Button onClick={() => setOpen(true)}><Plus /> Add vehicle</Button>} />} />
      </Card>
      <VehicleForm open={open} onOpenChange={setOpen} onSaved={(v) => { void reload(); navigate(`/app/vehicles/${v.id}`) }} />
    </>
  )
}
