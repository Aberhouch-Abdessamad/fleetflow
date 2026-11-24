import type { ApiEnvelope, Pagination } from '@/types/api'
import { http } from './http'

export interface Paged<T> { data: T[]; meta: { pagination: Pagination } & Record<string, unknown> }

type Params = Record<string, unknown>

function clean(params?: Params) {
  if (!params) return undefined
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false))
}

/** Typed helpers that unwrap the { success, data, meta } envelope. */
export const api = {
  async get<T>(url: string, params?: Params): Promise<T> {
    return (await http.get<ApiEnvelope<T>>(url, { params: clean(params) })).data.data
  },
  async page<T>(url: string, params?: Params): Promise<Paged<T>> {
    const r = (await http.get<ApiEnvelope<T[]>>(url, { params: clean(params) })).data
    return { data: r.data, meta: (r.meta ?? { pagination: { current_page: 1, per_page: r.data.length, total: r.data.length, last_page: 1 } }) as Paged<T>['meta'] }
  },
  async post<T>(url: string, body?: unknown): Promise<T> {
    return (await http.post<ApiEnvelope<T>>(url, body)).data.data
  },
  async put<T>(url: string, body?: unknown): Promise<T> {
    return (await http.put<ApiEnvelope<T>>(url, body)).data.data
  },
  async patch<T>(url: string, body?: unknown): Promise<T> {
    return (await http.patch<ApiEnvelope<T>>(url, body)).data.data
  },
  async delete<T>(url: string): Promise<T> {
    return (await http.delete<ApiEnvelope<T>>(url)).data.data
  },
  /** Multipart (files). Always POST — update routes accept POST for multipart. */
  async upload<T>(url: string, data: Record<string, unknown>): Promise<T> {
    const fd = new FormData()
    for (const [k, v] of Object.entries(data)) {
      if (v === undefined || v === null || v === '') continue
      if (v instanceof File || v instanceof Blob) fd.append(k, v)
      else if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
      else if (Array.isArray(v)) v.forEach((x, i) => fd.append(`${k}[${i}]`, String(x)))
      else fd.append(k, String(v))
    }
    return (await http.post<ApiEnvelope<T>>(url, fd)).data.data
  },
  /** Authenticated file download (exports, private documents). */
  async download(url: string, params?: Params, filename?: string) {
    const r = await http.get(url, { params: clean(params), responseType: 'blob' })
    const name = filename ?? /filename="?([^"]+)"?/.exec(String(r.headers['content-disposition'] ?? ''))?.[1] ?? 'download'
    const href = URL.createObjectURL(r.data as Blob)
    const a = document.createElement('a')
    a.href = href
    a.download = name
    a.click()
    setTimeout(() => URL.revokeObjectURL(href), 1000)
  },
}

/** Private files are served from /api/v1/files/... — strip the prefix for the axios baseURL. */
export function fileUrl(path: string | null | undefined): string | null {
  if (!path) return null
  const base = String(import.meta.env.VITE_API_URL ?? '/api/v1')
  if (/^https?:/.test(base)) return new URL(base).origin + path
  return path
}
