import { Dialog as D } from 'radix-ui'
import { X } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { Button } from './button'

export function Modal({ open, onOpenChange, title, description, children, footer, size = 'md' }: {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: ReactNode
  description?: ReactNode
  children?: ReactNode
  footer?: ReactNode
  size?: 'sm' | 'md' | 'lg'
}) {
  return (
    <D.Root open={open} onOpenChange={onOpenChange}>
      <D.Portal>
        <D.Overlay className="fixed inset-0 z-50 bg-navy-950/50 backdrop-blur-[2px] animate-fade-in" />
        <D.Content
          className={cn(
            'fixed left-1/2 top-1/2 z-50 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-border bg-surface shadow-pop animate-scale-in',
            { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' }[size],
          )}
        >
          <div className="flex items-start justify-between gap-4 px-6 pb-2 pt-5">
            <div>
              <D.Title className="font-display text-lg font-bold">{title}</D.Title>
              {description ? <D.Description className="mt-1 text-sm text-muted">{description}</D.Description> : <D.Description className="sr-only">{String(title)}</D.Description>}
            </div>
            <D.Close asChild>
              <Button variant="ghost" size="icon-sm" aria-label="Close"><X /></Button>
            </D.Close>
          </div>
          {children && <div className="px-6 py-3">{children}</div>}
          {footer && <div className="flex justify-end gap-2 px-6 pb-5 pt-3">{footer}</div>}
        </D.Content>
      </D.Portal>
    </D.Root>
  )
}

/** Confirmation dialog for destructive or important actions. */
export function ConfirmDialog({ trigger, title, description, confirmLabel = 'Confirm', tone = 'danger', onConfirm }: {
  trigger: ReactNode
  title: ReactNode
  description?: ReactNode
  confirmLabel?: string
  tone?: 'danger' | 'primary'
  onConfirm: () => Promise<unknown> | unknown
}) {
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  return (
    <>
      <span onClick={() => setOpen(true)}>{trigger}</span>
      <Modal
        open={open}
        onOpenChange={(o) => !busy && setOpen(o)}
        title={title}
        description={description}
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setOpen(false)} disabled={busy}>Cancel</Button>
            <Button
              variant={tone}
              loading={busy}
              onClick={async () => {
                setBusy(true)
                try { await onConfirm(); setOpen(false) } finally { setBusy(false) }
              }}
            >
              {confirmLabel}
            </Button>
          </>
        }
      />
    </>
  )
}
