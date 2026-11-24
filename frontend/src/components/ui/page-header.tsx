import type { ReactNode } from 'react'

/** Page title row. `eyebrow` renders as a quiet section crumb above the title. */
export function PageHeader({ title, description, eyebrow, actions }: { title: ReactNode; description?: ReactNode; eyebrow?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between animate-slide-up">
      <div className="min-w-0">
        {eyebrow && <p className="mb-1 text-xs font-medium text-subtle sm:hidden">{eyebrow}</p>}
        <h1 className="text-[1.375rem] font-semibold leading-tight">{title}</h1>
        {description && <p className="mt-1 max-w-2xl text-[0.8125rem] text-muted">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}
