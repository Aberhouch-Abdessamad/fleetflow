import { Bell, CheckCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { Button, Card, EmptyState, PageHeader, Pagination, Segmented, Skeleton } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { dateTime } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useNotifications } from '@/stores/notifications'
import type { AppNotification } from '@/types/domain'

const DOT: Record<string, string> = { info: 'bg-info', success: 'bg-success', warning: 'bg-warning', danger: 'bg-danger' }

export default function NotificationsPage() {
  const navigate = useNavigate()
  const refresh = useNotifications((s) => s.refreshCount)
  const { rows, meta, filters, set, loading, reload } = usePaged<AppNotification>('/notifications', { unread: false })
  return (
    <>
      <PageHeader eyebrow="Workspace" title="Notifications" actions={<>
        <Segmented value={filters.unread ? 'unread' : 'all'} onChange={(v) => set({ unread: v === 'unread' })} options={[{ value: 'all', label: 'All' }, { value: 'unread', label: 'Unread' }]} />
        <Button variant="outline" onClick={async () => { await api.post('/notifications/read-all'); void reload(); void refresh() }}><CheckCheck /> Mark all read</Button>
      </>} />
      <Card className="overflow-hidden">
        {loading && !rows.length ? <div className="space-y-2 p-4">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-14" />)}</div>
          : rows.length === 0 ? <EmptyState icon={<Bell />} title="No notifications" description="Assignments, delivery updates, maintenance reminders and approvals appear here." />
          : <ul className="divide-y divide-border">{rows.map((n) => (
            <li key={n.id}><button className="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-surface-2" onClick={async () => {
              if (!n.read) { await api.post(`/notifications/${n.id}/read`); void refresh() }
              if (n.data.link) navigate(n.data.link); else void reload()
            }}>
              <span className={cn('mt-1.5 size-2.5 shrink-0 rounded-full', n.read ? 'bg-border-strong' : DOT[n.data.tone] ?? 'bg-primary')} />
              <span className="min-w-0 flex-1"><span className={cn('block text-sm', n.read ? 'text-fg-soft' : 'font-semibold')}>{n.data.title}</span>{n.data.body && <span className="block text-sm text-muted">{n.data.body}</span>}</span>
              <span className="shrink-0 text-xs text-subtle">{dateTime(n.created_at)}</span>
            </button></li>))}</ul>}
        {meta && meta.pagination.total > 0 && <Pagination meta={meta.pagination} onPage={(p) => set({ page: p })} />}
      </Card>
    </>
  )
}
