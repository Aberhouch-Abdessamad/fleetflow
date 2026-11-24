import { History } from 'lucide-react'
import { Button, EmptyState, Skeleton } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import type { Delivery } from '@/types/domain'
import { DriverSection } from '../DriverApp'
import { DeliveryRow } from './DriverHomePage'

export default function DriverHistoryPage() {
  const { rows, meta, set, loading } = usePaged<Delivery>('/driver/history')
  return (
    <DriverSection title={`Delivery history${meta ? ` · ${meta.pagination.total}` : ''}`}>
      {loading && !rows.length ? <div className="space-y-3">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-24" />)}</div>
        : rows.length === 0 ? <EmptyState className="ff-card" icon={<History />} title="No completed deliveries yet" />
        : <div className="space-y-3">{rows.map((d) => <DeliveryRow key={d.id} d={d} />)}</div>}
      {meta && meta.pagination.current_page < meta.pagination.last_page && <Button variant="outline" className="mt-4 w-full" onClick={() => set({ page: meta.pagination.current_page + 1 })}>Next page</Button>}
    </DriverSection>
  )
}
