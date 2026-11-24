import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRight, Mail, Phone, User as UserIcon } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { Button, Field, Input } from '@/components/ui'
import { applyServerErrors } from '@/lib/forms'
import { useAuth } from '@/stores/auth'
import { PasswordInput, PasswordStrength } from '../components/PasswordInput'

const schema = z
  .object({
    name: z.string().trim().min(2, 'Enter your full name').max(120),
    email: z.string().trim().email('Enter a valid email'),
    phone: z.string().trim().max(32).optional(),
    password: z.string().min(8, 'At least 8 characters').regex(/[A-Za-z]/, 'Include a letter').regex(/\d/, 'Include a number'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })
type Values = z.infer<typeof schema>

export default function RegisterPage() {
  const signUp = useAuth((s) => s.register)
  const navigate = useNavigate()
  const { register, handleSubmit, watch, setError, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await signUp({ ...values, phone: values.phone || undefined })
      navigate('/onboarding', { replace: true })
    } catch (e) {
      applyServerErrors(e, setError, ['name', 'email', 'phone', 'password', 'password_confirmation'])
    }
  })

  return (
    <div>
      <p className="font-mono text-xs text-muted">Step 1 of 2</p>
      <h1 className="mt-2 text-2xl font-semibold tracking-[-0.025em]">Create your account</h1>
      <p className="mt-1 text-[0.8125rem] text-muted">Next, you'll set up your company workspace.</p>

      <form onSubmit={onSubmit} className="mt-7 space-y-4" noValidate>
        <Field label="Full name" error={errors.name?.message} required>
          <Input autoComplete="name" placeholder="Samira Haddad" leading={<UserIcon />} {...register('name')} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Work email" error={errors.email?.message} required>
            <Input type="email" autoComplete="email" placeholder="you@company.com" leading={<Mail />} {...register('email')} />
          </Field>
          <Field label="Phone" error={errors.phone?.message}>
            <Input type="tel" autoComplete="tel" placeholder="+212 6…" leading={<Phone />} {...register('phone')} />
          </Field>
        </div>
        <Field label="Password" error={errors.password?.message} required hint="8+ characters with letters and numbers.">
          <PasswordInput autoComplete="new-password" {...register('password')} />
        </Field>
        <PasswordStrength value={watch('password') ?? ''} />
        <Field label="Confirm password" error={errors.password_confirmation?.message} required>
          <PasswordInput autoComplete="new-password" {...register('password_confirmation')} />
        </Field>
        <Button type="submit" size="lg" className="w-full" loading={isSubmitting}>
          Continue <ArrowRight />
        </Button>
      </form>

      <p className="mt-8 text-center text-sm text-muted">
        Already have an account? <Link to="/login" className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg">Sign in</Link>
      </p>
    </div>
  )
}
