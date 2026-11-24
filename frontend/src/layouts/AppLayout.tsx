import {
  Bell, Check, CheckCheck, ChevronRight, ChevronsUpDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Plus, Search, ShieldCheck, Smartphone, UserCog, X,
} from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { adminNavigation, companyNavigation, filterNavigation, type NavGroup } from '@/app/navigation'
import { LogoMark } from '@/components/brand/Logo'
import { CommandPalette, useCommandPalette } from '@/components/CommandPalette'
import { ThemeToggle } from '@/components/ThemeToggle'
import {
  Avatar, Badge, Button, DropdownContent, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, DropdownTrigger, Spinner, Tooltip,
} from '@/components/ui'
import { companyApi } from '@/features/auth/api/authApi'
import { ago } from '@/lib/format'
import { setActiveCompany } from '@/lib/http'
import { cn } from '@/lib/utils'
import { useInterval } from '@/hooks/usePaged'
import { useAuth } from '@/stores/auth'
import { useNotifications } from '@/stores/notifications'

export const ROLE_LABELS: Record<string, string> = {
  company_admin: 'Company Admin', fleet_manager: 'Fleet Manager', dispatcher: 'Dispatcher',
  driver: 'Driver', accountant: 'Accountant', customer: 'Customer',
}

function SidebarNav({ groups, collapsed, onNavigate }: { groups: NavGroup[]; collapsed: boolean; onNavigate?: () => void }) {
  return (
    <nav className="flex-1 space-y-5 overflow-y-auto px-2.5 py-3" aria-label="Main">
      {groups.map((group) => (
        <div key={group.label}>
          {!collapsed && <p className="mb-1 px-2 text-xs font-medium text-sidebar-muted">{group.label}</p>}
          <ul className="space-y-px">
            {group.items.map((item) => {
              const Icon = item.icon
              const node = (
                <NavLink
                  to={item.to}
                  end={item.to === '/app' || item.to === '/admin'}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    cn('group relative flex h-8 items-center gap-2.5 rounded-md px-2 text-[0.8125rem] font-medium transition-colors', collapsed && 'justify-center px-0',
                      isActive
                        ? 'bg-sidebar-active text-fg shadow-[0_1px_2px_rgb(0_0_0/0.06),0_0_0_1px_var(--sidebar-border)]'
                        : 'text-sidebar-fg hover:bg-sidebar-2 hover:text-fg')
                  }
                >
                  {({ isActive }) => (<>
                    <Icon className={cn('size-4 shrink-0', isActive ? 'text-fg' : 'text-sidebar-muted group-hover:text-fg')} />
                    {!collapsed && <span className="truncate">{item.label}</span>}
                  </>)}
                </NavLink>
              )
              return <li key={item.to}>{collapsed ? <Tooltip content={item.label} side="right">{node}</Tooltip> : node}</li>
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
        <button className={cn('flex w-full items-center gap-2 rounded-md p-1.5 text-left transition-colors hover:bg-sidebar-2', collapsed && 'justify-center')}>
          <span className="grid size-6 shrink-0 place-items-center rounded-md bg-fg text-[10px] font-semibold text-bg">{current.name.slice(0, 2).toUpperCase()}</span>
          {!collapsed && (
            <>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-[0.8125rem] font-semibold text-fg">{current.name}</span>
              </span>
              <ChevronsUpDown className="size-3.5 text-sidebar-muted" />
            </>
          )}
        </button>
      </DropdownTrigger>
      <DropdownContent align="start" className="w-64">
        <DropdownLabel>{current.city} · {current.currency} · {current.timezone}</DropdownLabel>
        <DropdownSeparator />
        <DropdownLabel>Workspaces</DropdownLabel>
        {(user?.companies ?? []).map((c) => (
          <DropdownItem key={c.id} onSelect={() => c.id !== current.id && switchTo(c.id)}>
            <span className="grid size-5 place-items-center rounded bg-surface-3 text-[9px] font-semibold text-fg">{c.name.slice(0, 2).toUpperCase()}</span>
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

const TONE_DOT: Record<string, string> = { info: 'bg-info', success: 'bg-success', warning: 'bg-warning', danger: 'bg-danger' }

function NotificationBell() {
  const { unread, items, loading, load, markRead, markAll, refreshCount } = useNotifications()
  const navigate = useNavigate()
  useEffect(() => { void refreshCount() }, [refreshCount])
  useInterval(() => void refreshCount(), 30_000)

  return (
    <DropdownMenu onOpenChange={(o) => o && void load()}>
      <DropdownTrigger asChild>
        <Button variant="ghost" size="icon-sm" aria-label={`Notifications${unread ? ` (${unread} unread)` : ''}`} className="relative">
          <Bell />
          {unread > 0 && <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-primary ring-2 ring-bg" />}
        </Button>
      </DropdownTrigger>
      <DropdownContent align="end" className="w-[22rem] p-0">
        <div className="flex items-center justify-between border-b border-border px-3.5 py-2.5">
          <p className="text-[0.8125rem] font-semibold">Notifications</p>
          {unread > 0 && <button className="flex items-center gap-1 text-xs font-medium text-muted hover:text-fg" onClick={() => void markAll()}><CheckCheck className="size-3.5" /> Mark all read</button>}
        </div>
        <div className="max-h-96 overflow-y-auto">
          {loading && items.length === 0 ? <div className="grid place-items-center py-8"><Spinner className="text-primary" /></div>
            : items.length === 0 ? <p className="px-4 py-10 text-center text-[0.8125rem] text-muted">You're all caught up.</p>
            : items.map((n) => (
              <DropdownItem key={n.id} className="items-start gap-2.5 rounded-none border-b border-border px-3.5 py-2.5 last:border-0"
                onSelect={() => { if (!n.read) void markRead(n.id); if (n.data.link) navigate(n.data.link) }}>
                <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : TONE_DOT[n.data.tone] ?? 'bg-primary')} />
                <span className="min-w-0 flex-1">
                  <span className={cn('block text-[13px] leading-snug', n.read ? 'text-muted' : 'font-medium text-fg')}>{n.data.title}</span>
                  {n.data.body && <span className="mt-0.5 block truncate text-xs text-muted">{n.data.body}</span>}
                  <span className="mt-1 block text-[11px] text-subtle">{ago(n.created_at)}</span>
                </span>
              </DropdownItem>
            ))}
        </div>
        <Link to="/app/notifications" className="block border-t border-border px-4 py-2 text-center text-xs font-medium text-muted hover:bg-surface-2 hover:text-fg">View all notifications</Link>
      </DropdownContent>
    </DropdownMenu>
  )
}

export function UserMenu({ admin, compact }: { admin?: boolean; compact?: boolean }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  if (!user) return null
  return (
    <DropdownMenu>
      <DropdownTrigger asChild>
        <button className={cn('flex w-full items-center gap-2 rounded-md p-1.5 text-left transition-colors hover:bg-sidebar-2', compact && 'w-auto justify-center')} aria-label="Account menu">
          <Avatar name={user.name} className="size-7" />
          {!compact && (
            <span className="min-w-0 flex-1 leading-tight">
              <span className="block truncate text-[0.8125rem] font-medium text-fg">{user.name}</span>
              <span className="block truncate text-xs text-sidebar-muted">{admin ? 'Platform admin' : ROLE_LABELS[user.roles[0] ?? ''] ?? 'Member'}</span>
            </span>
          )}
        </button>
      </DropdownTrigger>
      <DropdownContent align={compact ? 'end' : 'start'} side={compact ? 'bottom' : 'top'} className="w-60">
        <div className="px-2 py-1.5">
          <p className="text-[0.8125rem] font-semibold">{user.name}</p>
          <p className="truncate text-xs text-muted">{user.email}</p>
        </div>
        <DropdownSeparator />
        {user.current_company && <DropdownItem onSelect={() => navigate('/app/profile')}><UserCog /> Profile & security</DropdownItem>}
        {user.roles.includes('driver') && <DropdownItem onSelect={() => navigate('/driver')}><Smartphone /> Driver app</DropdownItem>}
        {user.is_super_admin && !admin && <DropdownItem onSelect={() => navigate('/admin')}><ShieldCheck /> Platform admin</DropdownItem>}
        {admin && user.current_company && <DropdownItem onSelect={() => navigate('/app')}><ShieldCheck /> Back to workspace</DropdownItem>}
        <DropdownSeparator />
        <DropdownItem className="data-[highlighted]:text-danger" onSelect={async () => { await logout(); navigate('/login', { replace: true }) }}>
          <LogOut /> Sign out
        </DropdownItem>
      </DropdownContent>
    </DropdownMenu>
  )
}

function VerifyBanner() {
  const user = useAuth((s) => s.user)
  const [sent, setSent] = useState(false)
  if (!user || user.email_verified) return null
  return (
    <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-warning/20 bg-warning-soft px-4 py-1.5 text-center text-xs font-medium text-warning">
      Please verify your email address ({user.email}).
      <button className="font-semibold underline" disabled={sent} onClick={async () => {
        const { api } = await import('@/lib/api')
        await api.post('/auth/email/resend').catch(() => {})
        setSent(true)
        toast.success('Verification email sent')
      }}>{sent ? 'Sent' : 'Resend link'}</button>
    </div>
  )
}

function Breadcrumb({ groups }: { groups: NavGroup[] }) {
  const { pathname } = useLocation()
  let best: { group: string; label: string; to: string } | null = null
  for (const g of groups) for (const i of g.items) {
    const match = pathname === i.to || (i.to !== '/app' && i.to !== '/admin' && pathname.startsWith(`${i.to}/`))
    if (match && (!best || i.to.length > best.to.length)) best = { group: g.label, label: i.label, to: i.to }
  }
  const extra: Record<string, [string, string]> = { '/app/notifications': ['Account', 'Notifications'], '/app/profile': ['Account', 'Profile & security'] }
  if (!best && extra[pathname]) best = { group: extra[pathname][0], label: extra[pathname][1], to: pathname }
  if (!best) return null
  const deeper = pathname !== best.to
  const leaf = pathname.endsWith('/new') ? 'New' : pathname.endsWith('/edit') ? 'Edit' : 'Details'
  return (
    <nav aria-label="Breadcrumb" className="hidden min-w-0 items-center gap-1.5 text-[0.8125rem] sm:flex">
      <span className="text-subtle">{best.group}</span>
      <ChevronRight className="size-3.5 text-subtle" />
      {deeper ? <Link to={best.to} className="truncate text-muted hover:text-fg">{best.label}</Link> : <span className="truncate font-medium text-fg">{best.label}</span>}
      {deeper && <><ChevronRight className="size-3.5 text-subtle" /><span className="truncate font-medium text-fg">{leaf}</span></>}
    </nav>
  )
}

function SearchButton({ collapsed }: { collapsed?: boolean }) {
  const setOpen = useCommandPalette((s) => s.setOpen)
  const mac = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform)
  if (collapsed) return <Tooltip content="Search (⌘K)" side="right"><button onClick={() => setOpen(true)} aria-label="Search" className="mx-auto grid size-8 place-items-center rounded-md text-sidebar-muted hover:bg-sidebar-2 hover:text-fg"><Search className="size-4" /></button></Tooltip>
  return (
    <button onClick={() => setOpen(true)} className="flex h-8 w-full items-center gap-2 rounded-md border border-sidebar-border bg-surface px-2 text-[0.8125rem] text-subtle shadow-xs transition-colors hover:border-border-strong hover:text-muted">
      <Search className="size-3.5" /><span className="flex-1 text-left">Search…</span>
      <kbd className="ff-kbd">{mac ? '⌘' : 'Ctrl'}</kbd><kbd className="ff-kbd -ml-1">K</kbd>
    </button>
  )
}

export function AppLayout({ variant = 'company' }: { variant?: 'company' | 'admin' }) {
  const { user } = useAuth()
  const location = useLocation()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(() => {
    try { return localStorage.getItem('ff-sidebar') === 'collapsed' } catch { return false }
  })
  useEffect(() => { setMobileOpen(false); document.getElementById('ff-scroll')?.scrollTo({ top: 0 }) }, [location.pathname])
  useEffect(() => { try { localStorage.setItem('ff-sidebar', collapsed ? 'collapsed' : 'open') } catch { /* ignore */ } }, [collapsed])

  const groups = useMemo(() => variant === 'admin' ? adminNavigation : filterNavigation(companyNavigation, user?.permissions ?? []), [user?.permissions, variant])
  const live = variant === 'company' && user?.current_company?.status === 'active'

  const sidebar = (isMobile: boolean) => {
    const c = collapsed && !isMobile
    return (
      <div className="flex h-full flex-col bg-sidebar">
        <div className={cn('flex h-12 items-center gap-2 px-3', c && 'justify-center px-0')}>
          {variant === 'admin'
            ? <Link to="/admin" className={cn('flex min-w-0 items-center gap-2 p-1.5', c && 'p-0')}><LogoMark className="size-6" />{!c && <span className="truncate text-[0.8125rem] font-semibold">Platform admin</span>}</Link>
            : <div className="min-w-0 flex-1"><WorkspaceSwitcher collapsed={c} /></div>}
          {isMobile && <Button variant="ghost" size="icon-sm" className="ml-auto" onClick={() => setMobileOpen(false)} aria-label="Close menu"><X /></Button>}
        </div>
        <div className={cn('px-2.5 pb-1', c && 'px-0')}><SearchButton collapsed={c} /></div>
        <SidebarNav groups={groups} collapsed={c} onNavigate={() => setMobileOpen(false)} />
        <div className={cn('space-y-1 p-2.5', c && 'flex flex-col items-center px-0')}>
          {!isMobile && (
            <button onClick={() => setCollapsed((v) => !v)} aria-label={c ? 'Expand sidebar' : 'Collapse sidebar'}
              className={cn('flex h-8 w-full items-center gap-2.5 rounded-md px-2 text-[0.8125rem] text-sidebar-muted transition-colors hover:bg-sidebar-2 hover:text-fg', c && 'w-8 justify-center px-0')}>
              {c ? <PanelLeftOpen className="size-4" /> : <><PanelLeftClose className="size-4" /> Collapse</>}
            </button>
          )}
          <UserMenu admin={variant === 'admin'} compact={c} />
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-dvh bg-canvas">
      <CommandPalette groups={groups} />
      <aside className={cn('fixed inset-y-0 left-0 z-30 hidden transition-[width] duration-200 lg:block', collapsed ? 'w-[60px]' : 'w-[232px]')}>{sidebar(false)}</aside>
      {mobileOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-black/30 animate-fade-in" onClick={() => setMobileOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-[260px] max-w-[85vw] border-r border-border shadow-pop animate-slide-up">{sidebar(true)}</aside>
        </div>
      )}
      <div className={cn('transition-[padding] duration-200 lg:py-2 lg:pr-2', collapsed ? 'lg:pl-[60px]' : 'lg:pl-[232px]')}>
        <div id="ff-scroll" className="min-h-dvh bg-bg lg:h-[calc(100dvh-1rem)] lg:min-h-0 lg:overflow-y-auto lg:rounded-xl lg:border lg:border-border lg:shadow-[0_1px_3px_rgb(0_0_0/0.03)]">
          <VerifyBanner />
          <header className="sticky top-0 z-20 flex h-12 items-center gap-2 border-b border-border bg-bg/85 px-3 backdrop-blur-md sm:px-5">
            <Button variant="ghost" size="icon-sm" className="lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Open menu"><Menu /></Button>
            <div className="flex min-w-0 flex-1 items-center gap-3">
              {variant === 'admin' && <Badge tone="primary"><ShieldCheck /> Super Admin</Badge>}
              <Breadcrumb groups={groups} />
            </div>
            {live && (
              <span className="mr-1 hidden items-center gap-1.5 rounded-md border border-border px-2 py-1 font-mono text-[11px] text-muted md:inline-flex">
                <span className="relative flex size-1.5"><span className="absolute inset-0 animate-ping rounded-full bg-accent/70" /><span className="relative size-1.5 rounded-full bg-accent" /></span>
                {user?.current_company?.timezone}
              </span>
            )}
            <Button variant="ghost" size="icon-sm" className="lg:hidden" onClick={() => useCommandPalette.getState().setOpen(true)} aria-label="Search"><Search /></Button>
            <ThemeToggle className="size-7" />
            {variant === 'company' && <NotificationBell />}
            <div className="lg:hidden"><UserMenu admin={variant === 'admin'} compact /></div>
          </header>
          <main className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  )
}
