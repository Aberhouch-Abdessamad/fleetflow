import { Fuel, Plus, Receipt } from 'lucide-react'
import { useState } from 'react'
import { Button, EmptyState, Skeleton, StatusBadge } from '@/components/ui'
import { ExpenseForm } from '@/features/expenses/pages/ExpensesPage'
import { FuelForm } from '@/features/fuel/pages/FuelPage'
import { usePaged } from '@/hooks/usePaged'
import { date, label, money } from '@/lib/format'
import type { Expense } from '@/types/domain'
import { DriverSection, useDriver } from '../DriverApp'

export default function DriverExpensesPage() {
  const { data } = useDriver()
  const { rows, loading, reload } = usePaged<Expense>('/expenses')
  const [exp, setExp] = useState(false)
  const [fuel, setFuel] = useState(false)
  const vehicleId = data?.driver.vehicle?.id
  return (
    <>
      <div className="mb-6 grid grid-cols-2 gap-2">
        <Button size="lg" variant="outline" onClick={() => setExp(true)}><Plus /> Expense</Button>
        <Button size="lg" variant="outline" disabled={!vehicleId} onClick={() => setFuel(true)}><Fuel /> Fuel</Button>
      </div>
      <DriverSection title="My expenses">
        {loading && !rows.length ? <Skeleton className="h-40" /> : rows.length === 0 ? <EmptyState className="ff-card" icon={<Receipt />} title="No expenses submitted" />
          : <ul className="ff-card divide-y divide-border">{rows.map((e) => (
            <li key={e.id} className="flex items-center justify-between gap-3 p-4 text-sm">
              <div className="min-w-0"><p className="truncate font-semibold">{e.description}</p><p className="text-xs text-muted">{label(e.category)} · {date(e.expense_date)}</p>{e.rejection_reason && <p className="text-xs text-danger">{e.rejection_reason}</p>}</div>
              <div className="text-right"><p className="font-semibold">{money(e.amount)}</p><StatusBadge status={e.status} /></div>
            </li>))}</ul>}
      </DriverSection>
      {exp && <ExpenseForm open={exp} onOpenChange={setExp} onSaved={() => void reload()} />}
      {fuel && vehicleId && <FuelForm open={fuel} onOpenChange={setFuel} fixedVehicleId={vehicleId} onSaved={() => void reload()} />}
    </>
  )
}
