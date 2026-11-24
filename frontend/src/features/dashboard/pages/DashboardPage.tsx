import { ArrowUpRight, BarChart3, Building2, CheckCircle2, Circle, KeyRound, Settings, Sparkles } from 'lucide-react'
import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, CardBody, CardHeader, EmptyState } from '@/components/ui'
import { ApiStatusCard } from '@/features/system/components/ApiStatusCard'
import { countries } from '@/lib/locale'
import { useAuth } from '@/stores/auth'

const ROLE_LABELS: Record<string, string> = {
  company_admin: 'Company Admin', fleet_manager: 'Fleet Manager', dispatcher: 'Dispatcher',
  driver: 'Driver', accountant: 'Accountant', customer: 'Customer',
}

/** Delivery plan of the project itself (not business data). */
const ROADMAP = [
  { phase: 1, title: 'Foundation, auth architecture & tenancy core', done: true },
  { phase: 2, title: 'Full database schema & multi-tenancy', done: false },
  { phase: 3, title: 'Authentication, team & roles', done: false },
  { phase: 4, title: 'Fleet & vehicles', done: false },
  { phase: 5, title: 'Drivers', done: false },
  { phase: 6, title: 'Customers & deliveries', done: false },
]

function greeting(tz: string) {
  const h = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hour12: false, timeZone: tz }).format(new Date()))
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
}

export default function DashboardPage() {
  const user = useAuth((s) => s.user)!
  const company = user.current_company!
  const countryName = useMemo(() => countries().find((c) => c.code === company.country)?.name ?? company.country, [company.country])
  const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', timeZone: company.timezone }).format(new Date())

  const permissionGroups = useMemo(() => {
    const groups = new Map<string, number>()
    user.permissions.forEach((p) => groups.set(p.split('.')[0]!, (groups.get(p.split('.')[0]!) ?? 0) + 1))
    return [...groups.entries()]
  }, [user.permissions])

  return (
    <div className="space-y-6">
      {/* Hero */}
      <section className="relative overflow-hidden rounded-2xl bg-navy-900 px-6 py-7 text-white shadow-pop sm:px-8 animate-slide-up">
        <div className="pointer-events-none absolute -right-20 -top-24 size-80 rounded-full bg-[#2459e6]/40 blur-[90px]" />
        <div className="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-[#0fa89a]/25 blur-[90px]" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.08)_1px,transparent_1px)] [background-size:20px_20px] [mask-image:linear-gradient(to_left,black,transparent_70%)]" />
        <div className="relative flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-medium text-white/60">{today}</p>
            <h1 className="mt-1 text-[1.75rem] font-extrabold text-white sm:text-3xl">
              {greeting(company.timezone)}, {user.name.split(' ')[0]}
            </h1>
            <p className="mt-2 max-w-xl text-white/70">
              You're in the <span className="font-semibold text-white">{company.name}</span> workspace as{' '}
              {user.roles.map((r) => ROLE_LABELS[r] ?? r).join(', ') || 'a member'}.
            </p>
          </div>
          {user.permissions.includes('company.update') && (
            <Button asChild variant="outline" className="border-white/15 bg-white/10 text-white backdrop-blur hover:bg-white/15 hover:border-white/25">
              <Link to="/app/settings"><Settings /> Workspace settings</Link>
            </Button>
          )}
        </div>
      </section>

      {/* KPIs — intentionally empty until real operational data exists */}
      <Card className="animate-slide-up [animation-delay:60ms]">
        <EmptyState
          icon={<BarChart3 />}
          title="Operational KPIs will appear here"
          description="Vehicle availability, delivery volumes, on-time rate and revenue are calculated from your real records once the fleet and delivery modules are live (Phases 4–6). Nothing is shown until there is data to measure."
        />
      </Card>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="animate-slide-up [animation-delay:100ms]">
          <CardHeader icon={<Building2 />} title="Workspace" description="Company profile" />
          <CardBody>
            <dl className="space-y-3 text-sm">
              {[
                ['Company', company.name],
                ['Location', `${company.city}, ${countryName}`],
                ['Timezone', company.timezone.replace(/_/g, ' ')],
                ['Currency', company.currency],
                ['Contact', company.contact_email],
              ].map(([k, v]) => (
                <div key={k} className="flex items-center justify-between gap-4">
                  <dt className="text-muted">{k}</dt>
                  <dd className="truncate text-right font-medium text-fg">{v}</dd>
                </div>
              ))}
              <div className="flex items-center justify-between gap-4">
                <dt className="text-muted">Status</dt>
                <dd><Badge dot tone="success">Active</Badge></dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        <Card className="animate-slide-up [animation-delay:140ms]">
          <CardHeader icon={<KeyRound />} title="Your access" description={`${user.permissions.length} permissions in this company`} />
          <CardBody>
            <div className="mb-4 flex flex-wrap gap-2">
              {user.roles.map((r) => <Badge key={r} tone="primary">{ROLE_LABELS[r] ?? r}</Badge>)}
            </div>
            <div className="flex flex-wrap gap-1.5">
              {permissionGroups.map(([module, count]) => (
                <span key={module} className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-2 py-1 text-xs text-fg-soft">
                  <span className="capitalize">{module}</span>
                  <span className="rounded bg-surface-3 px-1 font-semibold text-muted">{count}</span>
                </span>
              ))}
            </div>
          </CardBody>
        </Card>

        <div className="animate-slide-up [animation-delay:180ms]"><ApiStatusCard /></div>
      </div>

      <Card className="animate-slide-up [animation-delay:220ms]">
        <CardHeader
          icon={<Sparkles />}
          title="Platform rollout"
          description="Modules are enabled phase by phase. Locked items in the sidebar show their phase."
          action={<Button asChild variant="ghost" size="sm"><Link to="/system/status">System status <ArrowUpRight /></Link></Button>}
        />
        <CardBody>
          <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {ROADMAP.map((r) => (
              <li key={r.phase} className="flex items-start gap-3 rounded-xl border border-border bg-surface-2 p-3.5">
                {r.done ? <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" /> : <Circle className="mt-0.5 size-5 shrink-0 text-border-strong" />}
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-subtle">Phase {r.phase}</p>
                  <p className="mt-0.5 text-sm font-medium text-fg">{r.title}</p>
                </div>
              </li>
            ))}
          </ol>
        </CardBody>
      </Card>
    </div>
  )
}
