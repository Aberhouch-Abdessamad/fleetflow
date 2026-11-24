import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export const badgeVariants = cva(
  'inline-flex h-5 items-center gap-1.5 whitespace-nowrap rounded-md px-1.5 text-[11px] font-medium ring-1 ring-inset [&_svg]:size-3',
  {
    variants: {
      tone: {
        neutral: 'bg-surface-3 text-fg-soft ring-border',
        primary: 'bg-primary-soft text-primary ring-primary/15',
        accent: 'bg-accent-soft text-accent ring-accent/15',
        success: 'bg-success-soft text-success ring-success/15',
        warning: 'bg-warning-soft text-warning ring-warning/20',
        danger: 'bg-danger-soft text-danger ring-danger/15',
        info: 'bg-info-soft text-info ring-info/15',
      },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

export type BadgeTone = NonNullable<VariantProps<typeof badgeVariants>['tone']>

interface BadgeProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badgeVariants> {
  dot?: boolean
}

export function Badge({ className, tone, dot, children, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ tone }), className)} {...props}>
      {dot && <span className="size-1.5 rounded-full bg-current" />}
      {children}
    </span>
  )
}

/** Maps any domain status string to a consistent tone. Extend as modules land. */
const STATUS_TONES: Record<string, BadgeTone> = {
  active: 'success', available: 'success', delivered: 'success', completed: 'success', approved: 'success', paid: 'success',
  pending: 'warning', draft: 'neutral', assigned: 'info', accepted: 'info', picked_up: 'primary', in_transit: 'primary',
  on_delivery: 'primary', arrived: 'accent', under_maintenance: 'warning', scheduled: 'info', overdue: 'danger',
  failed: 'danger', cancelled: 'neutral', returned: 'warning', suspended: 'danger', rejected: 'danger',
  out_of_service: 'danger', inactive: 'neutral',
}

export function StatusBadge({ status, label, className }: { status: string; label?: string; className?: string }) {
  const text = label ?? status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
  return <Badge dot tone={STATUS_TONES[status] ?? 'neutral'} className={className}>{text}</Badge>
}
