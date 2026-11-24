import { DropdownMenu as DM } from 'radix-ui'
import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

export const DropdownMenu = DM.Root
export const DropdownTrigger = DM.Trigger

export function DropdownContent({ className, sideOffset = 8, ...props }: ComponentProps<typeof DM.Content>) {
  return (
    <DM.Portal>
      <DM.Content
        sideOffset={sideOffset}
        className={cn('z-50 min-w-56 origin-[var(--radix-dropdown-menu-content-transform-origin)] animate-scale-in rounded-xl border border-border bg-surface p-1.5 shadow-pop', className)}
        {...props}
      />
    </DM.Portal>
  )
}

export function DropdownItem({ className, ...props }: ComponentProps<typeof DM.Item>) {
  return (
    <DM.Item
      className={cn(
        'flex cursor-pointer select-none items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-fg-soft outline-none transition-colors data-[highlighted]:bg-surface-3 data-[highlighted]:text-fg data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:text-muted',
        className,
      )}
      {...props}
    />
  )
}

export function DropdownLabel({ className, ...props }: ComponentProps<typeof DM.Label>) {
  return <DM.Label className={cn('px-2.5 pb-1 pt-1.5 text-[11px] font-semibold uppercase tracking-wider text-subtle', className)} {...props} />
}

export function DropdownSeparator({ className }: { className?: string }) {
  return <DM.Separator className={cn('-mx-1.5 my-1.5 h-px bg-border', className)} />
}
