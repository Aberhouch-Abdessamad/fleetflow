import type { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { Pagination as P } from '@/types/api'
import { Button } from './button'

/** Responsive table: scrolls horizontally on small screens. */
export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="-mx-px overflow-x-auto">
      <table className={cn('w-full border-separate border-spacing-0 text-sm', className)} {...props} />
    </div>
  )
}
export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return <th className={cn('sticky top-0 h-9 border-b border-border bg-surface-2 px-3 text-left text-xs font-medium text-muted first:pl-4 last:pr-4', className)} {...props} />
}
export function Td({ className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return <td className={cn('h-11 border-b border-border px-3 py-2 align-middle text-[0.8125rem] text-fg-soft first:pl-4 last:pr-4 [tr:last-child>&]:border-b-0', className)} {...props} />
}
export function Tr({ className, ...props }: HTMLAttributes<HTMLTableRowElement>) {
  return <tr className={cn('transition-colors hover:bg-surface-2', className)} {...props} />
}

export function Pagination({ meta, onPage }: { meta: P; onPage: (page: number) => void }) {
  const from = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1
  const to = Math.min(meta.current_page * meta.per_page, meta.total)
  return (
    <div className="flex items-center justify-between gap-3 border-t border-border px-5 py-3 text-sm text-muted">
      <span>
        <span className="font-medium text-fg tabular-nums">{from}–{to}</span> of <span className="font-medium text-fg tabular-nums">{meta.total}</span>
      </span>
      <div className="flex gap-1.5">
        <Button variant="outline" size="icon-sm" aria-label="Previous page" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}><ChevronLeft /></Button>
        <Button variant="outline" size="icon-sm" aria-label="Next page" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}><ChevronRight /></Button>
      </div>
    </div>
  )
}
