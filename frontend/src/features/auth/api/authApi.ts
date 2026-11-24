import { api } from '@/lib/api'
import { ensureCsrfCookie } from '@/lib/http'
import type { Company, User } from '@/types/api'

export interface LoginInput { email: string; password: string }
export interface RegisterInput { name: string; email: string; phone?: string; password: string; password_confirmation: string }
export type CompanyInput = Pick<Company, 'name' | 'contact_email' | 'country' | 'city' | 'timezone' | 'currency'> &
  Partial<Pick<Company, 'phone' | 'address' | 'registration_number'>>

export const authApi = {
  async login(input: LoginInput) {
    await ensureCsrfCookie()
    return api.post<{ user: User }>('/auth/login', input)
  },
  async register(input: RegisterInput) {
    await ensureCsrfCookie()
    return api.post<User>('/auth/register', input)
  },
  logout: () => api.post<null>('/auth/logout'),
  me: () => api.get<User>('/auth/me'),
}

export const companyApi = {
  create: (input: CompanyInput) => api.post<Company>('/companies', input),
  current: () => api.get<Company>('/companies/current'),
  update: (input: Partial<CompanyInput>) => api.put<Company>('/companies/current', input),
  switch: (id: string) => api.post<Company>(`/companies/${id}/switch`),
}
