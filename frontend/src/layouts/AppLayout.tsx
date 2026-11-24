import { Check, ChevronsUpDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Plus, UserCog, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { companyNavigation, filterNavigation, type NavGroup } from '@/app/navigation'
import { Logo, LogoMark } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import {
  Avatar, Badge, Button, DropdownContent, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, DropdownTrigger, Tooltip,
} from '@/components/ui'
import { companyApi } from '@/features/auth/api/authApi'
import { setActiveCompany } from '@/lib/http'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'

const ROLE_LABELS: Record<string, string> = {
  company_admin: 'Company Admin', fleet_manager: 'Fleet Manager', dispatcher: 'Dispatcher',
  driver: 'Driver', accountant: 'Accountant', customer: 'Customer',
}

function SidebarNav({ groups, collapsed, onNavigate }: { groups: NavGroup[]; collapsed: boolean; onNavigate?: () => void }) {
  return (
    <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Main">
      {groups.map((group) => (
        <div key={group.label}>
          {!collapsed && <p className="mb-2 px-3 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-sidebar-muted">{group.label}</p>}
          <ul className="space-y-0.5">
            {group.items.map((item) => {
              const Icon = item.icon
              const inner = (
                <>
                  <Icon className="size-[18px] shrink-0" />
                  {!collapsed && <span className="truncate">{item.label}</span>}
                  {!collapsed && item.phase && (
                    <span className="ml-auto rounded-md bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold text-sidebar-muted">P{item.phase}</span>
                  )}
                </>
              )
              const base = cn('group relative flex h-10 items-center gap-3 rounded-lg px-3 text-[0.875rem] font-medium transition-colors', collapsed && 'justify-center px-0')
              const node = item.phase ? (
                <span aria-disabled="true" className={cn(base, 'cursor-not-allowed text-sidebar-fg/45')}>{inner}</span>
              ) : (
                <NavLink
                  to={item.to}
                  end={item.to === '/app'}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    cn(base, isActive
                      ? 'bg-sidebar-active text-white before:absolute before:-left-3 before:top-2 before:bottom-2 before:w-[3px] before:rounded-r-full before:bg-gradient-to-b before:from-[#5b8cff] before:to-[#2dd4bf]'
                      : 'text-sidebar-fg hover:bg-white/[0.05] hover:text-white')
                  }
                >
                  {inner}
                </NavLink>
              )
              const tip = item.phase ? `${item.label} — arrives in Phase ${item.phase}` : item.label
              return (
                <li key={item.to}>
                  {collapsed || item.phase ? <Tooltip content={tip} side="right">{node}</Tooltip> : node}
                </li>
              )
            })}
          </ul>
        </div>
      ))}
    </nav>
  )
}

function WorkspaceSwitcher({ collapsed }: { collapsed: boolean }) {
  const { user, refresh } = useAuth()
  const navigate = useNavigate()
  const current = user?.current_company
  if (!current) return null

  async function switchTo(id: string) {
    try {
      await companyApi.switch(id)
      setActiveCompany(id)
      await refresh()
      navigate('/app')
      toast.success('Workspace switched')
    } catch (e) {
      toast.error((e as Error).message)
    }
  }

  return (
    <DropdownMenu>
      <DropdownTrigger asChild>
        <button
          className={cn(
            'flex w-full items-center gap-3 rounded-xl border border-sidebar-border bg-white/[0.03] p-2 text-left transition-colors hover:bg-white/[0.06]',
            collapsed && 'justify-center p-1.5',
          )}
        >
          <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-[#2459e6] to-[#0fa89a] text-xs font-bold text-white">
            {current.name.slice(0, 2).toUpperCase()}
          </span>
          {!collapsed && (
            <>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-white">{current.name}</span>
                <span className="block truncate text-xs text-sidebar-muted">{current.city} · {current.currency}</span>
              </span>
              <ChevronsUpDown className="size-4 text-sidebar-muted" />
            </>
          )}
        </button>
      </DropdownTrigger>
      <DropdownContent align="start" className="w-64">
        <DropdownLabel>Workspaces</DropdownLabel>
        {(user?.companies ?? []).map((c) => (
          <DropdownItem key={c.id} onSelect={() => c.id !== current.id && switchTo(c.id)}>
            <span className="grid size-6 place-items-center rounded-md bg-surface-3 text-[10px] font-bold text-fg">{c.name.slice(0, 2).toUpperCase()}</span>
            <span className="flex-1 truncate">{c.name}</span>
            {c.id === current.id && <Check className="!text-primary" />}
          </DropdownItem>
        ))}
        <DropdownSeparator />
        <DropdownItem onSelect={() => navigate('/onboarding')}><Plus /> New company</DropdownItem>
      </DropdownContent>
    </DropdownMenu>
  )
}

function UserMenu() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  if (!user) return null
  return (
    <DropdownMenu>
      <DropdownTrigger asChild>
        <button className="flex items-center gap-2.5 rounded-full p-0.5 pr-2 transition-colors hover:bg-surface-3" aria-label="Account menu">
          <Avatar name={user.name} className="size-8" />
          <span className="hidden text-left leading-tight md:block">
            <span className="block text-sm font-semibold text-fg">{user.name}</span>
            <span className="block text-xs text-muted">{ROLE_LABELS[user.roles[0] ?? ''] ?? 'Member'}</span>
          </span>
        </button>
      </DropdownTrigger>
      <DropdownContent align="end">
        <div className="px-2.5 py-2">
          <p className="text-sm font-semibold">{user.name}</p>
          <p className="truncate text-xs text-muted">{user.email}</p>
        </div>
        <DropdownSeparator />
        <DropdownItem disabled><UserCog /> Profile <Badge className="ml-auto">Phase 3</Badge></DropdownItem>
        <DropdownSeparator />
        <DropdownItem
          className="data-[highlighted]:text-danger"
          onSelect={async () => {
            await logout()
            navigate('/login', { replace: true })
          }}
        >
          <LogOut /> Sign out
        </DropdownItem>
      </DropdownContent>
    </DropdownMenu>
  )
}

