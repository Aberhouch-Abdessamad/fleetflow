import { ArrowLeft, Mail, MailCheck } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Field, Input } from '@/components/ui'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  return (
    <div>
      <Link to="/login" className="mb-6 inline-flex items-center gap-1.5 text-sm text-muted hover:text-fg"><ArrowLeft className="size-4" /> Back to sign in</Link>
      <h1 className="text-2xl font-semibold tracking-[-0.025em]">Reset your password</h1>
      <p className="mt-1 text-[0.8125rem] text-muted">Enter your email and we'll send you a reset link.</p>
      {sent ? (
        <Alert tone="success" className="mt-8" title="Check your inbox"><span className="inline-flex items-center gap-1.5"><MailCheck className="size-4" /> If an account exists for {email}, a reset link is on its way. It expires in 60 minutes.</span></Alert>
      ) : (
        <form className="mt-7 space-y-4" onSubmit={async (e) => {
          e.preventDefault(); setBusy(true); setError(null)
          try { await api.post('/auth/forgot-password', { email }); setSent(true) } catch (err) { setError(toApiError(err).message) } finally { setBusy(false) }
        }}>
          {error && <Alert tone="danger">{error}</Alert>}
          <Field label="Email"><Input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} leading={<Mail />} autoComplete="email" /></Field>
          <Button type="submit" size="lg" className="w-full" loading={busy}>Send reset link</Button>
        </form>
      )}
    </div>
  )
}
