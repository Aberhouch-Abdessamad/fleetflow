import type { ReactNode } from 'react'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'
import { cn } from '@/lib/utils'

export function Skeleton({ className }: { className?: string }) {
  return <span className={cn('ff-skeleton block h-4 w-full', className)} aria-hidden="true" />
}

export function EmptyState({ icon, title, description, action, className }: {
  icon?: ReactNode
  title: ReactNode
  description?: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('relative flex flex-col items-center justify-center overflow-hidden px-6 py-14 text-center', className)}>
      {icon && (
        <div className="relative mb-4 grid size-10 place-items-center rounded-lg border border-border bg-surface text-muted shadow-xs [&_svg]:size-[18px]">
          <div className="absolute -inset-3 -z-10 rounded-xl border border-dashed border-border" />
          {icon}
        </div>
      )}
      <h3 className="relative text-sm font-semibold">{title}</h3>
      {description && <p className="relative mt-1 max-w-sm text-[0.8125rem] text-muted">{description}</p>}
      {action && <div className="relative mt-4">{action}</div>}
    </div>
  )
}

const ALERT = {
  info: { icon: Info, cls: 'border-border bg-surface-2 text-info' },
  success: { icon: CheckCircle2, cls: 'border-success/20 bg-success-soft text-success' },
  warning: { icon: AlertTriangle, cls: 'border-warning/25 bg-warning-soft text-warning' },
  danger: { icon: XCircle, cls: 'border-danger/20 bg-danger-soft text-danger' },
}

export function Alert({ tone = 'info', title, children, className, action }: {
  tone?: keyof typeof ALERT
  title?: ReactNode
  children?: ReactNode
  className?: string
  action?: ReactNode
}) {
  const { icon: Icon, cls } = ALERT[tone]
  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex gap-2.5 rounded-lg border px-3.5 py-2.5', cls, className)}>
      <Icon className="mt-0.5 size-4 shrink-0" />
      <div className="min-w-0 flex-1 text-sm">
        {title && <p className="font-medium text-fg">{title}</p>}
        {children && <div className={cn('text-fg-soft', title && 'mt-0.5')}>{children}</div>}
      </div>
      {action}
    </div>
  )
}
