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
        className={cn('z-50 min-w-52 origin-[var(--radix-dropdown-menu-content-transform-origin)] ff-pop rounded-lg border border-border bg-surface p-1 shadow-pop', className)}
        {...props}
      />
    </DM.Portal>
  )
}

export function DropdownItem({ className, ...props }: ComponentProps<typeof DM.Item>) {
  return (
    <DM.Item
      className={cn(
        'flex cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-[0.8125rem] text-fg-soft outline-none transition-colors data-[highlighted]:bg-surface-3 data-[highlighted]:text-fg data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:text-muted',
        className,
      )}
      {...props}
    />
  )
}

export function DropdownLabel({ className, ...props }: ComponentProps<typeof DM.Label>) {
  return <DM.Label className={cn('px-2 pb-1 pt-1.5 text-xs font-medium text-subtle', className)} {...props} />
}

export function DropdownSeparator({ className }: { className?: string }) {
  return <DM.Separator className={cn('-mx-1 my-1 h-px bg-border', className)} />
}
