import { cn } from '@/lib/utils'

export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={cn('size-8', className)} aria-hidden="true">
      <defs>
        <linearGradient id="ff-g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#5b8cff" />
          <stop offset="1" stopColor="#2dd4bf" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="9" fill="#0f1d45" />
      <rect x="0.5" y="0.5" width="31" height="31" rx="8.5" fill="none" stroke="white" strokeOpacity="0.08" />
      <path d="M7.5 21.5 13.5 9l4.2 8.2 2.6-4.2 4.2 8.5" fill="none" stroke="url(#ff-g)" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="24.5" cy="21.5" r="2.3" fill="#2dd4bf" />
    </svg>
  )
}

export function Logo({ className, light }: { className?: string; light?: boolean }) {
  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <LogoMark />
      <span className={cn('font-display text-[1.15rem] font-extrabold tracking-tight', light ? 'text-white' : 'text-fg')}>
        Fleet<span className="bg-gradient-to-r from-[#5b8cff] to-[#2dd4bf] bg-clip-text text-transparent">Flow</span>
      </span>
    </span>
  )
}
