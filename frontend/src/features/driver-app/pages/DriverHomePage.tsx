import { Check, ChevronRight, Clock, MapPin, Play, Square, Star, Weight, X } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Alert, Badge, Button, EmptyState, Field, FormModal, Skeleton, StatusBadge, Textarea } from '@/components/ui'
import { api } from '@/lib/api'
import { ago, num, pct, time } from '@/lib/format'
import { toApiError } from '@/lib/http'
import type { Delivery } from '@/types/domain'
import { DriverSection, useDriver } from '../DriverApp'

function Stat({ label, value }: { label: string; value: string | number }) {
  return <div className="px-2 py-3 text-center"><p className="text-lg font-semibold tabular-nums">{value}</p><p className="text-[11px] text-muted">{label}</p></div>
}

export function DeliveryRow({ d }: { d: Delivery }) {
  return (
    <Link to={`/driver/deliveries/${d.id}`} className="ff-card flex items-center gap-3 p-4 transition-colors active:scale-[0.99] hover:border-border-strong">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2"><span className="text-sm font-semibold">{d.reference_number}</span><StatusBadge status={d.status} />{d.priority === 'urgent' && <Badge tone="danger">Urgent</Badge>}</div>
        <p className="mt-1 flex items-start gap-1.5 text-sm text-fg-soft"><MapPin className="mt-0.5 size-3.5 shrink-0 text-primary" /><span className="line-clamp-2">{d.dropoff.address}</span></p>
        <p className="mt-1 flex flex-wrap gap-3 text-xs text-muted">
          {d.scheduled_delivery_at && <span className={`inline-flex items-center gap-1 ${d.is_late ? 'font-semibold text-danger' : ''}`}><Clock className="size-3" />{time(d.scheduled_delivery_at)}</span>}
          <span className="inline-flex items-center gap-1"><Weight className="size-3" />{num(d.total_weight, 1)} kg</span>
          {Number(d.cod_amount) > 0 && <span className="font-semibold text-warning">COD</span>}
        </p>
      </div>
      <ChevronRight className="size-5 text-subtle" />
    </Link>
  )
}

export default function DriverHomePage() {
  const { data, loading, error, reload, gps } = useDriver()
  const [busy, setBusy] = useState<string | null>(null)
  const [rejecting, setRejecting] = useState<Delivery | null>(null)
  const [reason, setReason] = useState('')

  if (loading && !data) return <div className="space-y-3"><Skeleton className="h-28" /><Skeleton className="h-24" /><Skeleton className="h-24" /></div>
  if (error || !data) return <Alert tone="danger" title="Driver profile unavailable">{error}</Alert>

  const act = async (key: string, fn: () => Promise<unknown>, msg: string) => {
    setBusy(key)
    try { await fn(); toast.success(msg); await reload() } catch (e) { toast.error(toApiError(e).message) } finally { setBusy(null) }
  }
  const respond = (d: Delivery, accept: boolean, why?: string) => act(`r${d.id}`, () => api.post(`/driver/assignments/${d.current_assignment!.id}/respond`, { accept, reason: why }), accept ? 'Assignment accepted' : 'Assignment rejected')

  return (
    <div>
      <div className="ff-card mb-5 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted">
              <span className={data.on_shift ? 'relative flex size-2' : 'size-2 rounded-full bg-border-strong'}>
                {data.on_shift && <><span className="absolute inset-0 animate-ping rounded-full bg-accent/60" /><span className="relative size-2 rounded-full bg-accent" /></>}
              </span>
              {data.on_shift ? 'On shift' : 'Off shift'}
            </p>
            <p className="mt-1 text-xl font-semibold tracking-tight">{data.driver.vehicle ? data.driver.vehicle.registration_number : 'No vehicle assigned'}</p>
            <p className="text-[0.8125rem] text-muted">{data.on_shift && data.shift_started_at ? `Started ${ago(data.shift_started_at)}` : 'Start your shift to receive work and share location.'}</p>
          </div>
        </div>
        <Button className="mt-4 w-full" variant={data.on_shift ? 'outline' : 'ink'} size="lg" loading={busy === 'shift'}
          onClick={() => act('shift', () => api.post(`/driver/shift/${data.on_shift ? 'end' : 'start'}`), data.on_shift ? 'Shift ended' : 'Shift started — location sharing is on')}>
          {data.on_shift ? <><Square /> End shift</> : <><Play /> Start shift</>}
        </Button>
        {gps.state === 'denied' && <Alert tone="danger" className="mt-3">Location permission is blocked. Allow location for this site in your browser settings so dispatch can see you.</Alert>}
      </div>

      <div className="ff-card mb-6 grid grid-cols-4 divide-x divide-border">
        <Stat label="Done today" value={data.stats.completed_today} />
        <Stat label="Active" value={data.stats.active} />
        <Stat label="On time" value={pct(data.stats.on_time_rate)} />
        <Stat label="Rating" value={data.stats.average_rating ?? '—'} />
      </div>

      {data.pending_assignments.length > 0 && (
        <DriverSection title={`New assignments (${data.pending_assignments.length})`}>
          <div className="space-y-3">
            {data.pending_assignments.map((d) => (
              <div key={d.id} className="ff-card border-primary/40 p-4 shadow-[0_0_0_3px_var(--primary-soft)]">
                <div className="flex items-center justify-between"><span className="font-mono text-[0.8125rem] font-medium">{d.reference_number}</span>{d.priority !== 'normal' && <Badge tone={d.priority === 'urgent' ? 'danger' : 'warning'}>{d.priority}</Badge>}</div>
                <p className="mt-2 text-sm"><span className="text-muted">From</span> {d.pickup.address}</p>
                <p className="text-sm"><span className="text-muted">To</span> {d.dropoff.address}</p>
                <p className="mt-1 text-xs text-muted">{num(d.total_weight, 1)} kg{d.scheduled_delivery_at ? ` · due ${time(d.scheduled_delivery_at)}` : ''}{d.vehicle ? ` · ${d.vehicle.registration_number}` : ''}</p>
                <div className="mt-3 grid grid-cols-2 gap-2">
                  {data.can_reject ? <Button variant="outline" onClick={() => { setRejecting(d); setReason('') }}><X /> Reject</Button> : <span />}
                  <Button loading={busy === `r${d.id}`} onClick={() => respond(d, true)} className={data.can_reject ? '' : 'col-span-2'}><Check /> Accept</Button>
                </div>
              </div>
            ))}
          </div>
        </DriverSection>
      )}

      <DriverSection title="Current deliveries">
        {data.active.length === 0 ? <EmptyState className="ff-card" icon={<Star />} title="No active deliveries" description="New assignments from dispatch appear here." />
          : <div className="space-y-3">{data.active.map((d) => <DeliveryRow key={d.id} d={d} />)}</div>}
      </DriverSection>

      <p className="text-center text-xs text-subtle">Today: {data.stats.total_deliveries} assigned · {data.stats.working_hours} h worked</p>

      <FormModal open={Boolean(rejecting)} onOpenChange={(o) => !o && setRejecting(null)} title="Reject assignment" size="sm" danger submitLabel="Reject" submitting={busy !== null}
        onSubmit={async (e) => { e.preventDefault(); if (rejecting) { await respond(rejecting, false, reason); setRejecting(null) } }}>
        <Field label="Reason" required><Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Vehicle issue, too far, already loaded…" /></Field>
      </FormModal>
    </div>
  )
}
