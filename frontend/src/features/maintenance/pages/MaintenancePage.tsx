import { AlertTriangle, CalendarDays, ChevronLeft, ChevronRight, List, Plus, Wrench, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import {
  Badge, Button, Card, CardHeader, DataTable, Field, FilterSelect, FormModal, Input, PageHeader, Segmented, Select, StatusBadge, Textarea, Toolbar, type Column,
} from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { usePaged } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { date, label, money } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { cn } from '@/lib/utils'
import { useAuth } from '@/stores/auth'
import type { Maintenance, Vehicle } from '@/types/domain'

export const MAINTENANCE_TYPES = ['regular_service', 'oil_change', 'tire_replacement', 'brake_inspection', 'engine_repair', 'electrical_repair', 'general_inspection', 'other']

function MaintenanceForm({ open, onOpenChange, record, onSaved }: { open: boolean; onOpenChange: (o: boolean) => void; record: Maintenance | null; onSaved: () => void }) {
  const vehicles = useOptions<Vehicle>('/vehicles')
  const blank = { vehicle_id: '', maintenance_type: 'regular_service', title: '', description: '', service_provider: '', scheduled_date: new Date().toISOString().slice(0, 10), completed_date: '', odometer: '', cost: '', status: 'scheduled', next_service_date: '', next_service_odometer: '' }
  const [f, setF] = useState(blank)
  const [invoice, setInvoice] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    if (!open) return
    setErrors({}); setInvoice(null)
    setF(record ? {
      vehicle_id: String(record.vehicle_id), maintenance_type: record.maintenance_type, title: record.title, description: record.description ?? '', service_provider: record.service_provider ?? '',
      scheduled_date: record.scheduled_date ?? '', completed_date: record.completed_date ?? '', odometer: record.odometer ? String(record.odometer) : '', cost: record.cost, status: record.status,
      next_service_date: record.next_service_date ?? '', next_service_odometer: record.next_service_odometer ? String(record.next_service_odometer) : '',
    } : blank)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, record])
  const s = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  const locked = record?.status === 'completed'
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title={record ? record.title : 'Schedule maintenance'} submitting={busy} submitLabel={record ? 'Save' : 'Schedule'} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true); setErrors({})
      try {
        const body = record ? { ...f, vehicle_id: undefined, invoice } : { ...f, invoice }
        await api.upload(record ? `/maintenance/${record.id}` : '/maintenance', body)
        toast.success('Maintenance saved'); onOpenChange(false); onSaved()
      } catch (err) { const x = toApiError(err); setErrors(x.errors); toast.error(x.message) } finally { setBusy(false) }
    }}>
      {f.status === 'in_progress' && <p className="rounded-lg bg-warning-soft px-3 py-2 text-xs font-medium text-warning">While in progress, the vehicle is "under maintenance" and cannot receive assignments.</p>}
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Vehicle" required error={errors.vehicle_id?.[0]}><Select value={f.vehicle_id} onChange={s('vehicle_id')} disabled={Boolean(record)}><option value="">Select…</option>{vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_number} · {v.make} {v.model}</option>)}</Select></Field>
        <Field label="Type" required><Select value={f.maintenance_type} onChange={s('maintenance_type')}>{MAINTENANCE_TYPES.map((t) => <option key={t} value={t}>{label(t)}</option>)}</Select></Field>
        <Field label="Title" required error={errors.title?.[0]} className="sm:col-span-2"><Input value={f.title} onChange={s('title')} /></Field>
        <Field label="Status"><Select value={f.status} onChange={s('status')} disabled={locked}>{['scheduled', 'in_progress', 'completed', 'cancelled'].map((x) => <option key={x} value={x}>{label(x)}</option>)}</Select></Field>
        <Field label="Service provider"><Input value={f.service_provider} onChange={s('service_provider')} /></Field>
        <Field label="Scheduled date"><Input type="date" value={f.scheduled_date} onChange={s('scheduled_date')} /></Field>
        <Field label="Completed date" error={errors.completed_date?.[0]}><Input type="date" value={f.completed_date} onChange={s('completed_date')} /></Field>
        <Field label="Odometer (km)"><Input type="number" value={f.odometer} onChange={s('odometer')} /></Field>
        <Field label="Cost" error={errors.cost?.[0]} hint={locked ? 'Locked — an expense was recorded' : 'Creates an expense on completion'}><Input type="number" step="0.01" value={f.cost} onChange={s('cost')} disabled={locked} /></Field>
        <Field label="Next service date"><Input type="date" value={f.next_service_date} onChange={s('next_service_date')} /></Field>
        <Field label="Next service at (km)"><Input type="number" value={f.next_service_odometer} onChange={s('next_service_odometer')} /></Field>
        <Field label="Invoice / report" className="sm:col-span-2"><Input type="file" accept="application/pdf,image/*" className="pt-1.5" onChange={(e) => setInvoice(e.target.files?.[0] ?? null)} /></Field>
        <Field label="Description" className="sm:col-span-2"><Textarea rows={2} value={f.description} onChange={s('description')} /></Field>
      </div>
    </FormModal>
  )
}

