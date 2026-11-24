export interface ApiEnvelope<T> {
  success: boolean
  message: string | null
  data: T
  meta?: { pagination?: Pagination } & Record<string, unknown>
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface Company {
  id: string
  name: string
  slug: string
  logo_url: string | null
  registration_number: string | null
  contact_email: string
  phone: string | null
  country: string
  city: string
  address: string | null
  timezone: string
  currency: string
  status: 'pending' | 'active' | 'suspended'
  created_at: string | null
}

export interface CompanySummary {
  id: string
  name: string
  status: Company['status']
}

export type CompanyRole = 'company_admin' | 'fleet_manager' | 'dispatcher' | 'driver' | 'accountant' | 'customer'

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  email_verified: boolean
  is_super_admin: boolean
  status: 'active' | 'suspended'
  current_company: Company | null
  roles: CompanyRole[]
  permissions: string[]
  companies?: CompanySummary[]
}

export interface HealthReport {
  app: string
  api_version: string
  environment: string
  time: string
  database: { ok: boolean; driver: string; version?: string; latency_ms?: number }
  maps_provider: string | null
  broadcasting: string
}
