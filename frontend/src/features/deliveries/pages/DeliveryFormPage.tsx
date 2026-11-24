import { ArrowLeft, KeyRound, Plus, Save, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Alert, Button, Card, CardBody, CardHeader, Field, Input, Modal, PageHeader, Select, Skeleton, Switch, Textarea } from '@/components/ui'
import { AddressPicker, type AddressValue } from '@/features/customers/components/AddressPicker'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { currency, fromLocalInput, toLocalInput } from '@/lib/format'
import { toApiError } from '@/lib/http'
import type { Customer, Delivery } from '@/types/domain'

interface Item { description: string; quantity: string; weight: string; dimensions: string }
const emptyItem = (): Item => ({ description: '', quantity: '1', weight: '', dimensions: '' })

export default function DeliveryFormPage() {
  const { id } = useParams()
  const editing = Boolean(id)
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const existing = useAsync(() => (id ? api.get<Delivery>(`/deliveries/${id}`) : Promise.resolve(null)), [id])
  const customers = useAsync(() => api.page<Customer>('/customers', { per_page: 100 }).then((r) => r.data), [])
  const settings = useAsync(() => api.get<{ require_proof_of_delivery: boolean; require_delivery_otp: boolean }>('/companies/current/settings'), [])

  const [customerId, setCustomerId] = useState(params.get('customer_id') ?? '')
  const [pickup, setPickup] = useState<AddressValue>({ address: '', latitude: null, longitude: null })
  const [dropoff, setDropoff] = useState<AddressValue & { city: string }>({ address: '', latitude: null, longitude: null, city: '' })
  const [f, setF] = useState({ pickup_contact_name: '', pickup_contact_phone: '', recipient_name: '', recipient_phone: '', recipient_email: '', description: '', scheduled_pickup_at: '', scheduled_delivery_at: '', priority: 'normal', delivery_fee: '', cod_amount: '', delivery_notes: '' })
  const [items, setItems] = useState<Item[]>([emptyItem()])
  const [requiresProof, setRequiresProof] = useState(true)
  const [requireOtp, setRequireOtp] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const [otp, setOtp] = useState<{ code: string; id: number } | null>(null)

  useEffect(() => { if (settings.data && !editing) { setRequiresProof(settings.data.require_proof_of_delivery); setRequireOtp(settings.data.require_delivery_otp) } }, [settings.data, editing])
  useEffect(() => {
    const d = existing.data
    if (!d) return
    setCustomerId(d.customer_id ? String(d.customer_id) : '')
    setPickup({ address: d.pickup.address, latitude: d.pickup.latitude, longitude: d.pickup.longitude })
    setDropoff({ address: d.dropoff.address, latitude: d.dropoff.latitude, longitude: d.dropoff.longitude, city: d.dropoff.city ?? '' })
    setF({ pickup_contact_name: d.pickup.contact_name ?? '', pickup_contact_phone: d.pickup.contact_phone ?? '', recipient_name: d.recipient.name, recipient_phone: d.recipient.phone ?? '', recipient_email: d.recipient.email ?? '',
      description: d.description ?? '', scheduled_pickup_at: toLocalInput(d.scheduled_pickup_at), scheduled_delivery_at: toLocalInput(d.scheduled_delivery_at), priority: d.priority,
      delivery_fee: d.delivery_fee, cod_amount: d.cod_amount, delivery_notes: d.delivery_notes ?? '' })
    setItems(d.items?.length ? d.items.map((i) => ({ description: i.description, quantity: String(i.quantity), weight: i.weight, dimensions: i.dimensions ?? '' })) : [emptyItem()])
    setRequiresProof(d.requires_proof)
  }, [existing.data])

  // Selecting a customer pre-fills their default delivery address & contact.
  const pickCustomer = (cid: string) => {
    setCustomerId(cid)
    const c = customers.data?.find((x) => String(x.id) === cid)
    if (!c || editing) return
    const a = c.addresses?.find((x) => x.is_default && x.address_type !== 'pickup') ?? c.addresses?.[0]
    if (a) setDropoff({ address: a.address, latitude: a.latitude, longitude: a.longitude, city: a.city })
    setF((p) => ({ ...p, recipient_name: p.recipient_name || a?.contact_name || c.name, recipient_phone: p.recipient_phone || a?.contact_phone || c.phone || '', recipient_email: p.recipient_email || c.email || '' }))
  }

  const totalWeight = items.reduce((s, i) => s + (Number(i.weight) || 0) * (Number(i.quantity) || 0), 0)
  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  const err = (k: string) => errors[k]?.[0]

  const submit = async (draft = false) => {
    setBusy(true); setErrors({})
    const body = {
      ...f, customer_id: customerId ? Number(customerId) : null,
      pickup_address: pickup.address, pickup_latitude: pickup.latitude, pickup_longitude: pickup.longitude,
      delivery_address: dropoff.address, delivery_latitude: dropoff.latitude, delivery_longitude: dropoff.longitude, delivery_city: dropoff.city || null,
      scheduled_pickup_at: fromLocalInput(f.scheduled_pickup_at), scheduled_delivery_at: fromLocalInput(f.scheduled_delivery_at),
      delivery_fee: f.delivery_fee || 0, cod_amount: f.cod_amount || 0, requires_proof: requiresProof,
      items: items.filter((i) => i.description.trim()).map((i) => ({ description: i.description, quantity: Number(i.quantity) || 1, weight: Number(i.weight) || 0, dimensions: i.dimensions || null })),
      ...(editing ? {} : { require_otp: requireOtp, as_draft: draft }),
    }
    try {
      if (editing) {
        await api.put(`/deliveries/${id}`, body)
        toast.success('Delivery updated')
        navigate(`/app/deliveries/${id}`)
      } else {
        const r = await api.post<{ delivery: Delivery; otp: string | null }>('/deliveries', body)
        toast.success(`Delivery ${r.delivery.reference_number} created`)
        if (r.otp) setOtp({ code: r.otp, id: r.delivery.id })
        else navigate(`/app/deliveries/${r.delivery.id}`)
      }
    } catch (e) {
      const x = toApiError(e)
      setErrors(x.errors)
      toast.error(x.kind === 'validation' ? 'Please check the highlighted fields.' : x.message)
    } finally { setBusy(false) }
  }

  if (editing && existing.loading) return <Skeleton className="h-[600px]" />
  if (editing && existing.error) return <Alert tone="danger">{existing.error.message}</Alert>

  return (
    <div className="space-y-5">
      <Link to={editing ? `/app/deliveries/${id}` : '/app/deliveries'} className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Back</Link>
      <PageHeader eyebrow="Operations" title={editing ? `Edit ${existing.data?.reference_number}` : 'New delivery'} description="Addresses can be typed, searched or pinned on the map." />

      <div className="grid gap-4 xl:grid-cols-[1fr_360px]">
        <div className="space-y-5">
          <Card><CardHeader title="Route" /><CardBody className="space-y-5">
            <Field label="Customer"><Select value={customerId} onChange={(e) => pickCustomer(e.target.value)}><option value="">No customer (one-off)</option>{customers.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
            <AddressPicker label="Pickup address" required value={pickup} onChange={setPickup} error={err('pickup_address')} />
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Pickup contact"><Input value={f.pickup_contact_name} onChange={set('pickup_contact_name')} /></Field>
              <Field label="Pickup phone"><Input value={f.pickup_contact_phone} onChange={set('pickup_contact_phone')} /></Field>
            </div>
            <AddressPicker label="Delivery address" required value={dropoff} onChange={(v) => setDropoff({ ...dropoff, ...v, city: v.city ?? dropoff.city })} error={err('delivery_address')} />
            <Field label="City" error={err('delivery_city')}><Input value={dropoff.city} onChange={(e) => setDropoff({ ...dropoff, city: e.target.value })} /></Field>
          </CardBody></Card>

          <Card><CardHeader title="Recipient" /><CardBody className="grid gap-4 sm:grid-cols-3">
            <Field label="Name" required error={err('recipient_name')}><Input value={f.recipient_name} onChange={set('recipient_name')} /></Field>
            <Field label="Phone" error={err('recipient_phone')}><Input type="tel" value={f.recipient_phone} onChange={set('recipient_phone')} /></Field>
            <Field label="Email" error={err('recipient_email')} hint="Receives tracking updates"><Input type="email" value={f.recipient_email} onChange={set('recipient_email')} /></Field>
          </CardBody></Card>

          <Card><CardHeader title="Items" description={`Total weight ${totalWeight.toFixed(1)} kg — used to check vehicle capacity`}
            action={<Button size="sm" variant="outline" onClick={() => setItems([...items, emptyItem()])}><Plus /> Add item</Button>} />
            <CardBody className="space-y-3">
              {items.map((it, i) => (
                <div key={i} className="grid grid-cols-[1fr_auto] gap-2 sm:grid-cols-[1fr_90px_110px_130px_auto]">
                  <Input placeholder="Description" value={it.description} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, description: e.target.value } : x))} aria-invalid={Boolean(errors[`items.${i}.description`]) || undefined} />
                  <Input type="number" min={1} placeholder="Qty" value={it.quantity} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, quantity: e.target.value } : x))} className="max-sm:hidden" />
                  <Input type="number" min={0} step="0.1" placeholder="kg / unit" value={it.weight} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, weight: e.target.value } : x))} className="max-sm:hidden" />
                  <Input placeholder="L×W×H" value={it.dimensions} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, dimensions: e.target.value } : x))} className="max-sm:hidden" />
                  <Button variant="ghost" size="icon" aria-label="Remove item" disabled={items.length === 1} onClick={() => setItems(items.filter((_, j) => j !== i))}><Trash2 /></Button>
                </div>
              ))}
              <Field label="Description"><Input value={f.description} onChange={set('description')} placeholder="What is being delivered" /></Field>
            </CardBody>
          </Card>
        </div>

        <div className="space-y-5">
          <Card><CardHeader title="Schedule & priority" /><CardBody className="space-y-4">
            <Field label="Pickup at" error={err('scheduled_pickup_at')}><Input type="datetime-local" value={f.scheduled_pickup_at} onChange={set('scheduled_pickup_at')} /></Field>
            <Field label="Deliver by" error={err('scheduled_delivery_at')} hint="Used to measure on-time performance"><Input type="datetime-local" value={f.scheduled_delivery_at} onChange={set('scheduled_delivery_at')} /></Field>
            <Field label="Priority"><Select value={f.priority} onChange={set('priority')}>{['low', 'normal', 'high', 'urgent'].map((p) => <option key={p} value={p}>{p[0].toUpperCase() + p.slice(1)}</option>)}</Select></Field>
          </CardBody></Card>
          <Card><CardHeader title="Charges" description={`Amounts in ${currency()}`} /><CardBody className="space-y-4">
            <Field label="Delivery fee" error={err('delivery_fee')}><Input type="number" min={0} step="0.01" value={f.delivery_fee} onChange={set('delivery_fee')} /></Field>
            <Field label="Cash on delivery" error={err('cod_amount')} hint="Driver confirms the amount collected"><Input type="number" min={0} step="0.01" value={f.cod_amount} onChange={set('cod_amount')} /></Field>
          </CardBody></Card>
          <Card><CardHeader title="Proof of delivery" /><CardBody className="divide-y divide-border py-1">
            <Switch checked={requiresProof} onChange={setRequiresProof} label="Require photo or signature" />
            {!editing && <Switch checked={requireOtp} onChange={setRequireOtp} label="Require delivery code (OTP)" description="A 6-digit code for the recipient is generated once" />}
          </CardBody></Card>
          <Card><CardBody><Field label="Special instructions"><Textarea rows={3} value={f.delivery_notes} onChange={set('delivery_notes')} /></Field></CardBody></Card>
          <div className="flex flex-col gap-2">
            <Button size="lg" onClick={() => submit(false)} loading={busy}><Save /> {editing ? 'Save changes' : 'Create delivery'}</Button>
            {!editing && <Button variant="outline" onClick={() => submit(true)} disabled={busy}>Save as draft</Button>}
          </div>
        </div>
      </div>

      <Modal open={Boolean(otp)} onOpenChange={() => otp && navigate(`/app/deliveries/${otp.id}`)} title="Delivery code" size="sm"
        description="Share this code with the recipient. It is shown only once and is needed to complete the delivery."
        footer={<Button onClick={() => navigate(`/app/deliveries/${otp!.id}`)}>I've shared it</Button>}>
        <div className="flex items-center justify-center gap-3 rounded-xl bg-surface-2 py-6 text-4xl font-semibold tracking-[0.3em]"><KeyRound className="size-6 text-primary" />{otp?.code}</div>
      </Modal>
    </div>
  )
}
