import { KeyRound, UserCog } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Badge, Button, Card, CardBody, CardHeader, Field, Input, PageHeader } from '@/components/ui'
import { PasswordInput } from '@/features/auth/components/PasswordInput'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'
import { useAuth } from '@/stores/auth'

export default function ProfilePage() {
  const { user, refresh } = useAuth()
  const [p, setP] = useState({ name: user!.name, email: user!.email, phone: user!.phone ?? '' })
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState<string | null>(null)
  return (
    <div className="space-y-5">
      <PageHeader eyebrow="Account" title="Profile & security" />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card><CardHeader icon={<UserCog />} title="Profile" description={user!.email_verified ? undefined : 'Your email is not verified yet'} action={user!.email_verified ? <Badge tone="success" dot>Verified</Badge> : <Badge tone="warning">Unverified</Badge>} />
          <CardBody>
            <form className="space-y-4" onSubmit={async (e) => {
              e.preventDefault(); setBusy('p'); setErrors({})
              try { await api.put('/auth/profile', p); await refresh(); toast.success(p.email !== user!.email ? 'Profile saved — check your inbox to verify the new email' : 'Profile saved') }
              catch (err) { const x = toApiError(err); setErrors(x.errors); toast.error(x.message) } finally { setBusy(null) }
            }}>
              <Field label="Full name" error={errors.name?.[0]}><Input value={p.name} onChange={(e) => setP({ ...p, name: e.target.value })} /></Field>
              <Field label="Email" error={errors.email?.[0]} hint="Changing it requires re-verification"><Input type="email" value={p.email} onChange={(e) => setP({ ...p, email: e.target.value })} /></Field>
              <Field label="Phone"><Input value={p.phone} onChange={(e) => setP({ ...p, phone: e.target.value })} /></Field>
              <div className="flex justify-end"><Button type="submit" loading={busy === 'p'}>Save profile</Button></div>
            </form>
          </CardBody>
        </Card>
        <Card><CardHeader icon={<KeyRound />} title="Password" description="Other sessions and API tokens are revoked when you change it." />
          <CardBody>
            <form className="space-y-4" onSubmit={async (e) => {
              e.preventDefault(); setBusy('pw'); setErrors({})
              try { await api.put('/auth/password', pw); toast.success('Password updated'); setPw({ current_password: '', password: '', password_confirmation: '' }) }
              catch (err) { const x = toApiError(err); setErrors(x.errors); toast.error(x.message) } finally { setBusy(null) }
            }}>
              <Field label="Current password" error={errors.current_password?.[0]}><PasswordInput autoComplete="current-password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} /></Field>
              <Field label="New password" error={errors.password?.[0]}><PasswordInput autoComplete="new-password" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} /></Field>
              <Field label="Confirm new password"><PasswordInput autoComplete="new-password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} /></Field>
              <div className="flex justify-end"><Button type="submit" loading={busy === 'pw'}>Update password</Button></div>
            </form>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
