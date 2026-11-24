import { Building2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Logo } from '@/components/brand/Logo'
import { Alert, Button, Field, Input, Skeleton } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { ensureCsrfCookie, toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'
import { PasswordInput } from '../components/PasswordInput'

export default function AcceptInvitationPage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { user, refresh, logout } = useAuth()
  const inv = useAsync(() => api.get<{ email: string; role: string; company: string; account_exists: boolean }>(`/invitations/${token}`), [token])
  const [f, setF] = useState({ name: '', phone: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  const accept = async () => {
    setBusy(true); setErrors({})
    try {
      await ensureCsrfCookie()
      await api.post(`/invitations/${token}/accept`, user ? {} : f)
      const me = await refresh()
      toast.success(`Welcome to ${inv.data?.company}`)
      navigate(me?.roles.includes('driver') ? '/driver' : '/app', { replace: true })
    } catch (e) { const x = toApiError(e); setErrors(x.errors); if (x.kind !== 'validation') toast.error(x.message) } finally { setBusy(false) }
  }

  return (
    <div className="grid min-h-dvh place-items-center p-5">
      <div className="w-full max-w-md">
        <div className="mb-8 flex justify-center"><Logo /></div>
        <div className="ff-card p-7">
          {inv.loading ? <Skeleton className="h-40" /> : inv.error ? <Alert tone="danger" title="Invitation unavailable">{inv.error.message}</Alert> : inv.data && (
            <>
              <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-lg border border-border bg-surface-2 text-muted [&_svg]:size-4"><Building2 /></span>
                <div><p className="text-sm text-muted">You're invited to join</p><p className="text-xl font-semibold">{inv.data.company}</p></div></div>
              <p className="mt-4 rounded-lg bg-surface-2 px-3 py-2 text-sm">as <b>{inv.data.role}</b> · {inv.data.email}</p>
              {user ? (
                user.email.toLowerCase() === inv.data.email ? <Button className="mt-6 w-full" size="lg" loading={busy} onClick={accept}>Accept invitation</Button>
                  : <Alert tone="warning" className="mt-6" title="Different account">You're signed in as {user.email}. <button className="font-semibold underline" onClick={() => logout()}>Sign out</button> to accept as {inv.data.email}.</Alert>
              ) : inv.data.account_exists ? (
                <Alert tone="info" className="mt-6">An account already exists for this email. <button className="font-semibold underline" onClick={() => navigate('/login', { state: { from: `/invitations/${token}` } })}>Sign in</button> to accept.</Alert>
              ) : (
                <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); void accept() }}>
                  <Field label="Full name" error={errors.name?.[0]}><Input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} /></Field>
                  <Field label="Phone"><Input value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} /></Field>
                  <Field label="Password" error={errors.password?.[0]}><PasswordInput autoComplete="new-password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} /></Field>
                  <Field label="Confirm password"><PasswordInput autoComplete="new-password" value={f.password_confirmation} onChange={(e) => setF({ ...f, password_confirmation: e.target.value })} /></Field>
                  <Button type="submit" className="w-full" size="lg" loading={busy}>Create account & join</Button>
                </form>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
