import { cn } from '@/lib/utils'

/** Monochrome mark: an ink tile with a route line ending in a stop. Inverts in dark mode. */
export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={cn('size-7 text-fg', className)} aria-hidden="true">
      <rect width="32" height="32" rx="8" fill="currentColor" />
      <path d="M8 21.5 13.2 10l4 7.6 2.5-3.8 3.8 7.7" fill="none" stroke="var(--bg)" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="23.6" cy="21.6" r="2.1" fill="var(--primary)" />
    </svg>
  )
}

export function Logo({ className, light }: { className?: string; light?: boolean }) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <LogoMark className={light ? 'text-white [--bg:#09090b]' : undefined} />
      <span className={cn('text-[0.8125rem] font-semibold tracking-tight', light ? 'text-white' : 'text-fg')}>FleetFlow</span>
    </span>
  )
}
