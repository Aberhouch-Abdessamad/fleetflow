import {
  ArrowLeft, CheckCircle2, Circle, Copy, KeyRound, Link2, MapPin, Package, Pencil, Phone, Truck, UserRound, XCircle,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { FleetMap, type MapMarker } from '@/components/map/FleetMap'
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, Field, FormModal, Input, KeyValue, Modal, Skeleton, StatusBadge, Textarea,
} from '@/components/ui'
import { AssignDialog } from '@/features/dispatch/components/AssignDialog'
import { useInterval } from '@/hooks/usePaged'
import { useResource } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { ago, dateTime, km, label, money, num } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useChannel } from '@/lib/realtime'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Delivery, DeliveryStatus } from '@/types/domain'
import { PriorityBadge } from './DeliveriesPage'

const FLOW: DeliveryStatus[] = ['pending', 'assigned', 'accepted', 'picked_up', 'in_transit', 'arrived', 'delivered']

function Progress({ status }: { status: DeliveryStatus }) {
  const idx = FLOW.indexOf(status)
  const failed = ['failed', 'cancelled', 'returned'].includes(status)
  return (
    <div className="overflow-x-auto px-5 pb-5">
      <ol className="grid min-w-[560px] grid-cols-7 gap-1.5">
        {FLOW.map((s, i) => {
          const done = !failed && i <= idx
          const current = !failed && i === idx
          return (
            <li key={s} className="min-w-0">
              <span className={cn('block h-1 rounded-full transition-colors', done ? (current ? 'bg-primary' : 'bg-fg') : 'bg-surface-3')} />
              <span className={cn('mt-2 flex items-center gap-1 truncate text-[11px] font-medium', current ? 'text-primary' : done ? 'text-fg' : 'text-subtle')}>
                {done && !current ? <CheckCircle2 className="size-3 shrink-0" /> : <Circle className={cn('size-2.5 shrink-0', current && 'fill-current')} />}
                {label(s)}
              </span>
            </li>
          )
        })}
      </ol>
    </div>
  )
}

