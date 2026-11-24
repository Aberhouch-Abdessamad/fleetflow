import { CheckCircle2, MapPin } from 'lucide-react'
import { Link, Outlet } from 'react-router-dom'
import { Logo } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'

const POINTS = [
  'Dispatch, track and prove every delivery in one place',
  'Isolated, secure workspace for every company',
  'Fleet, drivers, maintenance and costs — connected',
]

/** Decorative route illustration (pure SVG — not data). */
function RouteArt() {
  return (
    <svg viewBox="0 0 520 360" className="absolute inset-x-0 bottom-0 w-full opacity-90" aria-hidden="true">
      <defs>
        <linearGradient id="route" x1="0" x2="1">
          <stop offset="0" stopColor="#5b8cff" />
          <stop offset="1" stopColor="#2dd4bf" />
        </linearGradient>
        <radialGradient id="glow">
          <stop offset="0" stopColor="#2dd4bf" stopOpacity="0.5" />
          <stop offset="1" stopColor="#2dd4bf" stopOpacity="0" />
        </radialGradient>
      </defs>
      <path d="M-20 300 C 80 300, 90 190, 190 200 S 320 300, 380 210 S 470 90, 560 110" fill="none" stroke="white" strokeOpacity="0.06" strokeWidth="22" strokeLinecap="round" />
      <path d="M-20 300 C 80 300, 90 190, 190 200 S 320 300, 380 210 S 470 90, 560 110" fill="none" stroke="url(#route)" strokeWidth="2.5" strokeDasharray="2 10" strokeLinecap="round" className="animate-dash" />
      {[[190, 200], [380, 210]].map(([x, y]) => (
        <g key={x}>
          <circle cx={x} cy={y} r="26" fill="url(#glow)" />
          <circle cx={x} cy={y} r="6" fill="#0b1530" stroke="#2dd4bf" strokeWidth="2.5" />
        </g>
      ))}
    </svg>
  )
}

export function AuthLayout() {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
      <aside className="relative hidden overflow-hidden bg-navy-900 p-10 text-white lg:flex lg:flex-col xl:p-14">
        <div className="pointer-events-none absolute -left-40 -top-40 size-[520px] rounded-full bg-[#2459e6]/30 blur-[120px]" />
        <div className="pointer-events-none absolute -bottom-32 right-0 size-[420px] rounded-full bg-[#0fa89a]/20 blur-[110px]" />
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.07)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent_85%)]" />
        <RouteArt />

        <Link to="/" className="relative z-10"><Logo light /></Link>

        <div className="relative z-10 mt-auto mb-40 max-w-lg animate-slide-up">
          <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur">
            <MapPin className="size-3.5 text-[#2dd4bf]" /> Fleet & delivery operations platform
          </span>
          <h2 className="mt-5 font-display text-[2.5rem] font-extrabold leading-[1.1] tracking-tight text-white">
            <span className="block">Every vehicle.</span>
            <span className="block">Every route.</span>
            <span className="block bg-gradient-to-r from-[#7aa2ff] to-[#2dd4bf] bg-clip-text text-transparent">Every delivery.</span>
          </h2>
          <ul className="mt-8 space-y-3.5">
            {POINTS.map((p) => (
              <li key={p} className="flex items-start gap-3 text-[0.95rem] text-white/75">
                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-[#2dd4bf]" /> {p}
              </li>
            ))}
          </ul>
        </div>
      </aside>

      <main className="relative flex flex-col px-5 py-6 sm:px-10">
        <div className="flex items-center justify-between">
          <Link to="/" className="lg:invisible"><Logo /></Link>
          <ThemeToggle />
        </div>
        <div className="mx-auto flex w-full max-w-[420px] flex-1 flex-col justify-center py-10 animate-slide-up">
          <Outlet />
        </div>
        <p className="text-center text-xs text-subtle">© {new Date().getFullYear()} FleetFlow</p>
      </main>
    </div>
  )
}
