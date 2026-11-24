import { ArrowLeft, MapPin, Pencil, Plus, Star, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, Field, FormModal, Input, KeyValue, Select, Skeleton, StatusBadge } from '@/components/ui'
import { usePaged } from '@/hooks/usePaged'
import { useResource } from '@/hooks/useResource'
import { api } from '@/lib/api'
import { date, money } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Customer, Delivery } from '@/types/domain'
import { AddressPicker, type AddressValue } from '../components/AddressPicker'
import { CustomerForm } from './CustomersPage'

export default function CustomerDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const can = useAuth((s) => s.can)
  const { data: c, loading, error, reload } = useResource<Customer>(`/customers/${id}`)
  const deliveries = usePaged<Delivery>(`/customers/${id}/deliveries`)
  const [edit, setEdit] = useState(false)
  const [addOpen, setAddOpen] = useState(false)
  const [addr, setAddr] = useState<AddressValue & { city: string; label: string; address_type: string; contact_name: string; contact_phone: string }>({ address: '', latitude: null, longitude: null, city: '', label: '', address_type: 'delivery', contact_name: '', contact_phone: '' })
  const [busy, setBusy] = useState(false)

  if (loading && !c) return <Skeleton className="h-96" />
  if (error || !c) return <Alert tone="danger" title="Customer not found">{error?.message}</Alert>

  return (
    <div className="space-y-5">
      <Link to="/app/customers" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Customers</Link>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div className="flex items-center gap-2.5"><h1 className="text-xl font-semibold">{c.name}</h1><Badge>{c.customer_type}</Badge></div>
          <p className="mt-1 text-sm text-muted">{[c.email, c.phone, c.tax_number && `Tax ${c.tax_number}`].filter(Boolean).join(' · ') || 'No contact details'}</p>
        </div>
        <div className="flex gap-2">
          {can('deliveries.create') && <Button onClick={() => navigate(`/app/deliveries/new?customer_id=${c.id}`)}><Plus /> New delivery</Button>}
          {can('customers.manage') && <Button variant="outline" onClick={() => setEdit(true)}><Pencil /> Edit</Button>}
        </div>
      </div>
      <div className="grid gap-4 lg:grid-cols-[1fr_2fr]">
        <Card>
          <CardHeader title="Addresses" action={can('customers.manage') && <Button size="sm" variant="outline" onClick={() => setAddOpen(true)}><Plus /> Add</Button>} />
          <CardBody className="space-y-3">
            {(c.addresses ?? []).length === 0 && <p className="text-sm text-muted">No saved addresses.</p>}
            {c.addresses?.map((a) => (
              <div key={a.id} className="flex items-start gap-3 rounded-xl border border-border p-3">
                <MapPin className={`mt-0.5 size-4 ${a.latitude ? 'text-accent' : 'text-subtle'}`} />
                <div className="min-w-0 flex-1 text-sm">
                  <p className="font-semibold">{a.label || a.address_type} {a.is_default && <Star className="inline size-3.5 fill-warning text-warning" />}</p>
                  <p className="text-fg-soft">{a.address}, {a.city}</p>
                  {a.contact_name && <p className="text-xs text-muted">{a.contact_name} {a.contact_phone}</p>}
                </div>
                {can('customers.manage') && <ConfirmDialog title="Remove address?" confirmLabel="Remove" onConfirm={async () => { await api.delete(`/customers/${c.id}/addresses/${a.id}`); void reload() }} trigger={<Button size="icon-sm" variant="ghost" aria-label="Remove"><Trash2 /></Button>} />}
              </div>
            ))}
            {c.notes && <KeyValue items={[['Notes', c.notes]]} />}
          </CardBody>
        </Card>
        <Card className="overflow-hidden">
          <CardHeader title="Delivery history" description={`${deliveries.meta?.pagination.total ?? 0} deliveries`} />
          <DataTable loading={deliveries.loading} rows={deliveries.rows} meta={deliveries.meta} onPage={(p) => deliveries.set({ page: p })} onRowClick={(d) => navigate(`/app/deliveries/${d.id}`)} columns={[
            { key: 'r', header: 'Reference', cell: (d) => <span className="font-semibold">{d.reference_number}</span> },
            { key: 's', header: 'Status', cell: (d) => <StatusBadge status={d.status} /> },
            { key: 'a', header: 'Destination', cell: (d) => <span className="line-clamp-1">{d.dropoff.address}</span>, hideOnMobile: true },
            { key: 'f', header: 'Fee', cell: (d) => money(d.delivery_fee), hideOnMobile: true },
            { key: 'c', header: 'Date', cell: (d) => date(d.created_at) },
          ]} />
        </Card>
      </div>
      {edit && <CustomerForm open={edit} onOpenChange={setEdit} customer={c} onSaved={() => void reload()} />}
      <FormModal open={addOpen} onOpenChange={setAddOpen} title="Add address" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.post(`/customers/${c.id}/addresses`, addr); toast.success('Address saved'); setAddOpen(false); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Label"><Input value={addr.label} onChange={(e) => setAddr({ ...addr, label: e.target.value })} placeholder="Main store" /></Field>
          <Field label="Type"><Select value={addr.address_type} onChange={(e) => setAddr({ ...addr, address_type: e.target.value })}><option value="delivery">Delivery</option><option value="pickup">Pickup</option><option value="billing">Billing</option></Select></Field>
        </div>
        <AddressPicker label="Address" required value={addr} onChange={(v) => setAddr({ ...addr, ...v, city: v.city ?? addr.city })} />
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label="City" required><Input value={addr.city} onChange={(e) => setAddr({ ...addr, city: e.target.value })} /></Field>
          <Field label="Contact"><Input value={addr.contact_name} onChange={(e) => setAddr({ ...addr, contact_name: e.target.value })} /></Field>
          <Field label="Contact phone"><Input value={addr.contact_phone} onChange={(e) => setAddr({ ...addr, contact_phone: e.target.value })} /></Field>
        </div>
      </FormModal>
    </div>
  )
}
