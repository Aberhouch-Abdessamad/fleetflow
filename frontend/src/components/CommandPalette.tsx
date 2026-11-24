import { Dialog as D } from 'radix-ui'
import { ArrowRight, CornerDownLeft, LogOut, Moon, Plus, Search, Smartphone, Sun, UserCog, type LucideIcon } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { create } from 'zustand'
import type { NavGroup } from '@/app/navigation'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import { useTheme } from '@/stores/theme'

export const useCommandPalette = create<{ open: boolean; setOpen: (o: boolean) => void }>((set) => ({
  open: false,
  setOpen: (open) => set({ open }),
}))

interface Command { id: string; label: string; group: string; icon: LucideIcon; hint?: string; run: () => void }

/** ⌘K / Ctrl+K launcher: jump to any page you can access, or run a quick action. */
export function CommandPalette({ groups }: { groups: NavGroup[] }) {
  const { open, setOpen } = useCommandPalette()
  const navigate = useNavigate()
  const { user, logout, can } = useAuth()
  const { theme, toggle } = useTheme()
  const [q, setQ] = useState('')
  const [active, setActive] = useState(0)
  const listRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setOpen(!useCommandPalette.getState().open) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [setOpen])

  useEffect(() => { if (open) { setQ(''); setActive(0) } }, [open])

  const commands = useMemo<Command[]>(() => {
    const go = (to: string) => () => navigate(to)
    const list: Command[] = groups.flatMap((g) => g.items.map((i) => ({ id: i.to, label: i.label, group: 'Go to', icon: i.icon, hint: g.label, run: go(i.to) })))
    const inCompany = !!user?.current_company && groups.some((g) => g.items.some((i) => i.to.startsWith('/app')))
    if (inCompany && can('deliveries.create')) list.push({ id: 'new-delivery', label: 'Create delivery', group: 'Actions', icon: Plus, run: go('/app/deliveries/new') })
    if (inCompany && can('routes.manage')) list.push({ id: 'new-route', label: 'Routes · plan a route', group: 'Actions', icon: Plus, run: go('/app/routes') })
    if (user?.roles.includes('driver')) list.push({ id: 'driver', label: 'Open driver app', group: 'Actions', icon: Smartphone, run: go('/driver') })
    if (user?.current_company) list.push({ id: 'profile', label: 'Profile & security', group: 'Actions', icon: UserCog, run: go('/app/profile') })
    list.push({ id: 'theme', label: theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode', group: 'Actions', icon: theme === 'dark' ? Sun : Moon, run: toggle })
    list.push({ id: 'logout', label: 'Sign out', group: 'Actions', icon: LogOut, run: async () => { await logout(); navigate('/login', { replace: true }) } })
    return list
  }, [groups, navigate, user, can, theme, toggle, logout])

  const filtered = useMemo(() => {
    const t = q.trim().toLowerCase()
    if (!t) return commands
    return commands.filter((c) => `${c.label} ${c.hint ?? ''}`.toLowerCase().includes(t))
  }, [commands, q])

  useEffect(() => setActive(0), [q])
  useEffect(() => {
    listRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: 'nearest' })
  }, [active])

  const runAt = (i: number) => { const c = filtered[i]; if (!c) return; setOpen(false); c.run() }

  let lastGroup = ''
  return (
    <D.Root open={open} onOpenChange={setOpen}>
      <D.Portal>
        <D.Overlay className="fixed inset-0 z-50 bg-black/30 animate-fade-in dark:bg-black/60" />
        <D.Content
          className="fixed left-1/2 top-[14vh] z-50 w-[calc(100vw-2rem)] max-w-xl -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-surface shadow-pop ff-pop"
          onKeyDown={(e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, filtered.length - 1)) }
            if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)) }
            if (e.key === 'Enter') { e.preventDefault(); runAt(active) }
          }}
        >
          <D.Title className="sr-only">Command menu</D.Title>
          <D.Description className="sr-only">Search pages and actions</D.Description>
          <div className="flex items-center gap-2.5 border-b border-border px-4">
            <Search className="size-4 text-subtle" />
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search pages and actions…"
              className="h-12 flex-1 bg-transparent text-sm text-fg outline-none placeholder:text-subtle" aria-label="Search commands" />
            <kbd className="ff-kbd">Esc</kbd>
          </div>
          <div ref={listRef} className="max-h-[50vh] overflow-y-auto p-1.5" role="listbox">
            {filtered.length === 0 && <p className="px-3 py-10 text-center text-[0.8125rem] text-muted">No results for “{q}”.</p>}
            {filtered.map((c, i) => {
              const header = c.group !== lastGroup ? c.group : null
              lastGroup = c.group
              const Icon = c.icon
              return (
                <div key={c.id}>
                  {header && <p className="px-2.5 pb-1 pt-2.5 text-xs font-medium text-subtle">{header}</p>}
                  <button type="button" role="option" aria-selected={i === active} data-index={i}
                    onMouseMove={() => setActive(i)} onClick={() => runAt(i)}
                    className={cn('flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-[0.8125rem] transition-colors', i === active ? 'bg-surface-3 text-fg' : 'text-fg-soft')}>
                    <Icon className="size-4 text-muted" />
                    <span className="flex-1 truncate">{c.label}</span>
                    {c.hint && <span className="text-xs text-subtle">{c.hint}</span>}
                    {i === active && (c.group === 'Go to' ? <ArrowRight className="size-3.5 text-muted" /> : <CornerDownLeft className="size-3.5 text-muted" />)}
                  </button>
                </div>
              )
            })}
          </div>
          <div className="flex items-center gap-3 border-t border-border bg-surface-2 px-4 py-2 text-[11px] text-subtle">
            <span className="flex items-center gap-1"><kbd className="ff-kbd">↑</kbd><kbd className="ff-kbd">↓</kbd> navigate</span>
            <span className="flex items-center gap-1"><kbd className="ff-kbd">↵</kbd> open</span>
          </div>
        </D.Content>
      </D.Portal>
    </D.Root>
  )
}
