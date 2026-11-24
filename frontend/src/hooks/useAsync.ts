import { useCallback, useEffect, useRef, useState } from 'react'
import { toApiError, type ApiError } from '@/lib/http'

const keyOf = (deps: unknown[]) => {
  try { return JSON.stringify(deps) } catch { return String(Math.random()) }
}

/**
 * Loads data on mount / when deps change; ignores stale responses.
 * Data loaded for a previous set of deps is never returned for the new deps
 * (prevents rendering e.g. one tab's rows with another tab's columns),
 * while a plain reload() keeps showing the current data until it refreshes.
 * Failures are recorded against the deps key that produced them, so `error`
 * is only surfaced while current and `loading` settles after a failed load.
 */
export function useAsync<T>(fn: () => Promise<T>, deps: unknown[] = []) {
  const [state, setState] = useState<{ data: T | null; key: string | null; error: ApiError | null }>({ data: null, key: null, error: null })
  const [loading, setLoading] = useState(true)
  const seq = useRef(0)
  const key = keyOf(deps)

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const run = useCallback(fn, deps)

  const reload = useCallback(async () => {
    const id = ++seq.current
    setLoading(true)
    try {
      const result = await run()
      if (id === seq.current) setState({ data: result, key, error: null })
    } catch (e) {
      // Tag the failure with the current key: state.key used to stay stale on
      // error, which kept `loading` true forever (e.g. eternal skeleton on 404).
      if (id === seq.current) setState((s) => ({ data: s.key === key ? s.data : null, key, error: toApiError(e) }))
    } finally {
      if (id === seq.current) setLoading(false)
    }
  }, [run, key])

  useEffect(() => {
    void reload()
  }, [reload])

  const setData = useCallback((next: T | null | ((prev: T | null) => T | null)) => {
    setState((s) => ({ key: s.key, data: typeof next === 'function' ? (next as (p: T | null) => T | null)(s.data) : next, error: null }))
  }, [])

  const done = state.key === key
  return { data: done ? state.data : null, error: done ? state.error : null, loading: loading || !done, reload, setData }
}
