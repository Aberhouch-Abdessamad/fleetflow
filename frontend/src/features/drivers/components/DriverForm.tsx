import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { Field, FormModal, Input, Select, Textarea } from '@/components/ui'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import type { Driver } from '@/types/domain'

const opt = z.string().optional().or(z.literal(''))
const schema = z.object({
  first_name: z.string().trim().min(1, 'Required').max(80),
  last_name: z.string().trim().min(1, 'Required').max(80),
  phone: z.string().trim().min(3, 'Required').max(32),
  email: z.string().trim().email('Invalid email').optional().or(z.literal('')),
  license_number: z.string().trim().min(1, 'Required').max(64),
  license_type: z.enum(['A', 'B', 'C', 'CE', 'D']),
  license_expiry_date: z.string().min(1, 'Required'),
  employment_date: opt, national_id: opt, address: opt, date_of_birth: opt, emergency_contact: opt, emergency_phone: opt, notes: opt,
})
type Values = z.infer<typeof schema>
const FIELDS = Object.keys(schema.shape) as (keyof Values)[]

export const LICENSES = [
  { value: 'A', label: 'A — Motorcycle' }, { value: 'B', label: 'B — Car / van' }, { value: 'C', label: 'C — Truck' },
  { value: 'CE', label: 'CE — Articulated truck' }, { value: 'D', label: 'D — Bus' },
]

export function DriverForm({ open, onOpenChange, driver, onSaved }: { open: boolean; onOpenChange: (o: boolean) => void; driver?: Driver | null; onSaved: (d: Driver) => void }) {
  const [photo, setPhoto] = useState<File | null>(null)
  const { register, handleSubmit, reset, setError, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })
  useEffect(() => {
    if (!open) return
    setPhoto(null)
    const d = driver
    reset({
      first_name: d?.first_name ?? '', last_name: d?.last_name ?? '', phone: d?.phone ?? '', email: d?.email ?? '',
      license_number: d?.license_number ?? '', license_type: (d?.license_type as Values['license_type']) ?? 'B', license_expiry_date: d?.license_expiry_date ?? '',
      employment_date: d?.employment_date ?? '', national_id: d?.national_id ?? '', address: d?.address ?? '', date_of_birth: d?.date_of_birth ?? '',
      emergency_contact: d?.emergency_contact ?? '', emergency_phone: d?.emergency_phone ?? '', notes: d?.notes ?? '',
    })
  }, [open, driver, reset])

  const submit = handleSubmit(async (values) => {
    try {
      const saved = await api.upload<Driver>(driver ? `/drivers/${driver.id}` : '/drivers', { ...values, photo })
      toast.success(driver ? 'Driver updated' : 'Driver added')
      onSaved(saved); onOpenChange(false)
    } catch (e) { applyServerErrors(e, setError, FIELDS) }
  })

  return (
    <FormModal open={open} onOpenChange={onOpenChange} title={driver ? `Edit ${driver.name}` : 'Add driver'} onSubmit={submit} submitting={isSubmitting} submitLabel={driver ? 'Save changes' : 'Add driver'}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="First name" error={errors.first_name?.message} required><Input {...register('first_name')} /></Field>
        <Field label="Last name" error={errors.last_name?.message} required><Input {...register('last_name')} /></Field>
        <Field label="Phone" error={errors.phone?.message} required><Input type="tel" {...register('phone')} /></Field>
        <Field label="Email" error={errors.email?.message} hint="Used to invite the driver to the app"><Input type="email" {...register('email')} /></Field>
        <Field label="Licence number" error={errors.license_number?.message} required><Input {...register('license_number')} /></Field>
        <Field label="Licence category" error={errors.license_type?.message} required><Select {...register('license_type')}>{LICENSES.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}</Select></Field>
        <Field label="Licence expiry" error={errors.license_expiry_date?.message} required><Input type="date" {...register('license_expiry_date')} /></Field>
        <Field label="Employment date" error={errors.employment_date?.message}><Input type="date" {...register('employment_date')} /></Field>
      </div>
      <p className="pt-2 text-xs font-medium text-subtle">Private information</p>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="National ID" error={errors.national_id?.message}><Input {...register('national_id')} /></Field>
        <Field label="Date of birth" error={errors.date_of_birth?.message}><Input type="date" {...register('date_of_birth')} /></Field>
        <Field label="Address" className="sm:col-span-2"><Input {...register('address')} /></Field>
        <Field label="Emergency contact"><Input {...register('emergency_contact')} /></Field>
        <Field label="Emergency phone"><Input type="tel" {...register('emergency_phone')} /></Field>
        <Field label="Photo" className="sm:col-span-2"><Input type="file" accept="image/*" className="pt-1.5" onChange={(e) => setPhoto(e.target.files?.[0] ?? null)} /></Field>
        <Field label="Notes" className="sm:col-span-2"><Textarea rows={2} {...register('notes')} /></Field>
      </div>
    </FormModal>
  )
}
