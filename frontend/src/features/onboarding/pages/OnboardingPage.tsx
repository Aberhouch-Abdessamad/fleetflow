import { ArrowRight, Check, LogOut } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Logo } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button, Card } from '@/components/ui'
import { companyApi } from '@/features/auth/api/authApi'
import { setActiveCompany } from '@/lib/http'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import { CompanyForm } from '../components/CompanyForm'

const STEPS = ['Account', 'Company workspace', 'Fleet setup']

export default function OnboardingPage() {
  const { user, refresh, logout } = useAuth()
  const navigate = useNavigate()
  const hasCompany = Boolean(user?.current_company)

  return (
    <div className="min-h-dvh">
      <header className="border-b border-border bg-surface/70 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-5">
          <Logo />
          <div className="flex items-center gap-1">
            <ThemeToggle />
            {hasCompany
              ? <Button variant="ghost" size="sm" onClick={() => navigate('/app')}>Back to dashboard</Button>
              : <Button variant="ghost" size="sm" onClick={() => logout()}><LogOut /> Sign out</Button>}
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-5 py-10">
        <ol className="mb-10 flex flex-wrap items-center gap-x-3 gap-y-2 animate-fade-in" aria-label="Onboarding progress">
          {STEPS.map((label, i) => {
            const done = i === 0
            const active = i === 1
            return (
              <li key={label} className="flex items-center gap-3">
                <span className={cn('flex items-center gap-2 text-sm font-medium', active ? 'text-fg' : 'text-muted')}>
                  <span className={cn('grid size-7 place-items-center rounded-full text-xs font-bold ring-1 ring-inset',
                    done ? 'bg-success text-white ring-success' : active ? 'bg-primary text-white ring-primary shadow-glow' : 'bg-surface text-muted ring-border-strong')}>
                    {done ? <Check className="size-3.5" /> : i + 1}
                  </span>
                  {label}
                </span>
                {i < STEPS.length - 1 && <span className="hidden h-px w-10 bg-border-strong sm:block" />}
              </li>
            )
          })}
        </ol>

        <div className="mb-8 animate-slide-up">
          <h1 className="text-3xl font-extrabold">{hasCompany ? 'Add another company' : `Welcome, ${user?.name.split(' ')[0]} — let's set up your workspace`}</h1>
          <p className="mt-2 max-w-2xl text-muted">
            Each company gets its own isolated workspace for vehicles, drivers, deliveries and finances. You'll be its Company Admin and can invite your team afterwards.
          </p>
        </div>

        <Card className="p-6 sm:p-8 animate-slide-up">
          <CompanyForm
            onSubmit={async (values) => {
              const company = await companyApi.create(values)
              setActiveCompany(company.id)
              await refresh()
              toast.success(`${company.name} is ready`)
              navigate('/app', { replace: true })
            }}
            footer={({ isSubmitting }) => (
              <div className="mt-8 flex flex-col-reverse items-stretch justify-between gap-3 border-t border-border pt-6 sm:flex-row sm:items-center">
                <p className="text-xs text-muted">You can change any of this later in Company Settings.</p>
                <Button type="submit" size="lg" loading={isSubmitting}>Create workspace <ArrowRight /></Button>
              </div>
            )}
          />
        </Card>
      </main>
    </div>
  )
}
