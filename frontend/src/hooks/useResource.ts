import { api } from '@/lib/api'
import { useAsync } from './useAsync'

export function useResource<T>(url: string | null, params?: Record<string, unknown>) {
  const key = url ? url + JSON.stringify(params ?? {}) : null
  return useAsync<T | null>(() => (url ? api.get<T>(url, params) : Promise.resolve(null)), [key])
}

/** Option lists for selects (first 100). Pass null to skip (e.g. no permission). */
export function useOptions<T>(url: string | null, params?: Record<string, unknown>) {
  const r = useAsync(() => (url ? api.page<T>(url, { per_page: 100, ...params }).then((p) => p.data) : Promise.resolve([] as T[])), [url, JSON.stringify(params ?? {})])
  return r.data ?? []
}
