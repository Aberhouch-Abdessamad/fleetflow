import type { FormEventHandler, ReactNode } from 'react'
import { Button } from './button'
import { Modal } from './dialog'

/** Modal containing a form with standard Cancel / Submit footer. */
export function FormModal({ open, onOpenChange, title, description, onSubmit, submitting, submitLabel = 'Save', children, size = 'lg', danger }: {
  open: boolean
  onOpenChange: (o: boolean) => void
  title: ReactNode
  description?: ReactNode
  onSubmit: FormEventHandler<HTMLFormElement>
  submitting?: boolean
  submitLabel?: string
  children: ReactNode
  size?: 'sm' | 'md' | 'lg'
  danger?: boolean
}) {
  return (
    <Modal open={open} onOpenChange={(o) => !submitting && onOpenChange(o)} title={title} description={description} size={size}>
      <form onSubmit={onSubmit} noValidate className="-mx-5 -mb-3">
        <div className="max-h-[65vh] space-y-4 overflow-y-auto px-5 pb-3">{children}</div>
        <div className="flex justify-end gap-2 rounded-b-xl border-t border-border bg-surface-2 px-5 py-3">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>Cancel</Button>
          <Button type="submit" variant={danger ? 'danger' : 'primary'} loading={submitting}>{submitLabel}</Button>
        </div>
      </form>
    </Modal>
  )
}
