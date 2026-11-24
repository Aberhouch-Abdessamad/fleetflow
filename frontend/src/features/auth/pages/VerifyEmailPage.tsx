import { CheckCircle2, XCircle } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { LogoMark } from '@/components/brand/Logo'
import { Button, Spinner } from '@/components/ui'
import { http, toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'

export default function VerifyEmailPage() {
  const { search } = useLocation()
  const refresh = useAuth((s) => s.refresh)
  const [state, setState] = useState<'loading' | 'ok' | 'error'>('loading')
  const [msg, setMsg] = useState('')
  useEffect(() => {
    const p = new URLSearchParams(search)
    const id = p.get('id'), hash = p.get('hash')
    p.delete('id'); p.delete('hash')
    http.get(`/auth/verify/${id}/${hash}?${p.toString()}`).then(() => { setState('ok'); void refresh() }).catch((e) => { setState('error'); setMsg(toApiError(e).message) })
  }, [search, refresh])
  return (
    <div className="grid min-h-dvh place-items-center p-6">
      <div className="ff-card w-full max-w-md p-8 text-center">
        <LogoMark className="mx-auto size-11" />
        {state === 'loading' ? <Spinner className="mx-auto mt-6 text-primary" />
          : state === 'ok' ? <><CheckCircle2 className="mx-auto mt-6 size-10 text-success" /><h1 className="mt-3 text-xl font-semibold">Email verified</h1><p className="mt-1 text-sm text-muted">Thanks — your account is confirmed.</p></>
          : <><XCircle className="mx-auto mt-6 size-10 text-danger" /><h1 className="mt-3 text-xl font-semibold">Verification failed</h1><p className="mt-1 text-sm text-muted">{msg}</p></>}
        <Button asChild className="mt-6"><Link to="/">Continue</Link></Button>
      </div>
    </div>
  )
}
