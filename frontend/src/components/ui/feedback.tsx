import type { ReactNode } from 'react'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'
import { cn } from '@/lib/utils'

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('ff-skeleton h-4 w-full', className)} aria-hidden="true" />
}

export function EmptyState({ icon, title, description, action, className }: {
  icon?: ReactNode
  title: ReactNode
  description?: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('relative flex flex-col items-center justify-center overflow-hidden rounded-xl px-6 py-14 text-center', className)}>
      <div className="ff-grid-bg absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]" />
      {icon && (
        <div className="relative mb-4 grid size-14 place-items-center rounded-2xl border border-border bg-surface text-primary shadow-card [&_svg]:size-6">
          {icon}
        </div>
      )}
      <h3 className="relative text-base font-semibold">{title}</h3>
      {description && <p className="relative mt-1.5 max-w-sm text-sm text-muted">{description}</p>}
      {action && <div className="relative mt-5">{action}</div>}
    </div>
  )
}

const ALERT = {
  info: { icon: Info, cls: 'border-info/25 bg-info-soft text-info' },
  success: { icon: CheckCircle2, cls: 'border-success/25 bg-success-soft text-success' },
  warning: { icon: AlertTriangle, cls: 'border-warning/30 bg-warning-soft text-warning' },
  danger: { icon: XCircle, cls: 'border-danger/25 bg-danger-soft text-danger' },
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
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex gap-3 rounded-xl border px-4 py-3', cls, className)}>
      <Icon className="mt-0.5 size-[18px] shrink-0" />
      <div className="min-w-0 flex-1 text-sm">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={cn('text-fg-soft', title && 'mt-0.5')}>{children}</div>}
      </div>
      {action}
    </div>
  )
}
