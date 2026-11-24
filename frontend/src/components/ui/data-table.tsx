import type { ReactNode } from 'react'
import { Inbox } from 'lucide-react'
import type { Pagination as P } from '@/types/api'
import { cn } from '@/lib/utils'
import { EmptyState, Skeleton } from './feedback'
import { Pagination, Table, Td, Th, Tr } from './table'

export interface Column<T> {
  key: string
  header: ReactNode
  cell: (row: T) => ReactNode
  className?: string
  hideOnMobile?: boolean
}

/** Table with loading skeletons, empty state, row click and pagination. */
export function DataTable<T extends { id: number | string }>({ columns, rows, loading, meta, onPage, onRowClick, empty, className }: {
  columns: Column<T>[]
  rows: T[]
  loading?: boolean
  meta?: { pagination?: P }
  onPage?: (page: number) => void
  onRowClick?: (row: T) => void
  empty?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('overflow-hidden', className)}>
      <Table>
        <thead>
          <tr>{columns.map((c) => <Th key={c.key} className={cn(c.className, c.hideOnMobile && 'hidden md:table-cell')}>{c.header}</Th>)}</tr>
        </thead>
        <tbody>
          {loading && rows.length === 0
            ? [...Array(6)].map((_, i) => (
                <tr key={i}>{columns.map((c) => <Td key={c.key} className={cn(c.hideOnMobile && 'hidden md:table-cell')}><Skeleton className="h-4 w-3/4" /></Td>)}</tr>
              ))
            : rows.map((row) => (
                <Tr key={row.id} onClick={onRowClick ? () => onRowClick(row) : undefined} className={cn(onRowClick && 'cursor-pointer', loading && 'opacity-60')}>
                  {columns.map((c) => <Td key={c.key} className={cn(c.className, c.hideOnMobile && 'hidden md:table-cell')}>{c.cell(row)}</Td>)}
                </Tr>
              ))}
        </tbody>
      </Table>
      {!loading && rows.length === 0 && (empty ?? <EmptyState icon={<Inbox />} title="Nothing here yet" description="No records match the current filters." />)}
      {meta?.pagination && onPage && meta.pagination.total > 0 && <Pagination meta={meta.pagination} onPage={onPage} />}
    </div>
  )
}
