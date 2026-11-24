import { AlertTriangle, ArrowLeft, Camera, CheckCircle2, KeyRound, MapPin, Navigation, PackageCheck, Phone, PenLine, Truck } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Alert, Badge, Button, Field, FormModal, Input, Select, Skeleton, StatusBadge, Textarea } from '@/components/ui'
import { useResource } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { label, money, num, time } from '@/lib/format'
import { toApiError } from '@/lib/http'
import type { Delivery, DeliveryStatus } from '@/types/domain'
import { SignaturePad } from '../components/SignaturePad'
import { useDriver } from '../DriverApp'

const NEXT: Partial<Record<DeliveryStatus, { to: DeliveryStatus; label: string; icon: typeof Truck }>> = {
  accepted: { to: 'picked_up', label: 'Confirm pickup', icon: PackageCheck },
  picked_up: { to: 'in_transit', label: 'Start delivery', icon: Truck },
  in_transit: { to: 'arrived', label: "I've arrived", icon: MapPin },
}

function here(): Promise<{ latitude: number; longitude: number } | null> {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null)
    navigator.geolocation.getCurrentPosition((p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude }), () => resolve(null), { timeout: 5000, maximumAge: 30_000 })
  })
}

/** Key-less navigation link: OpenStreetMap marker for a pinned place, search for an address. */
function navUrl(lat: number | null, lng: number | null, address: string) {
  if (lat && lng) return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}`
  return `https://www.openstreetmap.org/search?query=${encodeURIComponent(address)}`
}

