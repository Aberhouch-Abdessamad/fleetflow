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
  const { register, handleSubmit, setError, setValue, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)
    try {
      const user = await login(values)
      const from = (location.state as { from?: string } | null)?.from
      navigate(from ?? (user?.is_super_admin && !user.current_company ? '/admin' : !user?.current_company ? '/onboarding' : user.roles.length === 1 && user.roles[0] === 'driver' ? '/driver' : '/app'), { replace: true })
    } catch (e) {
      const err = applyServerErrors(e, setError, ['email', 'password'])
      if (err.kind !== 'validation') setFormError(err.message)
    }
  })

  return (
    <div>
      <h1 className="text-2xl font-semibold tracking-[-0.025em]">Welcome back</h1>
      <p className="mt-1 text-[0.8125rem] text-muted">Sign in to your FleetFlow workspace.</p>

      {formError && <Alert tone="danger" className="mt-6">{formError}</Alert>}

      <form onSubmit={onSubmit} className="mt-7 space-y-4" noValidate>
        <Field label="Email" error={errors.email?.message}>
          <Input type="email" autoComplete="email" placeholder="you@company.com" leading={<Mail />} {...register('email')} />
        </Field>
        <Field label="Password" error={errors.password?.message} labelAside={<Link to="/forgot-password" className="text-xs font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg">Forgot password?</Link>}>
          <PasswordInput autoComplete="current-password" placeholder="••••••••" {...register('password')} />
        </Field>
        <Button type="submit" size="lg" className="w-full" loading={isSubmitting}>
          Sign in <ArrowRight />
        </Button>
      </form>

      <p className="mt-6 text-center text-[0.8125rem] text-muted">
        New to FleetFlow?{' '}
        <Link to="/register" className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg">Create an account</Link>
      </p>

      {import.meta.env.DEV && (
        <div className="mt-8 rounded-lg border border-dashed border-border-strong p-3 text-xs">
          <p className="flex items-center justify-between text-muted"><span className="font-medium text-fg-soft">Seeded dev accounts</span><span>click to fill · password <code className="font-mono">password</code></span></p>
          <div className="mt-2 grid grid-cols-2 gap-1">
            {[['owner', 'Company admin'], ['dispatch', 'Dispatcher'], ['fleet', 'Fleet manager'], ['finance', 'Accountant'], ['driver', 'Driver app'], ['admin', 'Super admin']].map(([u, r]) => (
              <button key={u} type="button" onClick={() => { setValue('email', `${u}@fleetflow.test`); setValue('password', 'password') }}
                className="rounded-md px-2 py-1 text-left transition-colors hover:bg-surface-3">
                <span className="block font-mono text-[11px] text-fg-soft">{u}@</span><span className="text-subtle">{r}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
