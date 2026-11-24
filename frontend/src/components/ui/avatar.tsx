import { cn, initials } from '@/lib/utils'

const PALETTE = ['from-blue-500 to-indigo-600', 'from-teal-400 to-emerald-600', 'from-sky-400 to-blue-600', 'from-violet-500 to-fuchsia-600', 'from-amber-400 to-orange-600']

export function Avatar({ name, src, className }: { name: string; src?: string | null; className?: string }) {
  const hash = [...name].reduce((a, c) => a + c.charCodeAt(0), 0)
  return src ? (
    <img src={src} alt="" className={cn('size-9 rounded-full object-cover ring-2 ring-surface', className)} />
  ) : (
    <span
      aria-hidden="true"
      className={cn('grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br text-xs font-bold text-white ring-2 ring-surface', PALETTE[hash % PALETTE.length], className)}
    >
      {initials(name) || '?'}
    </span>
  )
}
