import { Tooltip as T } from 'radix-ui'
import type { ReactNode } from 'react'

export const TooltipProvider = T.Provider

export function Tooltip({ content, children, side = 'top' }: { content: ReactNode; children: ReactNode; side?: 'top' | 'right' | 'bottom' | 'left' }) {
  return (
    <T.Root delayDuration={250}>
      <T.Trigger asChild>{children}</T.Trigger>
      <T.Portal>
        <T.Content side={side} sideOffset={8} className="z-50 animate-fade-in rounded-md bg-fg px-2 py-1 text-xs font-medium text-bg shadow-pop">
          {content}
          <T.Arrow className="fill-fg" />
        </T.Content>
      </T.Portal>
    </T.Root>
  )
}
