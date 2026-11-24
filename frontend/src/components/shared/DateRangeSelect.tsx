import { CalendarRange } from 'lucide-react'
import { Input, Select } from '@/components/ui'

export interface RangeValue { range: string; from?: string; to?: string }
export const PRESETS = [
  ['today', 'Today'], ['yesterday', 'Yesterday'], ['last_7_days', 'Last 7 days'], ['last_30_days', 'Last 30 days'],
  ['this_month', 'This month'], ['last_month', 'Last month'], ['last_90_days', 'Last 90 days'], ['this_year', 'This year'], ['custom', 'Custom range'],
] as const

export function DateRangeSelect({ value, onChange }: { value: RangeValue; onChange: (v: RangeValue) => void }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="relative">
        <CalendarRange className="pointer-events-none absolute left-2.5 top-1/2 z-10 size-3.5 -translate-y-1/2 text-subtle" />
        <Select className="w-40 pl-8.5" value={value.range} onChange={(e) => onChange({ ...value, range: e.target.value })} aria-label="Date range">
          {PRESETS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </Select>
      </div>
      {value.range === 'custom' && (
        <>
          <Input type="date" className="w-38" value={value.from ?? ''} onChange={(e) => onChange({ ...value, from: e.target.value })} aria-label="From" />
          <Input type="date" className="w-38" value={value.to ?? ''} onChange={(e) => onChange({ ...value, to: e.target.value })} aria-label="To" />
        </>
      )}
    </div>
  )
}
