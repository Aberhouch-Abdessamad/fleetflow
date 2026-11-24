import { useCallback, useEffect, useRef, useState } from 'react'
import { toApiError, type ApiError } from '@/lib/http'

/** Loads data on mount / when deps change; ignores stale responses. */
export function useAsync<T>(fn: () => Promise<T>, deps: unknown[] = []) {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(true)
  const seq = useRef(0)

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const run = useCallback(fn, deps)

  const reload = useCallback(async () => {
    const id = ++seq.current
    setLoading(true)
    setError(null)
    try {
      const result = await run()
      if (id === seq.current) setData(result)
    } catch (e) {
      if (id === seq.current) setError(toApiError(e))
    } finally {
      if (id === seq.current) setLoading(false)
    }
  }, [run])

  useEffect(() => {
    void reload()
  }, [reload])

  return { data, error, loading, reload, setData }
}
