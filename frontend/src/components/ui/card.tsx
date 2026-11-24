import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/lib/utils'

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('ff-card', className)} {...props} />
}

export function CardHeader({ title, description, action, icon, className }: {
  title: ReactNode
  description?: ReactNode
  action?: ReactNode
  icon?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex items-start justify-between gap-4 border-b border-border px-5 py-4', className)}>
      <div className="flex min-w-0 items-start gap-3">
        {icon && <div className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary [&_svg]:size-[18px]">{icon}</div>}
        <div className="min-w-0">
          <h3 className="text-[0.95rem] font-semibold leading-6">{title}</h3>
          {description && <p className="mt-0.5 text-[0.8125rem] text-muted">{description}</p>}
        </div>
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('p-5', className)} {...props} />
}

export function CardFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex items-center justify-end gap-2 rounded-b-xl border-t border-border bg-surface-2 px-5 py-3', className)} {...props} />
}