export function AppLayout() {
  const { user } = useAuth()
  const location = useLocation()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(() => {
    try { return localStorage.getItem('ff-sidebar') === 'collapsed' } catch { return false }
  })

  useEffect(() => setMobileOpen(false), [location.pathname])
  useEffect(() => {
    try { localStorage.setItem('ff-sidebar', collapsed ? 'collapsed' : 'open') } catch { /* ignore */ }
  }, [collapsed])

  const groups = useMemo(() => filterNavigation(companyNavigation, user?.permissions ?? []), [user?.permissions])

  const sidebar = (isMobile: boolean) => {
    const c = collapsed && !isMobile
    return (
      <div className="relative flex h-full flex-col bg-sidebar text-sidebar-fg">
        <div className="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[radial-gradient(400px_200px_at_0%_0%,rgba(91,140,255,0.18),transparent)]" />
        <div className={cn('relative flex h-16 items-center px-5', c && 'justify-center px-0')}>
          <Link to="/app">{c ? <LogoMark /> : <Logo light />}</Link>
          {isMobile && (
            <Button variant="ghost" size="icon-sm" className="ml-auto text-sidebar-fg hover:bg-white/10 hover:text-white" onClick={() => setMobileOpen(false)} aria-label="Close menu">
              <X />
            </Button>
          )}
        </div>
        <div className={cn('relative px-3 pt-1', c && 'px-2')}><WorkspaceSwitcher collapsed={c} /></div>
        <SidebarNav groups={groups} collapsed={c} onNavigate={() => setMobileOpen(false)} />
        {!isMobile && (
          <div className="relative border-t border-sidebar-border p-3">
            <button
              onClick={() => setCollapsed((v) => !v)}
              className={cn('flex h-9 w-full items-center gap-3 rounded-lg px-3 text-sm text-sidebar-muted transition-colors hover:bg-white/[0.05] hover:text-white', c && 'justify-center px-0')}
              aria-label={c ? 'Expand sidebar' : 'Collapse sidebar'}
            >
              {c ? <PanelLeftOpen className="size-[18px]" /> : <><PanelLeftClose className="size-[18px]" /> Collapse</>}
            </button>
          </div>
        )}
      </div>
    )
  }

  return (
    <div className="min-h-dvh">
      {/* Desktop sidebar */}
      <aside className={cn('fixed inset-y-0 left-0 z-30 hidden border-r border-sidebar-border transition-[width] duration-200 lg:block', collapsed ? 'w-[76px]' : 'w-[264px]')}>
        {sidebar(false)}
      </aside>

      {/* Mobile drawer */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-navy-950/60 backdrop-blur-sm animate-fade-in" onClick={() => setMobileOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-[280px] max-w-[85vw] shadow-pop animate-slide-up">{sidebar(true)}</aside>
        </div>
      )}

      <div className={cn('transition-[padding] duration-200', collapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]')}>
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-bg/75 px-4 backdrop-blur-xl sm:px-6">
          <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Open menu">
            <Menu />
          </Button>
          <div className="min-w-0 flex-1">
            {user?.current_company?.status === 'active' && (
              <span className="hidden items-center gap-2 text-xs font-medium text-muted sm:inline-flex">
                <span className="relative flex size-2"><span className="absolute inset-0 animate-ping rounded-full bg-success/60" /><span className="relative size-2 rounded-full bg-success" /></span>
                Workspace online · {user.current_company.timezone}
              </span>
            )}
          </div>
          <ThemeToggle />
          <div className="mx-1 h-6 w-px bg-border" />
          <UserMenu />
        </header>
        <main className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