export default function DeliveryDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can, user } = useAuth()
  const { data: d, loading, error, reload } = useResource<Delivery>(`/deliveries/${id}`)
  const [assign, setAssign] = useState(false)
  const [statusTo, setStatusTo] = useState<DeliveryStatus | null>(null)
  const [cancel, setCancel] = useState(false)
  const [notes, setNotes] = useState('')
  const [cod, setCod] = useState('')
  const [busy, setBusy] = useState(false)
  const [otp, setOtp] = useState<string | null>(null)

  // Live: websocket when available, polling otherwise (only while the delivery is active).
  useChannel(user?.current_company ? `company.${user.current_company.id}` : null, ['DeliveryStatusUpdated', 'VehicleLocationUpdated'], (_e, p) => {
    if (Number(p.delivery_id) === Number(id) || (d?.vehicle && Number(p.vehicle_id) === d.vehicle.id)) void reload()
  })
  useInterval(() => void reload(), d && !['delivered', 'cancelled', 'returned', 'failed', 'draft'].includes(d.status) ? 20_000 : null)

  const markers = useMemo<MapMarker[]>(() => {
    if (!d) return []
    const m: MapMarker[] = []
    if (d.pickup.latitude && d.pickup.longitude) m.push({ id: 'p', lat: d.pickup.latitude, lng: d.pickup.longitude, kind: 'pickup', tone: 'accent', popup: `<b>Pickup</b><br/>${d.pickup.address}` })
    if (d.dropoff.latitude && d.dropoff.longitude) m.push({ id: 'd', lat: d.dropoff.latitude, lng: d.dropoff.longitude, kind: 'dropoff', tone: 'primary', popup: `<b>Destination</b><br/>${d.dropoff.address}` })
    const loc = d.vehicle?.location
    if (loc && !['delivered', 'cancelled', 'failed', 'returned'].includes(d.status)) m.push({ id: 'v', lat: loc.latitude, lng: loc.longitude, kind: 'vehicle', tone: loc.freshness === 'live' ? 'live' : 'stale', heading: loc.heading, label: d.vehicle!.registration_number })
    return m
  }, [d])

  if (loading && !d) return <div className="space-y-4"><Skeleton className="h-40" /><Skeleton className="h-96" /></div>
  if (error || !d) return <Alert tone="danger" title="Delivery not found">{error?.message}</Alert>

  const dispatcherNext = d.allowed_transitions.filter((s) => !['assigned', 'accepted', 'cancelled', 'pending'].includes(s))
  const canUnassign = can('deliveries.assign') && d.current_assignment && ['assigned', 'accepted'].includes(d.status)

  const doStatus = async () => {
    if (!statusTo) return
    setBusy(true)
    try {
      await api.patch(`/deliveries/${d.id}/status`, { status: statusTo, notes: notes || null, cod_collected_amount: cod || null })
      toast.success(`Marked ${label(statusTo).toLowerCase()}`); setStatusTo(null); setNotes(''); setCod(''); void reload()
    } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(false) }
  }

  return (
    <div className="space-y-5">
      <Link to="/app/deliveries" className="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-fg lg:hidden"><ArrowLeft className="size-3.5" /> Deliveries</Link>

      <Card>
        <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
          <div>
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-xl font-semibold">{d.reference_number}</h1>
              <StatusBadge status={d.status} />
              <PriorityBadge p={d.priority} />
              {d.is_late && <Badge tone="danger">Late</Badge>}
              {d.external_source && <Badge tone="info">{d.external_source} · {d.external_reference}</Badge>}
            </div>
            <p className="mt-1 text-sm text-muted">Created {dateTime(d.created_at)}{d.customer ? ` · ${d.customer.name}` : ''}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {d.tracking_url && <Button variant="outline" size="sm" onClick={() => { void navigator.clipboard.writeText(d.tracking_url!); toast.success('Tracking link copied') }}><Link2 /> Tracking link</Button>}
            {can('deliveries.update') && ['draft', 'pending', 'assigned', 'accepted'].includes(d.status) && <Button variant="outline" size="sm" onClick={() => navigate(`/app/deliveries/${d.id}/edit`)}><Pencil /> Edit</Button>}
            {can('deliveries.update') && d.requires_otp && !['delivered', 'cancelled', 'returned'].includes(d.status) && (
              <Button variant="outline" size="sm" onClick={async () => { try { setOtp((await api.post<{ otp: string }>(`/deliveries/${d.id}/otp`)).otp) } catch (e) { toast.error(toApiError(e).message) } }}><KeyRound /> New code</Button>
            )}
            {can('deliveries.assign') && ['pending', 'assigned', 'accepted'].includes(d.status) && <Button size="sm" onClick={() => setAssign(true)}><UserRound /> {d.driver ? 'Reassign' : 'Assign'}</Button>}
            {canUnassign && <ConfirmDialog title="Withdraw assignment?" description="The delivery returns to the unassigned queue." confirmLabel="Withdraw"
              onConfirm={async () => { try { await api.delete(`/dispatch/assignments/${d.current_assignment!.id}`); toast.success('Assignment withdrawn'); void reload() } catch (e) { toast.error(toApiError(e).message) } }}
              trigger={<Button size="sm" variant="outline">Unassign</Button>} />}
            {can('deliveries.update') && dispatcherNext.map((s) => (
              <Button key={s} size="sm" variant={s === 'failed' || s === 'returned' ? 'outline' : 'secondary'} onClick={() => setStatusTo(s)}>{label(s)}</Button>
            ))}
            {can('deliveries.cancel') && d.allowed_transitions.includes('cancelled') && <Button size="sm" variant="ghost" className="text-danger" onClick={() => setCancel(true)}><XCircle /> Cancel</Button>}
          </div>
        </div>
        <Progress status={d.status} />
        {d.status === 'failed' && <Alert tone="danger" className="mx-5 mb-5" title="Delivery failed">{d.failure_reason}</Alert>}
        {d.status === 'cancelled' && <Alert tone="warning" className="mx-5 mb-5" title="Cancelled">{d.cancellation_reason}</Alert>}
      </Card>

      <div className="grid gap-4 xl:grid-cols-[1fr_380px]">
        <div className="space-y-5">
          <Card className="overflow-hidden">
            <CardHeader title="Route" description={[d.distance_km && `${km(d.distance_km)} straight-line`, d.vehicle?.location && `vehicle ${d.vehicle.location.freshness === 'live' ? 'live' : 'last seen'} ${ago(d.vehicle.location.recorded_at)}`].filter(Boolean).join(' · ') || undefined} />
            <div className="h-80 p-3">{markers.length ? <FleetMap markers={markers} /> : <div className="grid h-full place-items-center rounded-xl border border-dashed border-border text-sm text-muted">No coordinates on this delivery — addresses only.</div>}</div>
            <div className="grid gap-px border-t border-border bg-border sm:grid-cols-2">
              {[['Pickup', d.pickup.address, d.pickup.contact_name, d.pickup.contact_phone, d.actual_pickup_at ?? d.scheduled_pickup_at, d.actual_pickup_at ? 'Picked up' : 'Scheduled'],
                ['Destination', d.dropoff.address, d.recipient.name, d.recipient.phone, d.actual_delivery_at ?? d.scheduled_delivery_at, d.actual_delivery_at ? 'Delivered' : 'Due']].map(([t, a, n, p, at, l]) => (
                <div key={String(t)} className="bg-surface p-4 text-sm">
                  <p className="flex items-center gap-1.5 text-xs font-medium text-subtle"><MapPin className="size-3.5" /> {t}</p>
                  <p className="mt-1 font-medium text-fg">{a}</p>
                  {n && <p className="mt-1 text-fg-soft">{n} {p && <a href={`tel:${p}`} className="inline-flex items-center gap-1 text-primary"><Phone className="size-3" />{p}</a>}</p>}
                  <p className="mt-1 text-xs text-muted">{l}: {dateTime(at as string | null)}</p>
                </div>
              ))}
            </div>
          </Card>

          <Card className="overflow-hidden"><CardHeader title="Items" icon={<Package />} description={`${num(d.total_weight, 1)} kg total`} />
            <DataTable rows={d.items ?? []} columns={[
              { key: 'd', header: 'Description', cell: (i) => i.description },
              { key: 'q', header: 'Qty', cell: (i) => i.quantity },
              { key: 'w', header: 'Weight', cell: (i) => `${num(i.weight, 1)} kg` },
              { key: 'x', header: 'Dimensions', cell: (i) => i.dimensions ?? '—', hideOnMobile: true },
            ]} />
          </Card>

          {(d.proofs?.length ?? 0) > 0 && (
            <Card><CardHeader title="Proof of delivery" icon={<CheckCircle2 />} /><CardBody className="grid gap-4 sm:grid-cols-2">
              {d.proofs!.map((p) => (
                <div key={p.id} className="rounded-xl border border-border p-3">
                  <p className="text-sm font-semibold">{label(p.type)} <span className="font-normal text-muted">· {dateTime(p.created_at)}</span></p>
                  {p.photo_url && <a href={fileUrl(p.photo_url)!} target="_blank" rel="noreferrer"><img src={fileUrl(p.photo_url)!} alt="Delivery photo" className="mt-2 h-44 w-full rounded-lg object-cover" /></a>}
                  {p.signature_url && <img src={fileUrl(p.signature_url)!} alt="Signature" className="mt-2 h-28 w-full rounded-lg bg-white object-contain p-2 ring-1 ring-border" />}
                  {p.recipient_name && <p className="mt-2 text-xs text-muted">Received by {p.recipient_name}</p>}
                  {p.type === 'otp' && <p className="mt-1 text-xs text-success">Recipient code verified</p>}
                </div>
              ))}
            </CardBody></Card>
          )}

          {(d.assignments?.length ?? 0) > 0 && (
            <Card className="overflow-hidden"><CardHeader title="Assignment history" />
              <DataTable rows={d.assignments!} columns={[
                { key: 'd', header: 'Driver', cell: (a) => a.driver },
                { key: 'v', header: 'Vehicle', cell: (a) => a.vehicle ?? '—' },
                { key: 's', header: 'Status', cell: (a) => <StatusBadge status={a.status} /> },
                { key: 'b', header: 'By', cell: (a) => a.assigned_by ?? '—', hideOnMobile: true },
                { key: 'r', header: 'Note', cell: (a) => a.reason ?? '—', hideOnMobile: true },
                { key: 't', header: 'When', cell: (a) => dateTime(a.created_at) },
              ]} />
            </Card>
          )}
        </div>

        <div className="space-y-5">
          <Card><CardHeader title="Assignment" icon={<Truck />} /><CardBody className="py-2">
            <KeyValue items={[
              ['Driver', d.driver ? <Link className="text-primary hover:underline" to={`/app/drivers/${d.driver.id}`}>{d.driver.name}</Link> : 'Unassigned'],
              ['Vehicle', d.vehicle ? <Link className="text-primary hover:underline" to={`/app/vehicles/${d.vehicle.id}`}>{d.vehicle.registration_number}</Link> : '—'],
              ['Assigned', dateTime(d.assigned_at)], ['Accepted', dateTime(d.accepted_at)],
              ['Route', d.route_id ? <Link className="text-primary hover:underline" to={`/app/routes/${d.route_id}`}>View route</Link> : '—'],
            ]} />
          </CardBody></Card>
          <Card><CardHeader title="Charges" /><CardBody className="py-2">
            <KeyValue items={[['Delivery fee', money(d.delivery_fee)], ['Cash on delivery', money(d.cod_amount)], ['COD collected', d.cod_collected_amount ? money(d.cod_collected_amount) : '—'], ['Invoiced', d.invoiced ? 'Yes' : 'No'],
              ['Proof required', d.requires_proof ? 'Photo or signature' : 'No'], ['Delivery code', d.requires_otp ? 'Required' : 'No'], ...(d.customer_rating ? [['Customer rating', `${d.customer_rating} / 5`] as [string, string]] : [])]} />
          </CardBody></Card>
          <Card><CardHeader title="Timeline" /><CardBody>
            <ol className="relative space-y-5 border-l border-border pl-5">
              {[...(d.timeline ?? [])].reverse().map((t) => (
                <li key={t.id} className="relative">
                  <span className="absolute -left-[26.5px] top-1 size-3 rounded-full border-2 border-surface bg-primary ring-2 ring-primary/20" />
                  <p className="text-sm font-semibold">{t.from === t.to ? 'Note' : label(t.to)}</p>
                  {t.notes && <p className="text-sm text-fg-soft">{t.notes}</p>}
                  <p className="text-xs text-muted">{dateTime(t.at)}{t.by ? ` · ${t.by}` : ''} · {t.source}</p>
                </li>
              ))}
            </ol>
          </CardBody></Card>
          {d.delivery_notes && <Card><CardHeader title="Instructions" /><CardBody className="whitespace-pre-line text-sm text-fg-soft">{d.delivery_notes}</CardBody></Card>}
        </div>
      </div>

      <AssignDialog delivery={d} open={assign} onOpenChange={setAssign} onAssigned={() => void reload()} />
      <FormModal open={Boolean(statusTo)} onOpenChange={(o) => !o && setStatusTo(null)} title={`Mark as ${label(statusTo)}`} size="sm" submitting={busy} submitLabel="Confirm" danger={statusTo === 'failed'}
        onSubmit={(e) => { e.preventDefault(); void doStatus() }}>
        {statusTo === 'delivered' && Number(d.cod_amount) > 0 && <Field label={`Cash collected (expected ${money(d.cod_amount)})`} required><Input type="number" step="0.01" value={cod} onChange={(e) => setCod(e.target.value)} /></Field>}
        <Field label={statusTo === 'failed' ? 'Failure reason' : 'Notes'} required={statusTo === 'failed'}><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
      </FormModal>
      <FormModal open={cancel} onOpenChange={setCancel} title="Cancel delivery" size="sm" danger submitLabel="Cancel delivery" submitting={busy} onSubmit={async (e) => {
        e.preventDefault(); setBusy(true)
        try { await api.post(`/deliveries/${d.id}/cancel`, { reason: notes }); toast.success('Delivery cancelled'); setCancel(false); setNotes(''); void reload() } catch (err) { toast.error(toApiError(err).message) } finally { setBusy(false) }
      }}>
        <Field label="Reason" required><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
      </FormModal>
      <Modal open={Boolean(otp)} onOpenChange={() => setOtp(null)} title="New delivery code" size="sm" description="The previous code no longer works. Share this one with the recipient."
        footer={<Button onClick={() => { void navigator.clipboard.writeText(otp!); toast.success('Copied'); setOtp(null) }}><Copy /> Copy & close</Button>}>
        <p className="rounded-xl bg-surface-2 py-6 text-center text-4xl font-semibold tracking-[0.3em]">{otp}</p>
      </Modal>
    </div>
  )
}
