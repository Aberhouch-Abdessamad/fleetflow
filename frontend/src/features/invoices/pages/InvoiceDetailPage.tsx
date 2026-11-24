import { ArrowLeft, Ban, CreditCard, Printer, Send } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Logo } from '@/components/brand/Logo'
import { Alert, Badge, Button, Card, Field, FormModal, Input, Select, Skeleton, StatusBadge, Textarea } from '@/components/ui'
import { useResource } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { date, label, money, num } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Invoice } from '@/types/domain'

export default function InvoiceDetailPage() {
  const { id } = useParams()
  const { can, user } = useAuth()
  const { data: inv, loading, error, reload, setData } = useResource<Invoice>(`/invoices/${id}`)
  const [pay, setPay] = useState(false)
  const [voiding, setVoiding] = useState(false)
  const [p, setP] = useState({ amount: '', payment_method: 'bank_transfer', reference: '' })
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  if (loading && !inv) return <Skeleton className="h-[600px]" />
  if (error || !inv) return <Alert tone="danger">{error?.message}</Alert>
  const company = user?.current_company
  const m = (v: string) => money(v, inv.currency)

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <Link to="/app/invoices" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Invoices</Link>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={() => window.print()}><Printer /> Print / PDF</Button>
          {can('invoices.manage') && inv.status === 'draft' && <Button onClick={async () => { try { setData(await api.post<Invoice>(`/invoices/${inv.id}/issue`)); toast.success('Invoice issued') } catch (e) { toast.error(toApiError(e).message) } }}><Send /> Issue</Button>}
          {can('payments.manage') && ['issued', 'partially_paid'].includes(inv.status) && <Button onClick={() => { setP({ ...p, amount: inv.balance }); setPay(true) }}><CreditCard /> Record payment</Button>}
          {can('invoices.manage') && !['void', 'paid'].includes(inv.status) && Number(inv.amount_paid) === 0 && <Button variant="ghost" className="text-danger" onClick={() => setVoiding(true)}><Ban /> Void</Button>}
        </div>
      </div>

      <Card className="mx-auto max-w-4xl p-6 sm:p-10 print:border-0 print:shadow-none">
        <div className="flex flex-col justify-between gap-4 sm:flex-row">
          <div><Logo /><div className="mt-4 text-sm text-muted"><p className="font-semibold text-fg">{company?.name}</p><p>{company?.address}</p><p>{company?.city}</p><p>{company?.contact_email}</p>{company?.registration_number && <p>Reg. {company.registration_number}</p>}</div></div>
          <div className="sm:text-right">
            <p className="font-mono text-xs text-subtle">Invoice</p>
            <p className="text-2xl font-semibold">{inv.invoice_number}</p>
            <div className="mt-2 flex gap-2 sm:justify-end"><StatusBadge status={inv.status} />{inv.overdue && <Badge tone="danger">Overdue</Badge>}</div>
            <p className="mt-3 text-sm text-muted">Issued {date(inv.issued_at)} · Due {date(inv.due_date)}</p>
          </div>
        </div>
        <div className="mt-8 rounded-xl bg-surface-2 p-4 text-sm">
          <p className="text-xs font-medium text-subtle">Bill to</p>
          <p className="mt-1 font-semibold">{inv.customer?.name}</p>
          {inv.customer?.email && <p className="text-muted">{inv.customer.email}</p>}
          {inv.customer?.tax_number && <p className="text-muted">Tax ID {inv.customer.tax_number}</p>}
        </div>
        <table className="mt-8 w-full text-sm">
          <thead><tr className="border-b border-border text-left text-xs font-medium text-muted"><th className="py-2">Description</th><th className="py-2 text-right">Qty</th><th className="py-2 text-right">Unit</th><th className="py-2 text-right">Amount</th></tr></thead>
          <tbody>
            {inv.items?.map((it) => (
              <tr key={it.id} className="border-b border-border">
                <td className="py-2.5 pr-4">{it.delivery_id ? <Link to={`/app/deliveries/${it.delivery_id}`} className="hover:text-primary print:no-underline">{it.description}</Link> : it.description}</td>
                <td className="py-2.5 text-right">{num(it.quantity, 2)}</td><td className="py-2.5 text-right">{m(it.unit_price)}</td><td className="py-2.5 text-right font-medium">{m(it.total_price)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="ml-auto mt-6 w-full max-w-xs space-y-1.5 text-sm">
          <div className="flex justify-between"><span className="text-muted">Subtotal</span><span>{m(inv.subtotal)}</span></div>
          <div className="flex justify-between"><span className="text-muted">Tax ({num(inv.tax_rate, 2)}%)</span><span>{m(inv.tax_amount)}</span></div>
          <div className="flex justify-between border-t border-border pt-2 text-base font-semibold"><span>Total</span><span>{m(inv.total_amount)}</span></div>
          <div className="flex justify-between text-success"><span>Paid</span><span>{m(inv.amount_paid)}</span></div>
          <div className="flex justify-between rounded-lg bg-primary-soft px-2 py-1.5 font-semibold text-primary"><span>Balance due</span><span>{m(inv.balance)}</span></div>
        </div>
        {(inv.payments?.length ?? 0) > 0 && (
          <div className="mt-8"><p className="text-xs font-medium text-subtle">Payments</p>
            <ul className="mt-2 divide-y divide-border text-sm">{inv.payments!.map((x) => <li key={x.id} className="flex justify-between py-2"><span>{date(x.paid_at)} · {label(x.payment_method)}{x.reference ? ` · ${x.reference}` : ''}</span><span className="font-medium">{m(x.amount)}</span></li>)}</ul>
          </div>
        )}
        {inv.notes && <p className="mt-8 whitespace-pre-line text-sm text-muted">{inv.notes}</p>}
      </Card>

      <FormModal open={pay} onOpenChange={setPay} title="Record payment" size="sm" submitting={busy} submitLabel="Record" onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { setData(await api.post<Invoice>(`/invoices/${inv.id}/payments`, p)); toast.success('Payment recorded'); setPay(false) } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label={`Amount (balance ${m(inv.balance)})`} required><Input type="number" step="0.01" value={p.amount} onChange={(e) => setP({ ...p, amount: e.target.value })} /></Field>
        <Field label="Method"><Select value={p.payment_method} onChange={(e) => setP({ ...p, payment_method: e.target.value })}>{['bank_transfer', 'cash', 'card', 'cod', 'cheque', 'other'].map((x) => <option key={x} value={x}>{label(x)}</option>)}</Select></Field>
        <Field label="Reference"><Input value={p.reference} onChange={(e) => setP({ ...p, reference: e.target.value })} /></Field>
      </FormModal>
      <FormModal open={voiding} onOpenChange={setVoiding} title="Void invoice" size="sm" danger submitLabel="Void" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.post(`/invoices/${inv.id}/void`, { reason }); toast.success('Invoice voided'); setVoiding(false); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <p className="text-sm text-muted">Linked deliveries become billable again.</p>
        <Field label="Reason" required><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      </FormModal>
    </div>
  )
}
