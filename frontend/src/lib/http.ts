import axios, { AxiosError } from 'axios'

export type ApiErrorKind = 'network' | 'validation' | 'unauthenticated' | 'forbidden' | 'not_found' | 'tenant' | 'rate_limited' | 'server'

/** Normalised error thrown by every API call. */
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly kind: ApiErrorKind,
    public readonly status: number,
    public readonly code: string | null = null,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }

  firstError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 20_000,
})

/** Selected workspace (company public id). The backend re-validates membership. */
let activeCompany: string | null = null
export function setActiveCompany(id: string | null) {
  activeCompany = id
}

http.interceptors.request.use((config) => {
  if (activeCompany) config.headers.set('X-Company', activeCompany)
  return config
})

type Listener = (e: ApiError) => void
const unauthenticatedListeners = new Set<Listener>()
export function onUnauthenticated(fn: Listener) {
  unauthenticatedListeners.add(fn)
  return () => unauthenticatedListeners.delete(fn)
}

export function toApiError(err: unknown): ApiError {
  if (err instanceof ApiError) return err
  const ax = err as AxiosError<{ message?: string; code?: string; errors?: Record<string, string[]> }>
  if (!ax?.isAxiosError) return new ApiError('Unexpected error.', 'server', 0)
  if (!ax.response) return new ApiError('Cannot reach the server. Check your connection.', 'network', 0)

  const { status, data } = ax.response
  const code = data?.code ?? null
  const message = data?.message || ax.message
  const kind: ApiErrorKind =
    status === 422 ? 'validation'
    : status === 401 ? 'unauthenticated'
    : code?.startsWith('tenant_') ? 'tenant'
    : status === 403 ? 'forbidden'
    : status === 404 ? 'not_found'
    : status === 429 ? 'rate_limited'
    : 'server'

  return new ApiError(message, kind, status, code, data?.errors ?? {})
}

http.interceptors.response.use(
  (r) => r,
  (err) => {
    const e = toApiError(err)
    if (e.kind === 'unauthenticated') unauthenticatedListeners.forEach((fn) => fn(e))
    return Promise.reject(e)
  },
)

/** Fetches the Sanctum CSRF cookie (needed once before stateful POSTs). */
export async function ensureCsrfCookie() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}
