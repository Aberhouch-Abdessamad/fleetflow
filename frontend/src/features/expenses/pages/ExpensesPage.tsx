import { Check, Download, Plus, Receipt, X } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import {
  Button, Card, DataTable, DropdownContent, DropdownItem, DropdownMenu, DropdownTrigger, Field, FilterSelect, FormModal, Input, PageHeader, Select, StatCard, StatusBadge, Textarea, Toolbar, type Column,
} from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { date, label, money } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Expense, Vehicle } from '@/types/domain'

export const EXPENSE_CATEGORIES = ['fuel', 'maintenance', 'repairs', 'tolls', 'parking', 'insurance', 'vehicle_registration', 'driver_expenses', 'other']

export function ExpenseForm({ open, onOpenChange, onSaved }: { open: boolean; onOpenChange: (o: boolean) => void; onSaved: () => void }) {
  const can = useAuth((s) => s.can)
  const vehicles = useOptions<Vehicle>(can('vehicles.view') ? '/vehicles' : null)
  const [f, setF] = useState({ category: 'tolls', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10), vehicle_id: '' })
  const [receipt, setReceipt] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const s = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title="Submit expense" size="md" submitting={busy} submitLabel="Submit" onSubmit={async (e) => {
      e.preventDefault(); setBusy(true); setErrors({})
      try { await api.upload('/expenses', { ...f, receipt }); toast.success('Expense submitted for approval'); onOpenChange(false); onSaved() }
      catch (err) { const x = toApiError(err); setErrors(x.errors); toast.error(x.message) } finally { setBusy(false) }
    }}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Category" required><Select value={f.category} onChange={s('category')}>{EXPENSE_CATEGORIES.map((c) => <option key={c} value={c}>{label(c)}</option>)}</Select></Field>
        <Field label="Date" required error={errors.expense_date?.[0]}><Input type="date" value={f.expense_date} onChange={s('expense_date')} /></Field>
        <Field label="Amount" required error={errors.amount?.[0]}><Input type="number" step="0.01" min="0.01" value={f.amount} onChange={s('amount')} /></Field>
        {vehicles.length > 0 && <Field label="Vehicle"><Select value={f.vehicle_id} onChange={s('vehicle_id')}><option value="">—</option>{vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_number}</option>)}</Select></Field>}
        <Field label="Description" required error={errors.description?.[0]} className="sm:col-span-2"><Input value={f.description} onChange={s('description')} /></Field>
        <Field label="Receipt" className="sm:col-span-2"><Input type="file" accept="application/pdf,image/*" capture="environment" className="pt-1.5" onChange={(e) => setReceipt(e.target.files?.[0] ?? null)} /></Field>
      </div>
    </FormModal>
  )
}

