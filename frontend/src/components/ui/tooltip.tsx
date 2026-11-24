import { Tooltip as T } from 'radix-ui'
import type { ReactNode } from 'react'

export const TooltipProvider = T.Provider

export function Tooltip({ content, children, side = 'top' }: { content: ReactNode; children: ReactNode; side?: 'top' | 'right' | 'bottom' | 'left' }) {
  return (
    <T.Root delayDuration={250}>
      <T.Trigger asChild>{children}</T.Trigger>
      <T.Portal>
        <T.Content side={side} sideOffset={8} className="z-50 animate-fade-in rounded-md bg-navy-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-pop dark:bg-surface-3 dark:text-fg">
          {content}
          <T.Arrow className="fill-navy-900 dark:fill-[var(--surface-3)]" />
        </T.Content>
      </T.Portal>
    </T.Root>
  )
}
