import { Building2, Plus, User, Users } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Badge, Button, Card, DataTable, EmptyState, Field, FilterSelect, FormModal, Input, PageHeader, SearchInput, Select, Textarea, Toolbar, type Column } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Customer } from '@/types/domain'
import { AddressPicker, type AddressValue } from '../components/AddressPicker'

export function CustomerForm({ open, onOpenChange, customer, onSaved }: { open: boolean; onOpenChange: (o: boolean) => void; customer?: Customer | null; onSaved: (c: Customer) => void }) {
  const [f, setF] = useState({ name: customer?.name ?? '', email: customer?.email ?? '', phone: customer?.phone ?? '', customer_type: customer?.customer_type ?? 'business', tax_number: customer?.tax_number ?? '', notes: customer?.notes ?? '' })
  const [addr, setAddr] = useState<AddressValue & { city: string }>({ address: '', latitude: null, longitude: null, city: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title={customer ? 'Edit customer' : 'New customer'} submitting={busy} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true); setErrors({})
      try {
        const body = { ...f, ...(customer || !addr.address ? {} : { addresses: [{ ...addr, city: addr.city || '—', address_type: 'delivery', is_default: true }] }) }
        const c = customer ? await api.put<Customer>(`/customers/${customer.id}`, body) : await api.post<Customer>('/customers', body)
        toast.success('Customer saved'); onSaved(c); onOpenChange(false)
      } catch (err) { const x = toApiError(err); setErrors(x.errors); if (x.kind !== 'validation') toast.error(x.message) } finally { setBusy(false) }
    }}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Name" required error={errors.name?.[0]} className="sm:col-span-2"><Input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} /></Field>
        <Field label="Type"><Select value={f.customer_type} onChange={(e) => setF({ ...f, customer_type: e.target.value })}><option value="business">Business</option><option value="individual">Individual</option></Select></Field>
        <Field label="Tax / ICE number"><Input value={f.tax_number} onChange={(e) => setF({ ...f, tax_number: e.target.value })} /></Field>
        <Field label="Email" error={errors.email?.[0]}><Input type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></Field>
        <Field label="Phone"><Input type="tel" value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} /></Field>
      </div>
      {!customer && (
        <div className="space-y-3 rounded-xl border border-border bg-surface-2 p-4">
          <p className="text-xs font-medium text-subtle">Default delivery address (optional)</p>
          <AddressPicker label="Address" value={addr} onChange={(v) => setAddr({ ...addr, ...v, city: v.city ?? addr.city })} />
          <Field label="City"><Input value={addr.city} onChange={(e) => setAddr({ ...addr, city: e.target.value })} /></Field>
        </div>
      )}
      <Field label="Notes"><Textarea rows={2} value={f.notes} onChange={(e) => setF({ ...f, notes: e.target.value })} /></Field>
    </FormModal>
  )
}

export default function CustomersPage() {
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const { rows, meta, filters, set, loading, reload } = usePaged<Customer>('/customers', { search: '', type: '' })
  const columns: Column<Customer>[] = [
    { key: 'n', header: 'Customer', cell: (c) => (
      <div className="flex items-center gap-3">
        <span className="grid size-9 place-items-center rounded-lg bg-surface-3 text-muted">{c.customer_type === 'business' ? <Building2 className="size-4" /> : <User className="size-4" />}</span>
        <div><p className="font-semibold text-fg">{c.name}</p><p className="text-xs text-muted">{c.email ?? c.phone ?? '—'}</p></div>
      </div>
    ) },
    { key: 't', header: 'Type', cell: (c) => <Badge>{c.customer_type}</Badge>, hideOnMobile: true },
    { key: 'a', header: 'Default address', cell: (c) => <span className="line-clamp-1 text-fg-soft">{c.addresses?.find((a) => a.is_default)?.address ?? '—'}</span>, hideOnMobile: true },
    { key: 'd', header: 'Deliveries', cell: (c) => c.deliveries_count ?? 0 },
  ]
  return (
    <>
      <PageHeader eyebrow="Operations" title="Customers" description="Customers, their delivery addresses and order history."
        actions={can('customers.manage') && <Button onClick={() => setOpen(true)}><Plus /> New customer</Button>} />
      <Card>
        <Toolbar>
          <SearchInput className="sm:w-72" value={String(filters.search ?? '')} onChange={(v) => set({ search: v })} placeholder="Name, email, phone…" />
          <FilterSelect placeholder="All types" value={String(filters.type ?? '')} onChange={(v) => set({ type: v })} options={[{ value: 'business', label: 'Business' }, { value: 'individual', label: 'Individual' }]} />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={(c) => navigate(`/app/customers/${c.id}`)}
          empty={<EmptyState icon={<Users />} title="No customers yet" description="Customers are created here or automatically from integrations." />} />
      </Card>
      {open && <CustomerForm open={open} onOpenChange={setOpen} onSaved={(c) => { void reload(); navigate(`/app/customers/${c.id}`) }} />}
    </>
  )
}
