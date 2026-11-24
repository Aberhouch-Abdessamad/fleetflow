import { cn, initials } from '@/lib/utils'

/** Quiet tinted avatars: soft background + matching ink, picked from the name. */
const PALETTE = [
  'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
  'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
  'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
  'bg-zinc-200 text-zinc-700 dark:bg-zinc-500/20 dark:text-zinc-300',
]

export function Avatar({ name, src, className }: { name: string; src?: string | null; className?: string }) {
  const hash = [...name].reduce((a, c) => a + c.charCodeAt(0), 0)
  return src ? (
    <img src={src} alt="" className={cn('size-8 rounded-full object-cover ring-1 ring-border', className)} />
  ) : (
    <span
      aria-hidden="true"
      className={cn('grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold ring-1 ring-inset ring-black/5 dark:ring-white/5', PALETTE[hash % PALETTE.length], className)}
    >
      {initials(name) || '?'}
    </span>
  )
}
