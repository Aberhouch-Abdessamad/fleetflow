import { Download, Package, Plus } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { Badge, Button, Card, DataTable, DropdownContent, DropdownItem, DropdownMenu, DropdownTrigger, EmptyState, FilterSelect, Input, PageHeader, SearchInput, StatusBadge, Toolbar, type Column } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { dateTime, money, num } from '@/lib/format'
import { useAuth } from '@/stores/auth'
import type { Delivery } from '@/types/domain'

export const DELIVERY_STATUSES = ['draft', 'pending', 'assigned', 'accepted', 'picked_up', 'in_transit', 'arrived', 'delivered', 'failed', 'cancelled', 'returned']
export const PRIORITY_TONE = { low: 'neutral', normal: 'info', high: 'warning', urgent: 'danger' } as const

export function PriorityBadge({ p }: { p: Delivery['priority'] }) {
  return p === 'normal' ? null : <Badge tone={PRIORITY_TONE[p]}>{p}</Badge>
}

export default function DeliveriesPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const { rows, meta, filters, set, loading } = usePaged<Delivery>('/deliveries', { search: '', status: '', priority: '', from: '', to: '' })

  const columns: Column<Delivery>[] = [
    { key: 'ref', header: 'Delivery', cell: (d) => (
      <div><p className="flex items-center gap-2 font-semibold text-fg">{d.reference_number} <PriorityBadge p={d.priority} /></p>
        <p className="text-xs text-muted">{d.recipient.name}{d.external_reference ? ` · ${d.external_reference}` : ''}</p></div>
    ) },
    { key: 'status', header: 'Status', cell: (d) => <span className="flex items-center gap-1.5"><StatusBadge status={d.status} />{d.is_late && <Badge tone="danger">Late</Badge>}</span> },
    { key: 'dest', header: 'Destination', cell: (d) => <span className="line-clamp-1 max-w-64 text-fg-soft">{d.dropoff.address}</span>, hideOnMobile: true },
    { key: 'driver', header: 'Driver', cell: (d) => d.driver?.name ?? <span className="text-subtle">Unassigned</span>, hideOnMobile: true },
    { key: 'sched', header: 'Scheduled', cell: (d) => dateTime(d.scheduled_delivery_at), hideOnMobile: true },
    { key: 'w', header: 'Weight', cell: (d) => `${num(d.total_weight, 1)} kg`, hideOnMobile: true },
    { key: 'fee', header: 'Fee', cell: (d) => money(d.delivery_fee), className: 'text-right' },
  ]
  const exportAs = (format: string) => api.download('/deliveries/export', { ...filters, page: undefined, format })

  return (
    <>
      <PageHeader eyebrow="Operations" title="Deliveries" description="Every order from creation to proof of delivery."
        actions={<>
          {can('deliveries.view') && (
            <DropdownMenu>
              <DropdownTrigger asChild><Button variant="outline"><Download /> Export</Button></DropdownTrigger>
              <DropdownContent align="end"><DropdownItem onSelect={() => exportAs('csv')}>CSV</DropdownItem><DropdownItem onSelect={() => exportAs('xlsx')}>Excel (.xlsx)</DropdownItem></DropdownContent>
            </DropdownMenu>
          )}
          {can('deliveries.create') && <Button onClick={() => navigate('/app/deliveries/new')}><Plus /> New delivery</Button>}
        </>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Reference, recipient, address…" />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={DELIVERY_STATUSES.map((s) => ({ value: s, label: s.replace(/_/g, ' ') }))} />
          <FilterSelect placeholder="Any priority" value={String(filters.priority ?? '')} onChange={(v) => set({ priority: v })} options={['urgent', 'high', 'normal', 'low'].map((p) => ({ value: p, label: p }))} />
          <div className="flex items-center gap-2 text-xs text-muted">
            <Input type="date" className="h-9 w-38" value={String(filters.from ?? '')} onChange={(e) => set({ from: e.target.value })} aria-label="From" />–
            <Input type="date" className="h-9 w-38" value={String(filters.to ?? '')} onChange={(e) => set({ to: e.target.value })} aria-label="To" />
          </div>
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(d) => navigate(`/app/deliveries/${d.id}`)}
          empty={<EmptyState icon={<Package />} title="No deliveries found" description="Create a delivery or connect an e-commerce integration." />} />
      </Card>
    </>
  )
}
