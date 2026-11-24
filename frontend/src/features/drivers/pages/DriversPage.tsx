import { Plus, UserRound } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Avatar, Badge, Button, Card, DataTable, EmptyState, FilterSelect, PageHeader, SearchInput, StatusBadge, Toolbar, type Column } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { fileUrl } from '@/lib/api'
import { date } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { Driver } from '@/types/domain'
import { DriverForm } from '../components/DriverForm'

export const AVAILABILITY = [
  { value: 'available', label: 'Available' }, { value: 'on_duty', label: 'On duty' }, { value: 'off_duty', label: 'Off duty' }, { value: 'on_leave', label: 'On leave' },
]

export default function DriversPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const { rows, meta, filters, set, loading, reload } = usePaged<Driver>('/drivers', { search: '', status: '', availability: '' })

  const columns: Column<Driver>[] = [
    { key: 'name', header: 'Driver', cell: (d) => (
      <div className="flex items-center gap-3">
        <Avatar name={d.name} src={fileUrl(d.photo_url)} />
        <div><p className="font-semibold text-fg">{d.name}</p><p className="text-xs text-muted">{d.phone}</p></div>
      </div>
    ) },
    { key: 'status', header: 'Status', cell: (d) => <StatusBadge status={d.status} /> },
    { key: 'avail', header: 'Availability', cell: (d) => <StatusBadge status={d.on_shift ? 'on_duty' : d.availability} />, hideOnMobile: true },
    { key: 'vehicle', header: 'Vehicle', cell: (d) => d.vehicle?.registration_number ?? <span className="text-subtle">—</span>, hideOnMobile: true },
    { key: 'active', header: 'Active jobs', cell: (d) => d.active_deliveries_count ?? 0, hideOnMobile: true },
    { key: 'license', header: 'Licence', cell: (d) => (
      <span className="flex items-center gap-2"><Badge>{d.license_type}</Badge><span className={d.license_valid ? 'text-fg-soft' : 'font-semibold text-danger'}>{date(d.license_expiry_date)}</span></span>
    ) },
  ]

  return (
    <>
      <PageHeader eyebrow="Fleet" title="Drivers" description="Driver profiles, licences, availability and current workload."
        actions={can('drivers.manage') && <Button onClick={() => setOpen(true)}><Plus /> Add driver</Button>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Name, phone, email…" />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'suspended', label: 'Suspended' }]} />
          <FilterSelect placeholder="Any availability" value={String(filters.availability ?? '')} onChange={(v) => set({ availability: v })} options={AVAILABILITY} />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(d) => navigate(`/app/drivers/${d.id}`)}
          empty={<EmptyState icon={<UserRound />} title="No drivers yet" description="Add drivers, then invite them to the mobile driver app." />} />
      </Card>
      <DriverForm open={open} onOpenChange={setOpen} onSaved={(d) => { void reload(); navigate(`/app/drivers/${d.id}`) }} />
    </>
  )
}