export default function ExpensesPage() {
  const { can, user } = useAuth()
  const [open, setOpen] = useState(false)
  const [rejecting, setRejecting] = useState<Expense | null>(null)
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const vehicles = useOptions<Vehicle>(can('vehicles.view') ? '/vehicles' : null)
  const { rows, meta, filters, set, loading, reload } = usePaged<Expense>('/expenses', { status: '', category: '', vehicle_id: '', from: '', to: '', search: '' })
  const totals = meta?.totals as Record<string, { amount: string; count: number }> | undefined

  const approve = async (e: Expense) => {
    try { await api.patch(`/expenses/${e.id}/approve`); toast.success('Approved'); void reload() } catch (err) { toast.error(toApiError(err).message) }
  }
  const columns: Column<Expense>[] = [
    { key: 'd', header: 'Date', cell: (e) => date(e.expense_date) },
    { key: 'c', header: 'Expense', cell: (e) => <div><p className="font-semibold text-fg">{e.description}</p><p className="text-xs text-muted">{label(e.category)}{e.source ? ' · auto-generated' : ''}{e.vehicle ? ` · ${e.vehicle.registration_number}` : ''}</p></div> },
    { key: 'b', header: 'Submitted by', cell: (e) => e.created_by ?? '—', hideOnMobile: true },
    { key: 's', header: 'Status', cell: (e) => <div><StatusBadge status={e.status} />{e.rejection_reason && <p className="mt-1 text-xs text-danger">{e.rejection_reason}</p>}</div> },
    { key: 'r', header: '', cell: (e) => e.receipt_url ? <a href={fileUrl(e.receipt_url)!} target="_blank" rel="noreferrer" className="text-xs font-semibold text-primary">Receipt</a> : null, hideOnMobile: true },
    { key: 'a', header: 'Amount', cell: (e) => <span className="font-semibold">{money(e.amount)}</span>, className: 'text-right' },
    { key: 'x', header: '', className: 'text-right', cell: (e) => can('expenses.approve') && e.status === 'pending' && (e.created_by_id !== user?.id || e.source) ? (
      <div className="flex justify-end gap-1">
        <Button size="icon-sm" variant="outline" className="text-success" onClick={() => approve(e)} aria-label="Approve"><Check /></Button>
        <Button size="icon-sm" variant="outline" className="text-danger" onClick={() => { setRejecting(e); setReason('') }} aria-label="Reject"><X /></Button>
      </div>
    ) : null },
  ]

  return (
    <>
      <PageHeader eyebrow="Finance" title="Expenses" description="Operational costs with an approval workflow. Only approved expenses count toward margins."
        actions={<>
          {can('expenses.view') && (
            <DropdownMenu><DropdownTrigger asChild><Button variant="outline"><Download /> Export</Button></DropdownTrigger>
              <DropdownContent align="end">{['csv', 'xlsx'].map((fmt) => <DropdownItem key={fmt} onSelect={() => api.download('/expenses/export', { ...filters, page: undefined, format: fmt })}>{fmt.toUpperCase()}</DropdownItem>)}</DropdownContent>
            </DropdownMenu>
          )}
          {(can('expenses.manage') || can('expenses.create_own')) && <Button onClick={() => setOpen(true)}><Plus /> Submit expense</Button>}
        </>} />
      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <StatCard label="Approved" value={money(totals?.approved?.amount ?? 0)} tone="success" icon={<Check />} hint={`${totals?.approved?.count ?? 0} expenses`} loading={!meta} />
        <StatCard label="Pending approval" value={money(totals?.pending?.amount ?? 0)} tone="warning" icon={<Receipt />} hint={`${totals?.pending?.count ?? 0} expenses`} loading={!meta} />
        <StatCard label="Rejected" value={money(totals?.rejected?.amount ?? 0)} tone="danger" icon={<X />} hint={`${totals?.rejected?.count ?? 0} expenses`} loading={!meta} />
      </div>
      <Card>
        <Toolbar>
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['pending', 'approved', 'rejected'].map((s) => ({ value: s, label: label(s) }))} />
          <FilterSelect placeholder="All categories" value={String(filters.category ?? '')} onChange={(v) => set({ category: v })} options={EXPENSE_CATEGORIES.map((c) => ({ value: c, label: label(c) }))} />
          {vehicles.length > 0 && <FilterSelect placeholder="All vehicles" value={String(filters.vehicle_id ?? '')} onChange={(v) => set({ vehicle_id: v })} options={vehicles.map((v) => ({ value: String(v.id), label: v.registration_number }))} />}
          <Input type="date" className="h-9 sm:w-40" value={String(filters.from ?? '')} onChange={(e) => set({ from: e.target.value })} aria-label="From" />
          <Input type="date" className="h-9 sm:w-40" value={String(filters.to ?? '')} onChange={(e) => set({ to: e.target.value })} aria-label="To" />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} />
      </Card>
      {open && <ExpenseForm open={open} onOpenChange={setOpen} onSaved={() => void reload()} />}
      <FormModal open={Boolean(rejecting)} onOpenChange={(o) => !o && setRejecting(null)} title="Reject expense" size="sm" danger submitLabel="Reject" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.patch(`/expenses/${rejecting!.id}/reject`, { reason }); toast.success('Rejected'); setRejecting(null); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label="Reason" required><Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      </FormModal>
    </>
  )
}
