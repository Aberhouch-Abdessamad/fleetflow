import { AlertTriangle, CheckCircle2, Truck, UserRound } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Alert, Badge, Button, Modal, Skeleton } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { num } from '@/lib/format'
import { toApiError } from '@/lib/http'
import { cn } from '@/lib/utils'
import type { Delivery, Driver, Vehicle } from '@/types/domain'

interface DriverOption { driver: Driver; vehicle: { id: number; registration_number: string; label: string } | null; active_deliveries: number; eligible: boolean; reasons: string[] }
interface VehicleOption { vehicle: Vehicle; load: number; free_capacity: number; eligible: boolean; reasons: string[] }

/**
 * Assignment workflow: pick a driver (availability & licence checked) → pick a vehicle
 * (capacity, maintenance, conflicts checked server-side for that pairing) → assign.
 */
export function AssignDialog({ delivery, open, onOpenChange, onAssigned }: { delivery: Pick<Delivery, 'id' | 'reference_number' | 'total_weight' | 'driver' | 'vehicle'>; open: boolean; onOpenChange: (o: boolean) => void; onAssigned: () => void }) {
  const [driverId, setDriverId] = useState<number | null>(null)
  const [vehicleId, setVehicleId] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const drivers = useAsync(() => open ? api.get<DriverOption[]>('/dispatch/available-drivers', { delivery_id: delivery.id }) : Promise.resolve([]), [open, delivery.id])
  const vehicles = useAsync(() => open && driverId ? api.get<VehicleOption[]>('/dispatch/available-vehicles', { delivery_id: delivery.id, driver_id: driverId }) : Promise.resolve([]), [open, delivery.id, driverId])

  useEffect(() => { if (open) { setDriverId(null); setVehicleId(null); setError(null) } }, [open])
  const driverOpt = useMemo(() => drivers.data?.find((d) => d.driver.id === driverId), [drivers.data, driverId])
  useEffect(() => {
    // Pre-select the driver's own vehicle when it is eligible.
    if (driverOpt?.vehicle && vehicles.data?.some((v) => v.vehicle.id === driverOpt.vehicle!.id && v.eligible)) setVehicleId(driverOpt.vehicle.id)
  }, [driverOpt, vehicles.data])

  const reassign = Boolean(delivery.driver)
  const submit = async () => {
    if (!driverId || !vehicleId) return
    setBusy(true); setError(null)
    try {
      await api.post(`/deliveries/${delivery.id}/${reassign ? 'reassign' : 'assign'}`, { driver_id: driverId, vehicle_id: vehicleId })
      toast.success(`${delivery.reference_number} ${reassign ? 'reassigned' : 'assigned'}`)
      onOpenChange(false); onAssigned()
    } catch (e) { setError(toApiError(e).message) } finally { setBusy(false) }
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} size="lg" title={`${reassign ? 'Reassign' : 'Assign'} ${delivery.reference_number}`}
      description={`Load ${num(delivery.total_weight, 1)} kg. Eligibility is validated on the server when you confirm.`}
      footer={<>
        <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
        <Button onClick={submit} loading={busy} disabled={!driverId || !vehicleId}>{reassign ? 'Reassign' : 'Assign'}</Button>
      </>}>
      {error && <Alert tone="danger" className="mb-3">{error}</Alert>}
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-subtle"><UserRound className="size-3.5" /> 1 · Driver</p>
          <div className="max-h-80 space-y-1.5 overflow-y-auto pr-1">
            {drivers.loading ? [...Array(4)].map((_, i) => <Skeleton key={i} className="h-14" />)
              : drivers.data?.length === 0 ? <p className="text-sm text-muted">No active drivers.</p>
              : drivers.data?.map((o) => (
                <button key={o.driver.id} type="button" disabled={!o.eligible} onClick={() => { setDriverId(o.driver.id); setVehicleId(null) }}
                  className={cn('flex w-full items-center gap-3 rounded-xl border p-2.5 text-left transition-all disabled:cursor-not-allowed disabled:opacity-50',
                    driverId === o.driver.id ? 'border-primary bg-primary-soft ring-2 ring-primary/20' : 'border-border hover:border-border-strong hover:bg-surface-2')}>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">{o.driver.name} <span className="font-normal text-muted">· {o.driver.license_type}</span></p>
                    <p className="truncate text-xs text-muted">{o.reasons[0] ?? `${o.active_deliveries} active · ${o.vehicle?.registration_number ?? 'no vehicle'} · ${o.driver.availability.replace('_', ' ')}`}</p>
                  </div>
                  {driverId === o.driver.id && <CheckCircle2 className="size-4 text-primary" />}
                </button>
              ))}
          </div>
        </div>
        <div>
          <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-subtle"><Truck className="size-3.5" /> 2 · Vehicle</p>
          <div className="max-h-80 space-y-1.5 overflow-y-auto pr-1">
            {!driverId ? <p className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted">Select a driver first</p>
              : vehicles.loading ? [...Array(4)].map((_, i) => <Skeleton key={i} className="h-14" />)
              : vehicles.data?.map((o) => (
                <button key={o.vehicle.id} type="button" disabled={!o.eligible} onClick={() => setVehicleId(o.vehicle.id)}
                  className={cn('flex w-full items-center gap-3 rounded-xl border p-2.5 text-left transition-all disabled:cursor-not-allowed disabled:opacity-55',
                    vehicleId === o.vehicle.id ? 'border-primary bg-primary-soft ring-2 ring-primary/20' : 'border-border hover:border-border-strong hover:bg-surface-2')}>
                  <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-2 text-sm font-semibold">{o.vehicle.registration_number}
                      {driverOpt?.vehicle?.id === o.vehicle.id && <Badge tone="primary">Driver's vehicle</Badge>}</p>
                    {o.eligible
                      ? <p className="text-xs text-muted">{o.vehicle.type?.name} · {num(o.free_capacity)} {o.vehicle.capacity_unit} free</p>
                      : <p className="flex items-center gap-1 truncate text-xs text-danger"><AlertTriangle className="size-3" /> {o.reasons[0]}</p>}
                  </div>
                  {vehicleId === o.vehicle.id && <CheckCircle2 className="size-4 text-primary" />}
                </button>
              ))}
          </div>
        </div>
      </div>
    </Modal>
  )
}
