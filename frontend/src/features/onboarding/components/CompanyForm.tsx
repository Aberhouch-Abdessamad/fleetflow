import { zodResolver } from '@hookform/resolvers/zod'
import { Building2, Globe2, Mail, MapPin, Phone } from 'lucide-react'
import { useMemo, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Field, Input, Select } from '@/components/ui'
import type { CompanyInput } from '@/features/auth/api/authApi'
import { applyServerErrors } from '@/lib/forms'
import { browserTimezone, countries, currencies, timezones } from '@/lib/locale'

const schema = z.object({
  name: z.string().trim().min(2, 'Enter the company name').max(150),
  registration_number: z.string().trim().max(64).optional(),
  contact_email: z.string().trim().email('Enter a valid email'),
  phone: z.string().trim().max(32).optional(),
  country: z.string().length(2, 'Select a country'),
  city: z.string().trim().min(1, 'City is required').max(120),
  address: z.string().trim().max(255).optional(),
  timezone: z.string().min(1, 'Select a timezone'),
  currency: z.string().length(3, 'Select a currency'),
})
export type CompanyFormValues = z.infer<typeof schema>
const FIELDS = Object.keys(schema.shape) as (keyof CompanyFormValues)[]

function Section({ icon, title, description, children }: { icon: ReactNode; title: string; description: string; children: ReactNode }) {
  return (
    <section className="grid gap-5 py-7 first:pt-0 last:pb-0 md:grid-cols-[220px_1fr] md:gap-10">
      <div>
        <div className="flex items-center gap-2.5">
          <span className="grid size-7 place-items-center rounded-md border border-border bg-surface-2 text-muted [&_svg]:size-3.5">{icon}</span>
          <h2 className="text-[0.8125rem] font-semibold">{title}</h2>
        </div>
        <p className="mt-2 text-[0.8125rem] text-muted">{description}</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">{children}</div>
    </section>
  )
}

export function CompanyForm({ defaultValues, onSubmit, footer, id = 'company-form' }: {
  defaultValues?: Partial<Record<keyof CompanyFormValues, string | null>>
  onSubmit: (values: CompanyInput) => Promise<unknown>
  footer: (state: { isSubmitting: boolean; isDirty: boolean }) => ReactNode
  id?: string
}) {
  const countryList = useMemo(countries, [])
  const tzList = useMemo(timezones, [])
  const currencyList = useMemo(currencies, [])

  const { register, handleSubmit, setError, reset, formState: { errors, isSubmitting, isDirty } } = useForm<CompanyFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { timezone: browserTimezone(), currency: 'MAD', country: 'MA', ...clean(defaultValues) },
  })

  const submit = handleSubmit(async (values) => {
    try {
      await onSubmit({ ...values, registration_number: values.registration_number || null, phone: values.phone || null, address: values.address || null })
      reset(values)
    } catch (e) {
      applyServerErrors(e, setError, FIELDS)
    }
  })

  return (
    <form id={id} onSubmit={submit} noValidate>
      <div className="divide-y divide-border">
        <Section icon={<Building2 />} title="Identity" description="How your company appears to your team and customers.">
          <Field label="Company name" error={errors.name?.message} required className="sm:col-span-2">
            <Input placeholder="Atlas Logistics" {...register('name')} />
          </Field>
          <Field label="Business registration no." error={errors.registration_number?.message} hint="Optional — RC / ICE / VAT number.">
            <Input placeholder="RC 123456" {...register('registration_number')} />
          </Field>
        </Section>

        <Section icon={<Mail />} title="Contact" description="Used for notifications and on customer-facing pages.">
          <Field label="Contact email" error={errors.contact_email?.message} required>
            <Input type="email" placeholder="ops@company.com" leading={<Mail />} {...register('contact_email')} />
          </Field>
          <Field label="Phone" error={errors.phone?.message}>
            <Input type="tel" placeholder="+212 522 …" leading={<Phone />} {...register('phone')} />
          </Field>
        </Section>

        <Section icon={<MapPin />} title="Location" description="Your headquarters or main depot.">
          <Field label="Country" error={errors.country?.message} required>
            <Select {...register('country')}>
              {countryList.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
            </Select>
          </Field>
          <Field label="City" error={errors.city?.message} required>
            <Input placeholder="Casablanca" {...register('city')} />
          </Field>
          <Field label="Address" error={errors.address?.message} className="sm:col-span-2">
            <Input placeholder="Street, building, district" leading={<MapPin />} {...register('address')} />
          </Field>
        </Section>

        <Section icon={<Globe2 />} title="Preferences" description="Schedules, reports and amounts use these settings.">
          <Field label="Timezone" error={errors.timezone?.message} required>
            <Select {...register('timezone')}>
              {tzList.map((tz) => <option key={tz} value={tz}>{tz.replace(/_/g, ' ')}</option>)}
            </Select>
          </Field>
          <Field label="Currency" error={errors.currency?.message} required>
            <Select {...register('currency')}>
              {currencyList.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}
            </Select>
          </Field>
        </Section>
      </div>
      {footer({ isSubmitting, isDirty })}
    </form>
  )
}

function clean(v?: Partial<Record<string, unknown>>) {
  if (!v) return {}
  return Object.fromEntries(Object.entries(v).map(([k, val]) => [k, val ?? ''])) as Partial<CompanyFormValues>
}
