import { MapPinned, ShieldCheck, Workflow } from 'lucide-react'
import { Link, Outlet } from 'react-router-dom'
import { Logo } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'

const POINTS = [
  { icon: Workflow, title: 'Dispatch with confidence', text: 'Eligibility checks for licence, capacity and conflicts before anything is assigned.' },
  { icon: MapPinned, title: 'Track what is really moving', text: 'Positions come from drivers on shift — stale data is labelled, never guessed.' },
  { icon: ShieldCheck, title: 'Isolated by design', text: 'Every company gets its own workspace, roles and audit trail.' },
]

/** Decorative line-art route (pure SVG — not data). */
function RouteArt() {
  const d = 'M40 300 C 120 300, 130 200, 220 205 S 330 290, 390 215 S 470 110, 540 120'
  return (
    <svg viewBox="0 0 580 360" className="w-full" aria-hidden="true">
      <path d={d} fill="none" stroke="var(--border-strong)" strokeWidth="14" strokeLinecap="round" opacity="0.5" />
      <path d={d} fill="none" stroke="var(--fg)" strokeWidth="1.5" strokeDasharray="1 7" strokeLinecap="round" className="animate-dash" />
      {[[40, 300], [220, 205], [390, 215]].map(([x, y]) => (
        <circle key={x} cx={x} cy={y} r="5" fill="var(--bg)" stroke="var(--fg)" strokeWidth="1.5" />
      ))}
      <circle cx="540" cy="120" r="14" fill="var(--primary)" opacity="0.15" />
      <circle cx="540" cy="120" r="6" fill="var(--primary)" />
    </svg>
  )
}

export function AuthLayout() {
  return (
    <div className="grid min-h-dvh bg-bg lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <main className="relative flex flex-col px-5 py-5 sm:px-10">
        <div className="flex items-center justify-between">
          <Link to="/"><Logo /></Link>
          <ThemeToggle />
        </div>
        <div className="mx-auto flex w-full max-w-[380px] flex-1 flex-col justify-center py-10 animate-slide-up">
          <Outlet />
        </div>
        <p className="text-xs text-subtle">© {new Date().getFullYear()} FleetFlow</p>
      </main>

      <aside className="relative m-2 hidden overflow-hidden rounded-xl border border-border bg-canvas lg:flex lg:flex-col">
        <div className="ff-lines-bg pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_at_60%_40%,black_20%,transparent_75%)]" />
        <div className="relative flex flex-1 flex-col justify-center px-12 xl:px-16">
          <p className="font-mono text-xs text-muted">Fleet &amp; delivery operations</p>
          <h2 className="mt-3 max-w-md text-[2rem] font-semibold leading-[1.15] tracking-[-0.03em]">
            Every vehicle, route and delivery — <span className="text-muted">in one calm workspace.</span>
          </h2>
          <div className="-mx-4 mt-6 max-w-xl"><RouteArt /></div>
          <ul className="mt-4 grid max-w-xl gap-5 xl:grid-cols-3">
            {POINTS.map(({ icon: Icon, title, text }) => (
              <li key={title}>
                <span className="grid size-7 place-items-center rounded-md border border-border bg-surface shadow-xs"><Icon className="size-3.5" /></span>
                <p className="mt-2.5 text-[0.8125rem] font-medium">{title}</p>
                <p className="mt-0.5 text-xs leading-relaxed text-muted">{text}</p>
              </li>
            ))}
          </ul>
        </div>
      </aside>
    </div>
  )
}
