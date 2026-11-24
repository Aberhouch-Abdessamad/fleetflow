import { AlertTriangle, History, Home, LayoutDashboard, LogOut, MapPin, Navigation, Receipt, WifiOff } from 'lucide-react'
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { LogoMark } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { useInterval } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { ago } from '@/lib/format'
import { realtimeEnabled, useChannel } from '@/lib/realtime'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Delivery, Driver } from '@/types/domain'
import { useGpsTracker, type GpsState } from './components/useGpsTracker'

export interface DriverDashboard {
  driver: Driver; on_shift: boolean; shift_started_at: string | null; can_reject: boolean
  pending_assignments: Delivery[]; active: Delivery[]
  stats: { completed_today: number; active: number; total_deliveries: number; on_time_rate: number | null; average_rating: number | null; working_hours: number }
}

interface Ctx { data: DriverDashboard | null; loading: boolean; error: string | null; reload: () => Promise<void>; gps: ReturnType<typeof useGpsTracker> }
const DriverCtx = createContext<Ctx | null>(null)
export const useDriver = () => useContext(DriverCtx)!

const GPS_LABEL: Record<GpsState, string> = {
  idle: 'Location sharing off', requesting: 'Waiting for GPS…', active: 'Sharing live location', denied: 'Location permission denied',
  unavailable: 'GPS not available on this device', error: 'GPS signal problem',
}

function Online() {
  const [online, setOnline] = useState(navigator.onLine)
  useEffect(() => {
    const on = () => setOnline(true), off = () => setOnline(false)
    window.addEventListener('online', on); window.addEventListener('offline', off)
    return () => { window.removeEventListener('online', on); window.removeEventListener('offline', off) }
  }, [])
  return online ? null : <div className="flex items-center justify-center gap-2 bg-danger px-4 py-1.5 text-xs font-semibold text-white"><WifiOff className="size-3.5" /> You're offline — actions will fail until you reconnect. GPS points are kept and sent later.</div>
}

/** Mobile-first driver shell (installable PWA-style). React Native can reuse the same /driver API later. */
export function DriverLayout() {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  const { data, loading, error, reload } = useAsync(() => api.get<DriverDashboard>('/driver/dashboard'), [])
  const sharing = Boolean(data && (data.on_shift || data.active.some((d) => ['accepted', 'picked_up', 'in_transit', 'arrived'].includes(d.status))))
  const gps = useGpsTracker(sharing)
  useChannel(data ? `driver.${data.driver.id}` : null, ['DriverAssignmentUpdated', 'DeliveryStatusUpdated'], () => void reload())
  useInterval(() => void reload(), realtimeEnabled ? 120_000 : 30_000)

  return (
    <DriverCtx.Provider value={{ data, loading, error: error?.message ?? null, reload, gps }}>
      <div className="mx-auto flex min-h-dvh max-w-xl flex-col bg-bg">
        <Online />
        <header className="sticky top-0 z-20 flex h-13 items-center gap-2.5 border-b border-border bg-bg/90 px-4 backdrop-blur-xl">
          <LogoMark className="size-6" />
          <div className="min-w-0 flex-1 leading-tight">
            <p className="truncate text-[0.8125rem] font-semibold">{user?.name}</p>
            <p className="truncate text-[11px] text-muted">{user?.current_company?.name}</p>
          </div>
          <ThemeToggle />
          {can('deliveries.view') && <Button variant="ghost" size="icon" onClick={() => navigate('/app')} aria-label="Back office"><LayoutDashboard /></Button>}
          <Button variant="ghost" size="icon" aria-label="Sign out" onClick={async () => { await logout(); navigate('/login') }}><LogOut /></Button>
        </header>
        {sharing && (
          <div className={cn('flex items-center gap-2 border-b border-border px-4 py-2 text-xs font-medium', gps.state === 'active' ? 'bg-accent-soft text-accent' : gps.state === 'requesting' ? 'bg-info-soft text-info' : 'bg-warning-soft text-warning')}>
            {gps.state === 'active' ? <Navigation className="size-3.5" /> : gps.state === 'requesting' ? <MapPin className="size-3.5 animate-pulse" /> : <AlertTriangle className="size-3.5" />}
            <span className="flex-1">{GPS_LABEL[gps.state]}{gps.last ? ` · ${ago(gps.last.at)}${gps.last.accuracy ? ` · ±${gps.last.accuracy} m` : ''}` : ''}{gps.queued > 0 ? ` · ${gps.queued} queued` : ''}</span>
            {gps.state === 'denied' && <span className="text-[11px]">Enable location in browser settings</span>}
          </div>
        )}
        <main className="flex-1 px-4 pb-28 pt-4"><Outlet /></main>
        <nav className="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-xl border-t border-border bg-bg/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl">
          <div className="grid grid-cols-3">
            {([['/driver', Home, 'Today'], ['/driver/history', History, 'History'], ['/driver/expenses', Receipt, 'Expenses']] as const).map(([to, Icon, l]) => (
              <NavLink key={to} to={to} end className={({ isActive }) => cn('flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium', isActive ? 'text-fg' : 'text-subtle')}>
                <Icon className="size-[18px]" />{l}
              </NavLink>
            ))}
          </div>
        </nav>
      </div>
    </DriverCtx.Provider>
  )
}

export function DriverSection({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
  return (
    <section className="mb-6">
      <div className="mb-2 flex items-center justify-between"><h2 className="text-xs font-medium text-subtle">{title}</h2>{action}</div>
      {children}
    </section>
  )
}
