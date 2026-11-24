import { Droplets, Fuel, Gauge, Plus, Wallet } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Button, Card, DataTable, Field, FilterSelect, FormModal, Input, PageHeader, Select, StatCard, Switch, Toolbar, type Column } from '@/components/ui'
import { FUEL_TYPES } from '@/features/vehicles/components/VehicleForm'
import { usePaged } from '@/hooks/usePaged'
import { useOptions } from '@/hooks/useResource'
import { api, fileUrl } from '@/lib/api'
import { dateTime, money, num, toLocalInput } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import type { Driver, FuelRecord, Vehicle } from '@/types/domain'

export function FuelForm({ open, onOpenChange, onSaved, fixedVehicleId }: { open: boolean; onOpenChange: (o: boolean) => void; onSaved: () => void; fixedVehicleId?: number }) {
  const can = useAuth((s) => s.can)
  const vehicles = useOptions<Vehicle>(can('vehicles.view') ? '/vehicles' : null)
  const drivers = useOptions<Driver>(can('drivers.view') ? '/drivers' : null)
  const [f, setF] = useState({ vehicle_id: fixedVehicleId ? String(fixedVehicleId) : '', driver_id: '', fuel_type: 'diesel', quantity: '', unit_price: '', odometer: '', station_name: '', recorded_at: toLocalInput(new Date().toISOString()) })
  const [full, setFull] = useState(true)
  const [receipt, setReceipt] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const s = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value })
  const total = (Number(f.quantity) || 0) * (Number(f.unit_price) || 0)
  return (
    <FormModal open={open} onOpenChange={onOpenChange} title="Record fuel" size="md" submitting={busy} onSubmit={async (e) => {
      e.preventDefault(); setBusy(true); setErrors({})
      try {
        await api.upload('/fuel-records', { ...f, full_tank: full, recorded_at: new Date(f.recorded_at).toISOString(), receipt })
        toast.success('Fuel record saved'); onOpenChange(false); onSaved()
      } catch (err) { const x = toApiError(err); setErrors(x.errors); toast.error(x.message) } finally { setBusy(false) }
    }}>
      <div className="grid gap-4 sm:grid-cols-2">
        {!fixedVehicleId && <Field label="Vehicle" required error={errors.vehicle_id?.[0]}><Select value={f.vehicle_id} onChange={s('vehicle_id')}><option value="">Select…</option>{vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_number}</option>)}</Select></Field>}
        {drivers.length > 0 && <Field label="Driver"><Select value={f.driver_id} onChange={s('driver_id')}><option value="">—</option>{drivers.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}</Select></Field>}
        <Field label="Fuel type"><Select value={f.fuel_type} onChange={s('fuel_type')}>{FUEL_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}</Select></Field>
        <Field label="Date & time" required error={errors.recorded_at?.[0]}><Input type="datetime-local" value={f.recorded_at} onChange={s('recorded_at')} /></Field>
        <Field label="Quantity (L)" required error={errors.quantity?.[0]}><Input type="number" step="0.01" value={f.quantity} onChange={s('quantity')} /></Field>
        <Field label="Unit price" required error={errors.unit_price?.[0]}><Input type="number" step="0.001" value={f.unit_price} onChange={s('unit_price')} /></Field>
        <Field label="Odometer (km)" required error={errors.odometer?.[0]}><Input type="number" value={f.odometer} onChange={s('odometer')} /></Field>
        <Field label="Station"><Input value={f.station_name} onChange={s('station_name')} /></Field>
        <Field label="Receipt" className="sm:col-span-2"><Input type="file" accept="application/pdf,image/*" capture="environment" className="pt-1.5" onChange={(e) => setReceipt(e.target.files?.[0] ?? null)} /></Field>
      </div>
      <Switch checked={full} onChange={setFull} label="Full tank" description="Full-to-full fills are used to compute consumption" />
      <p className="rounded-lg bg-surface-2 px-3 py-2 text-sm text-muted">Estimated total <b className="text-fg">{money(total)}</b> — the final amount is calculated by the server.</p>
    </FormModal>
  )
}

