import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { toast } from 'sonner'
import { toApiError } from './http'

/** Maps backend 422 field errors onto react-hook-form; toasts anything else. */
export function applyServerErrors<T extends FieldValues>(err: unknown, setError: UseFormSetError<T>, fields: readonly Path<T>[]) {
  const e = toApiError(err)
  let mapped = false
  if (e.kind === 'validation') {
    for (const [field, messages] of Object.entries(e.errors)) {
      if ((fields as readonly string[]).includes(field)) {
        setError(field as Path<T>, { type: 'server', message: messages[0] })
        mapped = true
      }
    }
  }
  if (!mapped) toast.error(e.message)
  return e
}