function Calendar({ records, onPick }: { records: Maintenance[]; onPick: (m: Maintenance) => void }) {
  const [month, setMonth] = useState(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
  const days = useMemo(() => {
    const start = new Date(month); start.setDate(1 - ((month.getDay() + 6) % 7))
    return [...Array(42)].map((_, i) => { const d = new Date(start); d.setDate(start.getDate() + i); return d })
  }, [month])
  const key = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const byDay = useMemo(() => {
    const m = new Map<string, Maintenance[]>()
    for (const r of records) { const k = r.completed_date ?? r.scheduled_date; if (k) m.set(k, [...(m.get(k) ?? []), r]) }
    return m
  }, [records])
  const today = key(new Date())
  return (
    <div>
      <div className="flex items-center justify-between border-b border-border px-4 py-3">
        <h3 className="text-base font-semibold">{month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h3>
        <div className="flex gap-1">
          <Button size="icon-sm" variant="outline" aria-label="Previous month" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))}><ChevronLeft /></Button>
          <Button size="sm" variant="outline" onClick={() => setMonth(new Date(new Date().getFullYear(), new Date().getMonth(), 1))}>Today</Button>
          <Button size="icon-sm" variant="outline" aria-label="Next month" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))}><ChevronRight /></Button>
        </div>
      </div>
      <div className="grid grid-cols-7 border-b border-border bg-surface-2 text-center text-xs font-medium text-muted">
        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => <div key={d} className="py-2">{d}</div>)}
      </div>
      <div className="grid grid-cols-7">
        {days.map((d) => {
          const k = key(d)
          const items = byDay.get(k) ?? []
          const inMonth = d.getMonth() === month.getMonth()
          return (
            <div key={k} className={cn('min-h-24 border-b border-r border-border p-1.5 [&:nth-child(7n)]:border-r-0', !inMonth && 'bg-surface-2/60')}>
              <p className={cn('mb-1 inline-grid size-6 place-items-center rounded-full text-xs font-semibold', k === today ? 'bg-primary text-white' : inMonth ? 'text-fg-soft' : 'text-subtle')}>{d.getDate()}</p>
              <div className="space-y-1">
                {items.slice(0, 3).map((m) => (
                  <button key={m.id} onClick={() => onPick(m)} className={cn('block w-full truncate rounded-md px-1.5 py-0.5 text-left text-[11px] font-semibold',
                    m.status === 'completed' ? 'bg-success-soft text-success' : m.status === 'in_progress' ? 'bg-warning-soft text-warning' : m.overdue ? 'bg-danger-soft text-danger' : m.status === 'cancelled' ? 'bg-surface-3 text-muted line-through' : 'bg-primary-soft text-primary')}>
                    {m.vehicle?.registration_number} · {m.title}
                  </button>
                ))}
                {items.length > 3 && <p className="px-1 text-[10px] text-muted">+{items.length - 3} more</p>}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default function MaintenancePage() {
  const can = useAuth((s) => s.can)
  const [view, setView] = useState<'list' | 'calendar'>('list')
  const [editing, setEditing] = useState<Maintenance | null>(null)
  const [open, setOpen] = useState(false)
  const vehicles = useOptions<Vehicle>('/vehicles')
  const { rows, meta, filters, set, loading, reload } = usePaged<Maintenance>('/maintenance', { status: '', type: '', vehicle_id: '', per_page: view === 'calendar' ? 100 : 20 })
  const reminders = useAsync(() => api.get<{ id: number; type: string; vehicle: { id: number; registration_number: string } | null; due_date: string | null; due_odometer: number | null; overdue: boolean }[]>('/maintenance/reminders'), [])
  const pick = (m: Maintenance) => { setEditing(m); setOpen(true) }

  const columns: Column<Maintenance>[] = [
    { key: 'v', header: 'Vehicle', cell: (m) => <Link onClick={(e) => e.stopPropagation()} to={`/app/vehicles/${m.vehicle_id}`} className="font-medium text-fg underline decoration-border-strong underline-offset-4 hover:decoration-fg">{m.vehicle?.registration_number}</Link> },
    { key: 't', header: 'Work', cell: (m) => <div><p className="font-semibold text-fg">{m.title}</p><p className="text-xs text-muted">{label(m.maintenance_type)}{m.service_provider ? ` · ${m.service_provider}` : ''}</p></div> },
    { key: 's', header: 'Status', cell: (m) => <StatusBadge status={m.overdue ? 'overdue' : m.status} /> },
    { key: 'd', header: 'Date', cell: (m) => date(m.completed_date ?? m.scheduled_date) },
    { key: 'n', header: 'Next service', cell: (m) => date(m.next_service_date), hideOnMobile: true },
    { key: 'i', header: '', cell: (m) => m.invoice_url ? <a onClick={(e) => e.stopPropagation()} href={fileUrl(m.invoice_url)!} target="_blank" rel="noreferrer" className="text-xs font-semibold text-primary">Invoice</a> : null, hideOnMobile: true },
    { key: 'c', header: 'Cost', cell: (m) => <span className="font-semibold">{money(m.cost)}</span>, className: 'text-right' },
  ]

  return (
    <>
      <PageHeader eyebrow="Fleet" title="Maintenance" description="Service schedule, workshop history and automatic reminders."
        actions={<>
          <Segmented value={view} onChange={(v) => { setView(v); set({ per_page: v === 'calendar' ? 100 : 20 }) }} options={[{ value: 'list', label: <span className="inline-flex items-center gap-1"><List className="size-3.5" />List</span> }, { value: 'calendar', label: <span className="inline-flex items-center gap-1"><CalendarDays className="size-3.5" />Calendar</span> }]} />
          {can('maintenance.manage') && <Button onClick={() => { setEditing(null); setOpen(true) }}><Plus /> Schedule</Button>}
        </>} />

      {(reminders.data?.length ?? 0) > 0 && (
        <Card className="mb-6">
          <CardHeader icon={<AlertTriangle />} title={`${reminders.data!.length} open reminders`} description="Generated daily by the scheduler" />
          <div className="flex flex-wrap gap-2 p-4">
            {reminders.data!.map((r) => (
              <span key={r.id} className={cn('inline-flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs', r.overdue ? 'border-danger/30 bg-danger-soft text-danger' : 'border-warning/30 bg-warning-soft text-warning')}>
                <Wrench className="size-3.5" />
                <Link to={`/app/vehicles/${r.vehicle?.id}`} className="font-semibold">{r.vehicle?.registration_number}</Link>
                {label(r.type)} {r.due_date ? `· ${date(r.due_date)}` : r.due_odometer ? `· ${r.due_odometer} km` : ''}
                {can('maintenance.manage') && <button aria-label="Dismiss" onClick={async () => { await api.post(`/maintenance/reminders/${r.id}/dismiss`); void reminders.reload() }}><X className="size-3" /></button>}
              </span>
            ))}
          </div>
        </Card>
      )}

      <Card className="overflow-hidden">
        <Toolbar>
          <FilterSelect placeholder="All vehicles" value={String(filters.vehicle_id ?? '')} onChange={(v) => set({ vehicle_id: v })} options={vehicles.map((v) => ({ value: String(v.id), label: v.registration_number }))} />
          <FilterSelect placeholder="All statuses" value={String(filters.status ?? '')} onChange={(v) => set({ status: v })} options={['scheduled', 'in_progress', 'completed', 'cancelled'].map((s) => ({ value: s, label: label(s) }))} />
          <FilterSelect placeholder="All types" value={String(filters.type ?? '')} onChange={(v) => set({ type: v })} options={MAINTENANCE_TYPES.map((t) => ({ value: t, label: label(t) }))} />
          {meta && <Badge className="sm:ml-auto">{meta.pagination.total} records</Badge>}
        </Toolbar>
        {view === 'list'
          ? <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} onRowClick={can('maintenance.manage') ? pick : undefined} />
          : <Calendar records={rows} onPick={can('maintenance.manage') ? pick : () => {}} />}
      </Card>
      <MaintenanceForm open={open} onOpenChange={setOpen} record={editing} onSaved={() => { void reload(); void reminders.reload() }} />
    </>
  )
}
