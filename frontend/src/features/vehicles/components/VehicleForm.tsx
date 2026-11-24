import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { Field, FormModal, Input, Select, Textarea } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import type { Vehicle, VehicleType } from '@/types/domain'

const opt = z.string().optional().or(z.literal(''))
const schema = z.object({
  registration_number: z.string().trim().min(2, 'Required').max(32),
  vehicle_type_id: z.string().min(1, 'Select a type'),
  make: z.string().trim().min(1, 'Required').max(60),
  model: z.string().trim().min(1, 'Required').max(60),
  year: opt,
  color: opt,
  vin: opt,
  fuel_type: z.string().min(1),
  capacity: z.string().min(1, 'Required'),
  capacity_unit: z.string(),
  odometer: opt,
  insurance_provider: opt,
  insurance_policy_number: opt,
  insurance_expiry_date: opt,
  registration_expiry_date: opt,
  next_service_date: opt,
  next_service_odometer: opt,
  notes: opt,
})
type Values = z.infer<typeof schema>
const FIELDS = Object.keys(schema.shape) as (keyof Values)[]

export const FUEL_TYPES = ['diesel', 'petrol', 'electric', 'hybrid', 'lpg', 'cng']

export function VehicleForm({ open, onOpenChange, vehicle, onSaved }: { open: boolean; onOpenChange: (o: boolean) => void; vehicle?: Vehicle | null; onSaved: (v: Vehicle) => void }) {
  const types = useAsync(() => api.get<VehicleType[]>('/vehicle-types'), [])
  const [image, setImage] = useState<File | null>(null)
  const { register, handleSubmit, reset, setError, setValue, watch, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!open) return
    setImage(null)
    const v = vehicle
    reset({
      registration_number: v?.registration_number ?? '', vehicle_type_id: v ? String(v.vehicle_type_id) : '', make: v?.make ?? '', model: v?.model ?? '',
      year: v?.year ? String(v.year) : '', color: v?.color ?? '', vin: v?.vin ?? '', fuel_type: v?.fuel_type ?? 'diesel',
      capacity: v?.capacity ?? '', capacity_unit: v?.capacity_unit ?? 'kg', odometer: v ? String(v.odometer) : '',
      insurance_provider: v?.insurance_provider ?? '', insurance_policy_number: v?.insurance_policy_number ?? '',
      insurance_expiry_date: v?.insurance_expiry_date ?? '', registration_expiry_date: v?.registration_expiry_date ?? '',
      next_service_date: v?.next_service_date ?? '', next_service_odometer: v?.next_service_odometer ? String(v.next_service_odometer) : '', notes: v?.notes ?? '',
    })
  }, [open, vehicle, reset])

  const typeId = watch('vehicle_type_id')
  useEffect(() => {
    if (vehicle || !typeId) return
    const t = types.data?.find((x) => String(x.id) === typeId)
    if (t?.default_capacity) setValue('capacity', String(Number(t.default_capacity)))
  }, [typeId, types.data, vehicle, setValue])

  const submit = handleSubmit(async (values) => {
    try {
      const payload = { ...values, image }
      const saved = vehicle ? await api.upload<Vehicle>(`/vehicles/${vehicle.id}`, payload) : await api.upload<Vehicle>('/vehicles', payload)
      toast.success(vehicle ? 'Vehicle updated' : 'Vehicle added')
      onSaved(saved)
      onOpenChange(false)
    } catch (e) {
      applyServerErrors(e, setError, FIELDS)
    }
  })

  return (
    <FormModal open={open} onOpenChange={onOpenChange} title={vehicle ? `Edit ${vehicle.registration_number}` : 'Add vehicle'} onSubmit={submit} submitting={isSubmitting} submitLabel={vehicle ? 'Save changes' : 'Add vehicle'}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Registration number" error={errors.registration_number?.message} required><Input placeholder="12345-A-6" className="uppercase" {...register('registration_number')} /></Field>
        <Field label="Vehicle type" error={errors.vehicle_type_id?.message} required>
          <Select {...register('vehicle_type_id')}>
            <option value="">Select…</option>
            {types.data?.map((t) => <option key={t.id} value={t.id}>{t.name}{t.required_license ? ` · licence ${t.required_license}` : ''}</option>)}
          </Select>
        </Field>
        <Field label="Make" error={errors.make?.message} required><Input placeholder="Renault" {...register('make')} /></Field>
        <Field label="Model" error={errors.model?.message} required><Input placeholder="Master" {...register('model')} /></Field>
        <Field label="Year" error={errors.year?.message}><Input type="number" min={1950} {...register('year')} /></Field>
        <Field label="Color" error={errors.color?.message}><Input {...register('color')} /></Field>
        <Field label="VIN" error={errors.vin?.message}><Input className="uppercase" {...register('vin')} /></Field>
        <Field label="Fuel type" error={errors.fuel_type?.message} required>
          <Select {...register('fuel_type')}>{FUEL_TYPES.map((f) => <option key={f} value={f}>{f.toUpperCase() === 'LPG' || f === 'cng' ? f.toUpperCase() : f[0].toUpperCase() + f.slice(1)}</option>)}</Select>
        </Field>
        <Field label="Capacity" error={errors.capacity?.message} required>
          <div className="flex gap-2">
            <Input type="number" step="0.01" min={0} {...register('capacity')} />
            <Select className="w-24" {...register('capacity_unit')}>{['kg', 't', 'm3', 'pallets'].map((u) => <option key={u}>{u}</option>)}</Select>
          </div>
        </Field>
        <Field label="Odometer (km)" error={errors.odometer?.message}><Input type="number" min={0} {...register('odometer')} /></Field>
      </div>
      <p className="pt-2 text-xs font-medium text-subtle">Compliance & service</p>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Insurance provider" error={errors.insurance_provider?.message}><Input {...register('insurance_provider')} /></Field>
        <Field label="Policy number" error={errors.insurance_policy_number?.message}><Input {...register('insurance_policy_number')} /></Field>
        <Field label="Insurance expiry" error={errors.insurance_expiry_date?.message}><Input type="date" {...register('insurance_expiry_date')} /></Field>
        <Field label="Registration expiry" error={errors.registration_expiry_date?.message}><Input type="date" {...register('registration_expiry_date')} /></Field>
        <Field label="Next service date" error={errors.next_service_date?.message}><Input type="date" {...register('next_service_date')} /></Field>
        <Field label="Next service at (km)" error={errors.next_service_odometer?.message}><Input type="number" min={0} {...register('next_service_odometer')} /></Field>
        <Field label="Vehicle photo" hint="JPG, PNG or WebP up to 8 MB" className="sm:col-span-2">
          <Input type="file" accept="image/jpeg,image/png,image/webp" className="file:mr-3 file:rounded-md file:border-0 file:bg-surface-3 file:px-3 file:py-1 file:text-xs file:font-semibold pt-1.5" onChange={(e) => setImage(e.target.files?.[0] ?? null)} />
        </Field>
        <Field label="Notes" className="sm:col-span-2"><Textarea rows={2} {...register('notes')} /></Field>
      </div>
    </FormModal>
  )
}
