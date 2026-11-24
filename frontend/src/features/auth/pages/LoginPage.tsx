import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRight, Mail } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { Alert, Button, Field, Input } from '@/components/ui'
import { applyServerErrors } from '@/lib/forms'
import { useAuth } from '@/stores/auth'
import { PasswordInput } from '../components/PasswordInput'
import { useState } from 'react'

const schema = z.object({
  email: z.string().trim().min(1, 'Email is required').email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
})
type Values = z.infer<typeof schema>

export default function LoginPage() {
  const login = useAuth((s) => s.login)
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)
    try {
      const user = await login(values)
      const from = (location.state as { from?: string } | null)?.from
      navigate(user?.current_company ? (from ?? '/app') : '/onboarding', { replace: true })
    } catch (e) {
      const err = applyServerErrors(e, setError, ['email', 'password'])
      if (err.kind !== 'validation') setFormError(err.message)
    }
  })

  return (
    <div>
      <h1 className="text-[1.75rem] font-extrabold">Welcome back</h1>
      <p className="mt-1.5 text-sm text-muted">Sign in to your FleetFlow workspace.</p>

      {formError && <Alert tone="danger" className="mt-6">{formError}</Alert>}

      <form onSubmit={onSubmit} className="mt-8 space-y-5" noValidate>
        <Field label="Email" error={errors.email?.message}>
          <Input type="email" autoComplete="email" placeholder="you@company.com" leading={<Mail />} {...register('email')} />
        </Field>
        <Field label="Password" error={errors.password?.message} labelAside={<span className="text-xs text-subtle">Reset arrives in Phase 3</span>}>
          <PasswordInput autoComplete="current-password" placeholder="••••••••" {...register('password')} />
        </Field>
        <Button type="submit" size="lg" className="w-full" loading={isSubmitting}>
          Sign in <ArrowRight />
        </Button>
      </form>

      <p className="mt-8 text-center text-sm text-muted">
        New to FleetFlow?{' '}
        <Link to="/register" className="font-semibold text-primary hover:underline">Create an account</Link>
      </p>

      {import.meta.env.DEV && (
        <div className="mt-8 rounded-xl border border-dashed border-border-strong bg-surface-2 p-3.5 text-xs text-muted">
          <p className="font-semibold text-fg-soft">Development accounts (seeded)</p>
          <p className="mt-1"><code>owner@fleetflow.test</code> · <code>admin@fleetflow.test</code> — password <code>password</code></p>
        </div>
      )}
    </div>
  )
}
