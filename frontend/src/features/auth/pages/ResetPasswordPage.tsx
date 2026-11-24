import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Alert, Button, Field } from '@/components/ui'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'
import { PasswordInput, PasswordStrength } from '../components/PasswordInput'

export default function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [pw, setPw] = useState('')
  const [pw2, setPw2] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const token = params.get('token'), email = params.get('email')
  if (!token || !email) return <Alert tone="danger" title="Invalid link">This reset link is incomplete. <Link to="/forgot-password" className="font-semibold underline">Request a new one</Link>.</Alert>
  return (
    <div>
      <h1 className="text-2xl font-semibold tracking-[-0.025em]">Choose a new password</h1>
      <p className="mt-1 text-[0.8125rem] text-muted">For {email}</p>
      <form className="mt-7 space-y-4" onSubmit={async (e) => {
        e.preventDefault(); setBusy(true); setError(null)
        try { await api.post('/auth/reset-password', { email, token, password: pw, password_confirmation: pw2 }); toast.success('Password reset — please sign in'); navigate('/login') }
        catch (err) { const x = toApiError(err); setError(x.firstError('password') ?? x.message) } finally { setBusy(false) }
      }}>
        {error && <Alert tone="danger">{error}</Alert>}
        <Field label="New password" hint="8+ characters with letters and numbers"><PasswordInput autoComplete="new-password" value={pw} onChange={(e) => setPw(e.target.value)} /></Field>
        <PasswordStrength value={pw} />
        <Field label="Confirm password"><PasswordInput autoComplete="new-password" value={pw2} onChange={(e) => setPw2(e.target.value)} /></Field>
        <Button type="submit" size="lg" className="w-full" loading={busy}>Reset password</Button>
      </form>
    </div>
  )
}
