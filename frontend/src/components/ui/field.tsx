import { cloneElement, isValidElement, useId, type ReactElement, type ReactNode } from 'react'
import { AlertCircle } from 'lucide-react'
import { cn } from '@/lib/utils'

interface FieldProps {
  label: ReactNode
  error?: string
  hint?: ReactNode
  required?: boolean
  className?: string
  labelAside?: ReactNode
  children: ReactElement<Record<string, unknown>>
}

/** Label + control + hint/error, wired with ids and aria attributes. */
export function Field({ label, error, hint, required, className, labelAside, children }: FieldProps) {
  const id = useId()
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined
  const control = isValidElement(children)
    ? cloneElement(children, { id, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy })
    : children

  return (
    <div className={cn('space-y-1.5', className)}>
      <div className="flex items-baseline justify-between gap-2">
        <label htmlFor={id} className="text-[0.8125rem] font-semibold text-fg-soft">
          {label}
          {required && <span className="ml-0.5 text-danger">*</span>}
        </label>
        {labelAside}
      </div>
      {control}
      {error ? (
        <p id={`${id}-error`} role="alert" className="flex items-center gap-1.5 text-xs font-medium text-danger animate-fade-in">
          <AlertCircle className="size-3.5" /> {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-xs text-muted">{hint}</p>
      ) : null}
    </div>
  )
}
