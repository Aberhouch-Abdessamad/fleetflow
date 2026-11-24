import { useCallback, useEffect, useRef, useState } from 'react'
import { api, type Paged } from '@/lib/api'
import { toApiError, type ApiError } from '@/lib/http'

/** List state: filters + page + debounced search, with stale-response protection. */
export function usePaged<T>(url: string, initial: Record<string, unknown> = {}) {
  const [filters, setFilters] = useState<Record<string, unknown>>({ page: 1, ...initial })
  const [result, setResult] = useState<Paged<T> | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(true)
  const seq = useRef(0)

  const load = useCallback(async () => {
    const id = ++seq.current
    setLoading(true)
    try {
      const r = await api.page<T>(url, filters)
      if (id === seq.current) { setResult(r); setError(null) }
    } catch (e) {
      if (id === seq.current) setError(toApiError(e))
    } finally {
      if (id === seq.current) setLoading(false)
    }
  }, [url, filters])

  useEffect(() => {
    const t = setTimeout(() => void load(), filters.search !== undefined ? 250 : 0)
    return () => clearTimeout(t)
  }, [load, filters.search])

  const set = useCallback((patch: Record<string, unknown>) => setFilters((f) => ({ ...f, ...patch, page: patch.page ?? 1 })), [])

  return { rows: result?.data ?? [], meta: result?.meta, filters, set, loading, error, reload: load }
}

export function useInterval(fn: () => void, ms: number | null) {
  const ref = useRef(fn)
  ref.current = fn
  useEffect(() => {
    if (ms === null) return
    const t = setInterval(() => ref.current(), ms)
    return () => clearInterval(t)
  }, [ms])
}
