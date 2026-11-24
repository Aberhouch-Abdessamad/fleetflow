import { CheckCircle2, CircleDashed, RefreshCw, XCircle } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Logo } from '@/components/brand/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button, Card } from '@/components/ui'
import { authApi } from '@/features/auth/api/authApi'
import { ensureCsrfCookie, toApiError } from '@/lib/http'
import { systemApi } from '../api/systemApi'

type Check = { name: string; state: 'pending' | 'ok' | 'fail'; detail?: string }

const INITIAL: Check[] = [
  { name: 'API reachable (GET /api/v1/health)', state: 'pending' },
  { name: 'Database query from API', state: 'pending' },
  { name: 'Sanctum CSRF cookie (GET /sanctum/csrf-cookie)', state: 'pending' },
  { name: 'Session authentication (GET /api/v1/auth/me)', state: 'pending' },
]

/** Phase 1 verification page: every row is a real request. */
export default function SystemStatusPage() {
  const [checks, setChecks] = useState<Check[]>(INITIAL)
  const set = (i: number, c: Partial<Check>) => setChecks((prev) => prev.map((x, j) => (j === i ? { ...x, ...c } : x)))

  const run = useCallback(async () => {
    setChecks(INITIAL)
    try {
      const h = await systemApi.health()
      set(0, { state: 'ok', detail: `${h.app} · ${h.environment}` })
      set(1, h.database.ok ? { state: 'ok', detail: `${h.database.driver} ${h.database.version ?? ''} · ${h.database.latency_ms} ms` } : { state: 'fail', detail: 'Database unreachable' })
    } catch (e) {
      set(0, { state: 'fail', detail: toApiError(e).message })
      set(1, { state: 'fail', detail: 'Skipped' })
    }
    try {
      await ensureCsrfCookie()
      set(2, { state: document.cookie.includes('XSRF-TOKEN') ? 'ok' : 'fail', detail: document.cookie.includes('XSRF-TOKEN') ? 'XSRF-TOKEN cookie set' : 'Cookie not visible to the SPA' })
    } catch (e) {
      set(2, { state: 'fail', detail: toApiError(e).message })
    }
    try {
      const me = await authApi.me()
      set(3, { state: 'ok', detail: `Signed in as ${me.email}` })
    } catch (e) {
      const err = toApiError(e)
      set(3, err.status === 401 ? { state: 'ok', detail: 'Guard active — 401 for guests (expected)' } : { state: 'fail', detail: err.message })
    }
  }, [])

  useEffect(() => { void run() }, [run])
  const allOk = checks.every((c) => c.state === 'ok')

  return (
    <div className="min-h-dvh px-5 py-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-10 flex items-center justify-between">
          <Link to="/"><Logo /></Link>
          <ThemeToggle />
        </div>
        <h1 className="text-2xl font-extrabold">System status</h1>
        <p className="mt-1 text-sm text-muted">Verifies the SPA ↔ API ↔ database connection and the Sanctum handshake.</p>
        <Card className="mt-6 divide-y divide-border">
          {checks.map((c) => (
            <div key={c.name} className="flex items-start gap-3 px-5 py-4">
              {c.state === 'ok' ? <CheckCircle2 className="mt-0.5 size-5 text-success" /> : c.state === 'fail' ? <XCircle className="mt-0.5 size-5 text-danger" /> : <CircleDashed className="mt-0.5 size-5 animate-spin text-subtle" />}
              <div>
                <p className="text-sm font-semibold">{c.name}</p>
                {c.detail && <p className="mt-0.5 text-xs text-muted">{c.detail}</p>}
              </div>
            </div>
          ))}
        </Card>
        <div className="mt-5 flex items-center justify-between">
          <span className={`text-sm font-semibold ${allOk ? 'text-success' : 'text-muted'}`}>{allOk ? 'All systems operational' : 'Running checks…'}</span>
          <Button variant="outline" onClick={run}><RefreshCw /> Run again</Button>
        </div>
      </div>
    </div>
  )
}