export default function FuelPage() {
  const can = useAuth((s) => s.can)
  const [open, setOpen] = useState(false)
  const vehicles = useOptions<Vehicle>('/vehicles')
  const { rows, meta, filters, set, loading, reload } = usePaged<FuelRecord>('/fuel-records', { vehicle_id: '', from: '', to: '' })
  const sum = meta?.summary as { litres: number; total_cost: string; average_unit_price: number | null; l_per_100km: number | null; records: number } | undefined
  const columns: Column<FuelRecord>[] = [
    { key: 'd', header: 'Date', cell: (r) => dateTime(r.recorded_at) },
    { key: 'v', header: 'Vehicle', cell: (r) => <span className="font-semibold">{r.vehicle?.registration_number}</span> },
    { key: 'dr', header: 'Driver', cell: (r) => r.driver?.name ?? '—', hideOnMobile: true },
    { key: 'q', header: 'Litres', cell: (r) => `${num(r.quantity, 2)}${r.full_tank ? '' : ' · partial'}` },
    { key: 'p', header: 'Unit price', cell: (r) => money(r.unit_price), hideOnMobile: true },
    { key: 'o', header: 'Odometer', cell: (r) => `${num(r.odometer)} km`, hideOnMobile: true },
    { key: 's', header: 'Station', cell: (r) => r.station_name ?? '—', hideOnMobile: true },
    { key: 'r', header: '', cell: (r) => r.receipt_url ? <a href={fileUrl(r.receipt_url)!} target="_blank" rel="noreferrer" className="text-xs font-semibold text-primary">Receipt</a> : null, hideOnMobile: true },
    { key: 't', header: 'Total', cell: (r) => <span className="font-semibold">{money(r.total_cost)}</span>, className: 'text-right' },
  ]
  return (
    <>
      <PageHeader eyebrow="Fleet" title="Fuel" description="Fuel purchases, consumption and cost per kilometre."
        actions={(can('fuel.manage') || can('fuel.create_own')) && <Button onClick={() => setOpen(true)}><Plus /> Record fuel</Button>} />
      <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Fuel spend" value={sum ? money(sum.total_cost) : null} icon={<Wallet />} loading={!sum} hint={sum ? `${sum.records} fill-ups` : undefined} />
        <StatCard label="Litres" value={sum ? num(sum.litres, 1) : null} icon={<Droplets />} tone="accent" loading={!sum} />
        <StatCard label="Average price / L" value={sum?.average_unit_price ? money(sum.average_unit_price) : '—'} icon={<Fuel />} tone="warning" loading={!sum} />
        <StatCard label="Fleet consumption" value={sum?.l_per_100km ? `${sum.l_per_100km} L/100km` : '—'} icon={<Gauge />} tone="success" loading={!sum} hint="From full-to-full fills" />
      </div>
      <Card>
        <Toolbar>
          <FilterSelect placeholder="All vehicles" value={String(filters.vehicle_id ?? '')} onChange={(v) => set({ vehicle_id: v })} options={vehicles.map((v) => ({ value: String(v.id), label: v.registration_number }))} />
          <Input type="date" className="h-9 sm:w-40" value={String(filters.from ?? '')} onChange={(e) => set({ from: e.target.value })} aria-label="From" />
          <Input type="date" className="h-9 sm:w-40" value={String(filters.to ?? '')} onChange={(e) => set({ to: e.target.value })} aria-label="To" />
        </Toolbar>
        <DataTable columns={columns} rows={rows} loading={loading} meta={meta} onPage={(p) => set({ page: p })} />
      </Card>
      {open && <FuelForm open={open} onOpenChange={setOpen} onSaved={() => void reload()} />}
    </>
  )
}
