import { Eye, EyeOff, Lock } from 'lucide-react'
import { forwardRef, useState, type InputHTMLAttributes } from 'react'
import { Button, Input } from '@/components/ui'

export const PasswordInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>((props, ref) => {
  const [visible, setVisible] = useState(false)
  return (
    <Input
      ref={ref}
      type={visible ? 'text' : 'password'}
      leading={<Lock />}
      trailing={
        <Button type="button" variant="ghost" size="icon-sm" className="text-subtle" onClick={() => setVisible((v) => !v)} aria-label={visible ? 'Hide password' : 'Show password'}>
          {visible ? <EyeOff /> : <Eye />}
        </Button>
      }
      {...props}
    />
  )
})
PasswordInput.displayName = 'PasswordInput'

/** 0–4 strength score shown as a segmented meter. Purely advisory; the API enforces the rules. */
export function PasswordStrength({ value }: { value: string }) {
  const score = [value.length >= 8, /[a-z]/.test(value) && /[A-Z]/.test(value), /\d/.test(value), /[^A-Za-z0-9]/.test(value) || value.length >= 14]
    .filter(Boolean).length
  const labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong']
  const colors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success']
  if (!value) return null
  return (
    <div className="flex items-center gap-3 animate-fade-in" aria-live="polite">
      <div className="flex flex-1 gap-1">
        {[0, 1, 2, 3].map((i) => <span key={i} className={`h-1.5 flex-1 rounded-full transition-colors ${i < score ? colors[score] : 'bg-surface-3'}`} />)}
      </div>
      <span className="w-16 text-right text-xs font-medium text-muted">{labels[score]}</span>
    </div>
  )
}