export default function DriverDeliveryPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { reload: reloadDashboard } = useDriver()
  const { data: d, loading, error, reload, setData } = useResource<Delivery>(`/driver/deliveries/${id}`)
  const [busy, setBusy] = useState<string | null>(null)
  const [photo, setPhoto] = useState<File | null>(null)
  const [signature, setSignature] = useState<string | null>(null)
  const [recipient, setRecipient] = useState('')
  const [otp, setOtp] = useState('')
  const [cod, setCod] = useState('')
  const [failOpen, setFailOpen] = useState(false)
  const [issueOpen, setIssueOpen] = useState(false)
  const [reason, setReason] = useState('')
  const [issue, setIssue] = useState({ type: 'recipient_unavailable', notes: '' })

  if (loading && !d) return <div className="space-y-3"><Skeleton className="h-40" /><Skeleton className="h-60" /></div>
  if (error || !d) return <Alert tone="danger" title="Delivery unavailable">{error?.message}</Alert>

  const done = async () => { await reload(); void reloadDashboard() }
  const status = async (to: DeliveryStatus, extra: Record<string, unknown> = {}) => {
    setBusy(to)
    try {
      const pos = await here()
      // Never claim success before the server confirms.
      setData(await api.patch<Delivery>(`/deliveries/${d.id}/status`, { status: to, ...pos, ...extra }))
      toast.success(`${label(to)} confirmed`)
      void reloadDashboard()
      if (to === 'delivered' || to === 'failed') navigate('/driver')
    } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(null) }
  }
  const proof = async (type: 'photo' | 'signature' | 'otp') => {
    setBusy(type)
    try {
      const pos = await here()
      const body: Record<string, unknown> = { proof_type: type, ...pos }
      if (type === 'photo') body.photo = photo
      if (type === 'signature') { body.signature = signature; body.recipient_name = recipient }
      if (type === 'otp') body.otp = otp
      setData(await api.upload<Delivery>(`/deliveries/${d.id}/proof`, body))
      toast.success(type === 'otp' ? 'Code verified' : 'Proof saved')
      if (type === 'photo') setPhoto(null)
    } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(null) }
  }

  const next = NEXT[d.status]
  const hasPhotoOrSig = d.proofs?.some((p) => p.type === 'photo' || p.type === 'signature')
  const hasOtp = d.proofs?.some((p) => p.type === 'otp')
  const cod$ = Number(d.cod_amount) > 0
  const canComplete = d.status === 'arrived' && (!d.requires_proof || hasPhotoOrSig) && (!d.requires_otp || hasOtp) && (!cod$ || cod !== '')
  const target = ['accepted', 'assigned'].includes(d.status) ? d.pickup : d.dropoff

  return (
    <div className="space-y-4">
      <Link to="/driver" className="inline-flex items-center gap-1.5 text-sm text-muted"><ArrowLeft className="size-4" /> Today</Link>
      <div className="ff-card p-4">
        <div className="flex items-center justify-between gap-2"><h1 className="text-xl font-semibold">{d.reference_number}</h1><StatusBadge status={d.status} /></div>
        {d.priority !== 'normal' && <Badge className="mt-2" tone={d.priority === 'urgent' ? 'danger' : 'warning'}>{d.priority} priority</Badge>}
        <div className="mt-4 space-y-3 text-sm">
          <div className="flex gap-3"><span className="mt-1 size-2.5 shrink-0 rounded-full bg-accent" /><div><p className="text-xs text-muted">Pickup</p><p className="font-medium">{d.pickup.address}</p>{d.pickup.contact_phone && <a className="text-xs text-primary" href={`tel:${d.pickup.contact_phone}`}>{d.pickup.contact_name} {d.pickup.contact_phone}</a>}</div></div>
          <div className="flex gap-3"><span className="mt-1 size-2.5 shrink-0 rounded-full bg-primary" /><div><p className="text-xs text-muted">Deliver to {d.recipient.name}{d.scheduled_delivery_at ? ` · by ${time(d.scheduled_delivery_at)}` : ''}</p><p className="font-medium">{d.dropoff.address}</p></div></div>
        </div>
        <div className="mt-4 grid grid-cols-2 gap-2">
          <Button asChild variant="outline"><a href={navUrl(target.latitude, target.longitude, target.address)} target="_blank" rel="noreferrer"><Navigation /> Navigate</a></Button>
          {d.recipient.phone ? <Button asChild variant="outline"><a href={`tel:${d.recipient.phone}`}><Phone /> Call recipient</a></Button> : <Button variant="outline" disabled><Phone /> No phone</Button>}
        </div>
      </div>

      <div className="ff-card p-4 text-sm">
        <p className="mb-2 text-xs font-medium text-subtle">Load · {num(d.total_weight, 1)} kg</p>
        <ul className="space-y-1">{d.items?.map((i) => <li key={i.id} className="flex justify-between"><span>{i.description}</span><span className="text-muted">× {i.quantity}</span></li>)}</ul>
        {cod$ && <p className="mt-3 rounded-lg bg-warning-soft px-3 py-2 font-semibold text-warning">Collect cash on delivery: {money(d.cod_amount)}</p>}
        {d.delivery_notes && <p className="mt-3 rounded-lg bg-surface-2 px-3 py-2 text-fg-soft">{d.delivery_notes}</p>}
      </div>

      {next && (
        <Button size="lg" className="h-14 w-full text-base" loading={busy === next.to} onClick={() => status(next.to)}>
          <next.icon className="!size-5" /> {next.label}
        </Button>
      )}

      {d.status === 'arrived' && (
        <div className="ff-card space-y-5 p-4">
          <p className="text-xs font-medium text-subtle">Proof of delivery {d.requires_proof ? '(required)' : '(optional)'}</p>
          {(d.proofs?.length ?? 0) > 0 && (
            <div className="flex flex-wrap gap-2">{d.proofs!.map((p) => <Badge key={p.id} tone="success"><CheckCircle2 /> {label(p.type)}</Badge>)}</div>
          )}
          <div>
            <p className="mb-2 flex items-center gap-1.5 text-sm font-semibold"><Camera className="size-4" /> Photo</p>
            <Input type="file" accept="image/*" capture="environment" className="pt-1.5" onChange={(e) => setPhoto(e.target.files?.[0] ?? null)} />
            {photo && <Button className="mt-2 w-full" variant="secondary" loading={busy === 'photo'} onClick={() => proof('photo')}>Upload photo</Button>}
            {d.proofs?.filter((p) => p.photo_url).map((p) => <img key={p.id} src={fileUrl(p.photo_url)!} alt="" className="mt-2 h-28 w-full rounded-lg object-cover" />)}
          </div>
          <div>
            <p className="mb-2 flex items-center gap-1.5 text-sm font-semibold"><PenLine className="size-4" /> Signature</p>
            <SignaturePad onChange={setSignature} />
            <Field label="Recipient name" className="mt-2"><Input value={recipient} onChange={(e) => setRecipient(e.target.value)} placeholder={d.recipient.name} /></Field>
            {signature && <Button className="mt-2 w-full" variant="secondary" loading={busy === 'signature'} disabled={!recipient} onClick={() => proof('signature')}>Save signature</Button>}
          </div>
          {d.requires_otp && !hasOtp && (
            <div>
              <p className="mb-2 flex items-center gap-1.5 text-sm font-semibold"><KeyRound className="size-4" /> Delivery code</p>
              <div className="flex gap-2"><Input inputMode="numeric" maxLength={6} className="text-center font-mono text-lg tracking-[0.4em]" value={otp} onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))} placeholder="••••••" />
                <Button loading={busy === 'otp'} disabled={otp.length !== 6} onClick={() => proof('otp')}>Verify</Button></div>
            </div>
          )}
          {cod$ && <Field label={`Cash collected (expected ${money(d.cod_amount)})`} required><Input type="number" inputMode="decimal" step="0.01" value={cod} onChange={(e) => setCod(e.target.value)} /></Field>}
          <Button size="lg" className="h-14 w-full text-base" disabled={!canComplete} loading={busy === 'delivered'} onClick={() => status('delivered', cod$ ? { cod_collected_amount: cod } : {})}>
            <CheckCircle2 className="!size-5" /> Complete delivery
          </Button>
          {!canComplete && <p className="text-center text-xs text-muted">{d.requires_proof && !hasPhotoOrSig ? 'Add a photo or signature first. ' : ''}{d.requires_otp && !hasOtp ? 'Verify the delivery code. ' : ''}{cod$ && cod === '' ? 'Enter the cash collected.' : ''}</p>}
        </div>
      )}

      {['picked_up', 'in_transit', 'arrived', 'accepted'].includes(d.status) && (
        <div className="grid grid-cols-2 gap-2">
          <Button variant="outline" onClick={() => setIssueOpen(true)}><AlertTriangle /> Report issue</Button>
          {['picked_up', 'in_transit', 'arrived'].includes(d.status) && <Button variant="outline" className="text-danger" onClick={() => { setReason(''); setFailOpen(true) }}>Delivery failed</Button>}
        </div>
      )}

      <FormModal open={failOpen} onOpenChange={setFailOpen} title="Mark delivery as failed" size="sm" danger submitLabel="Confirm failure" submitting={busy === 'failed'}
        onSubmit={(e) => { e.preventDefault(); void status('failed', { notes: reason }) }}>
        <Field label="Reason" required><Select value={reason} onChange={(e) => setReason(e.target.value)}><option value="">Select…</option>{['Recipient unavailable', 'Address not found', 'Refused by recipient', 'Damaged goods', 'Unsafe location'].map((r) => <option key={r}>{r}</option>)}</Select></Field>
      </FormModal>
      <FormModal open={issueOpen} onOpenChange={setIssueOpen} title="Report an issue" size="sm" submitLabel="Send to dispatch" submitting={busy === 'issue'} onSubmit={async (e) => {
        e.preventDefault(); setBusy('issue')
        try { await api.post(`/driver/deliveries/${d.id}/issue`, { ...issue, ...(await here()) }); toast.success('Dispatch has been notified'); setIssueOpen(false); void done() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(null) }
      }}>
        <Field label="Issue"><Select value={issue.type} onChange={(e) => setIssue({ ...issue, type: e.target.value })}>{['recipient_unavailable', 'address_not_found', 'damaged_goods', 'vehicle_problem', 'refused', 'other'].map((t) => <option key={t} value={t}>{label(t)}</option>)}</Select></Field>
        <Field label="Details"><Textarea rows={3} value={issue.notes} onChange={(e) => setIssue({ ...issue, notes: e.target.value })} /></Field>
      </FormModal>
    </div>
  )
}
