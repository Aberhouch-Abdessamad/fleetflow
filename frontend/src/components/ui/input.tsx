import { forwardRef, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  leading?: ReactNode
  trailing?: ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(({ className, leading, trailing, ...props }, ref) => {
  if (!leading && !trailing) return <input ref={ref} className={cn('ff-input', className)} {...props} />
  return (
    <div className="relative">
      {leading && <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-subtle [&_svg]:size-4">{leading}</span>}
      <input ref={ref} className={cn('ff-input', leading && 'pl-9', trailing && 'pr-10', className)} {...props} />
      {trailing && <span className="absolute inset-y-0 right-1.5 flex items-center">{trailing}</span>}
    </div>
  )
})
Input.displayName = 'Input'

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(({ className, ...props }, ref) => (
  <textarea ref={ref} className={cn('ff-input h-auto min-h-24 py-2.5 leading-relaxed', className)} {...props} />
))
Textarea.displayName = 'Textarea'

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(({ className, children, ...props }, ref) => (
  <div className="relative">
    <select ref={ref} className={cn('ff-input cursor-pointer appearance-none pr-9', className)} {...props}>
      {children}
    </select>
    <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-subtle" />
  </div>
))
Select.displayName = 'Select'
