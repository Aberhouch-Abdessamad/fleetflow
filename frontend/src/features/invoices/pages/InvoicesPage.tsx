import { FileText, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Badge, Button, Card, DataTable, EmptyState, Field, FilterSelect, FormModal, Input, PageHeader, SearchInput, Select, StatusBadge, Toolbar, type Column } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { usePaged } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { date, money } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Customer, Delivery, Invoice } from '@/types/domain'

function NewInvoiceDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const navigate = useNavigate()
  const customers = useOptions<Customer>('/customers')
  const settings = useAsync(() => api.get<{ default_tax_rate: number }>('/companies/current/settings'), [])
  const [customerId, setCustomerId] = useState('')
  const billable = useAsync(() => customerId ? api.get<Delivery[]>('/invoices/billable', { customer_id: customerId }) : Promise.resolve([]), [customerId])
  const [picked, setPicked] = useState<number[]>([])
  const [lines, setLines] = useState<{ description: string; quantity: string; unit_price: string }[]>([])
  const [tax, setTax] = useState<string>('')
  const [due, setDue] = useState('')
  const [busy, setBusy] = useState(false)
  const taxRate = tax === '' ? Number(settings.data?.default_tax_rate ?? 0) : Number(tax)
  const subtotal = (billable.data ?? []).filter((d) => picked.includes(d.id)).reduce((s, d) => s + Number(d.delivery_fee), 0) + lines.reduce((s, l) => s + (Number(l.quantity) || 0) * (Number(l.unit_price) || 0), 0)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title="New invoice" submitLabel="Create draft" submitting={busy} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true)
      try {
        const inv = await api.post<Invoice>('/invoices', { customer_id: Number(customerId), delivery_ids: picked, lines: lines.filter((l) => l.description), tax_rate: taxRate, due_date: due || null })
        toast.success(`Invoice ${inv.invoice_number} created`); navigate(`/app/invoices/${inv.id}`)
      } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
    }}>
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Customer" required className="sm:col-span-3"><Select value={customerId} onChange={(e) => { setCustomerId(e.target.value); setPicked([]) }}><option value="">Select…</option>{customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
        <Field label="Tax rate (%)"><Input type="number" step="0.01" placeholder={String(settings.data?.default_tax_rate ?? 0)} value={tax} onChange={(e) => setTax(e.target.value)} /></Field>
        <Field label="Due date" hint="Defaults to company terms"><Input type="date" value={due} onChange={(e) => setDue(e.target.value)} /></Field>
      </div>
      {customerId && (
        <Field label={`Delivered, not yet invoiced (${billable.data?.length ?? 0})`}>
          <div className="max-h-52 space-y-1 overflow-y-auto rounded-lg border border-border p-1.5">
            {(billable.data ?? []).length === 0 && <p className="p-3 text-center text-xs text-muted">Nothing to bill for this customer.</p>}
            {(billable.data ?? []).length > 0 && <button type="button" className="px-2 text-xs font-semibold text-primary" onClick={() => setPicked(picked.length === billable.data!.length ? [] : billable.data!.map((d) => d.id))}>{picked.length === billable.data!.length ? 'Clear' : 'Select all'}</button>}
            {billable.data?.map((d) => (
              <label key={d.id} className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-surface-2">
                <input type="checkbox" className="size-4 accent-[var(--primary)]" checked={picked.includes(d.id)} onChange={(e) => setPicked(e.target.checked ? [...picked, d.id] : picked.filter((x) => x !== d.id))} />
                <span className="font-semibold">{d.reference_number}</span><span className="min-w-0 flex-1 truncate text-muted">{date(d.actual_delivery_at)}</span><span>{money(d.delivery_fee)}</span>
              </label>
            ))}
          </div>
        </Field>
      )}
      <div className="space-y-2">
        <div className="flex items-center justify-between"><p className="text-[0.8125rem] font-semibold text-fg-soft">Additional lines</p><Button type="button" size="sm" variant="ghost" onClick={() => setLines([...lines, { description: '', quantity: '1', unit_price: '' }])}><Plus /> Line</Button></div>
        {lines.map((l, i) => (
          <div key={i} className="grid grid-cols-[1fr_70px_100px_auto] gap-2">
            <Input placeholder="Description" value={l.description} onChange={(e) => setLines(lines.map((x, j) => j === i ? { ...x, description: e.target.value } : x))} />
            <Input type="number" value={l.quantity} onChange={(e) => setLines(lines.map((x, j) => j === i ? { ...x, quantity: e.target.value } : x))} />
            <Input type="number" step="0.01" placeholder="Price" value={l.unit_price} onChange={(e) => setLines(lines.map((x, j) => j === i ? { ...x, unit_price: e.target.value } : x))} />
            <Button type="button" size="icon" variant="ghost" onClick={() => setLines(lines.filter((_, j) => j !== i))} aria-label="Remove"><Trash2 /></Button>
          </div>
        ))}
      </div>
      <div className="rounded-xl bg-surface-2 p-3 text-sm">
        <div className="flex justify-between"><span className="text-muted">Subtotal (preview)</span><span>{money(subtotal)}</span></div>
        <div className="flex justify-between"><span className="text-muted">Tax {taxRate}%</span><span>{money(subtotal * taxRate / 100)}</span></div>
        <div className="mt-1 flex justify-between border-t border-border pt-1 font-semibold"><span>Total</span><span>{money(subtotal * (1 + taxRate / 100))}</span></div>
        <p className="mt-1 text-xs text-subtle">Final totals are calculated by the server.</p>
      </div>
    </FormModal>
  )
}

export default function InvoicesPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const { rows, meta, filters, set, loading } = usePaged<Invoice>('/invoices', { search: '', status: '' })
  const columns: Column<Invoice>[] = [
    { key: 'n', header: 'Invoice', cell: (i) => <div><p className="font-semibold text-fg">{i.invoice_number}</p><p className="text-xs text-muted">{i.customer?.name}</p></div> },
    { key: 's', header: 'Status', cell: (i) => <span className="flex gap-1.5"><StatusBadge status={i.status} />{i.overdue && <Badge tone="danger">Overdue</Badge>}</span> },
    { key: 'd', header: 'Due', cell: (i) => date(i.due_date), hideOnMobile: true },
    { key: 't', header: 'Total', cell: (i) => money(i.total_amount, i.currency), className: 'text-right' },
    { key: 'b', header: 'Balance', cell: (i) => <span className={Number(i.balance) > 0 && i.status !== 'void' ? 'font-semibold' : 'text-muted'}>{money(i.balance, i.currency)}</span>, className: 'text-right' },
  ]
  return (
    <>
      <PageHeader eyebrow="Finance" title="Invoices" description="Bill customers for completed deliveries and track payments."
        actions={can('invoices.manage') && <Button onClick={() => setOpen(true)}><Plus /> New invoice</Button>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-64" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Invoice number" />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['draft', 'issued', 'partially_paid', 'paid', 'void'].map((s) => ({ value: s, label: s.replace('_', ' ') }))} />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(i) => navigate(`/app/invoices/${i.id}`)}
          empty={<EmptyState icon={<FileText />} title="No invoices" description="Create an invoice from a customer's delivered orders." />} />
      </Card>
      {open && <NewInvoiceDialog open={open} onOpenChange={setOpen} />}
    </>
  )
}
