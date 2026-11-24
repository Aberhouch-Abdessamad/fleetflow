import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'
import { LogoMark } from '@/components/brand/Logo'
import { Button, EmptyState, Spinner } from '@/components/ui'
import { useAuth } from '@/stores/auth'

export function FullScreenLoader() {
  return (
    <div className="grid min-h-dvh place-items-center">
      <div className="flex flex-col items-center gap-4 animate-fade-in">
        <LogoMark className="size-8" />
        <Spinner className="text-muted" />
      </div>
    </div>
  )
}

export function RequireAuth() {
  const { status } = useAuth()
  const location = useLocation()
  if (status === 'idle' || status === 'loading') return <FullScreenLoader />
  if (status === 'guest') return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return <Outlet />
}

export function RequireGuest() {
  const { status, user } = useAuth()
  if (status === 'idle' || status === 'loading') return <FullScreenLoader />
  if (status === 'authenticated') return <Navigate to={user?.current_company ? '/app' : '/onboarding'} replace />
  return <Outlet />
}

/** Requires an operational company workspace; otherwise onboarding / suspension notice. */
export function RequireCompany() {
  const { user, logout } = useAuth()
  const company = user?.current_company
  if (!company) return <Navigate to="/onboarding" replace />
  if (company.status !== 'active') {
    return (
      <div className="grid min-h-dvh place-items-center p-6">
        <div className="ff-card w-full max-w-md">
          <EmptyState
            icon={<ShieldAlert />}
            title={`${company.name} is ${company.status}`}
            description={company.status === 'pending'
              ? 'Your workspace is awaiting platform approval. You will be notified once it is activated.'
              : 'This workspace has been suspended by the platform administrator. Contact support to restore access.'}
            action={<Button variant="outline" onClick={() => logout()}>Sign out</Button>}
          />
        </div>
      </div>
    )
  }
  return <Outlet />
}

export function RequirePermission({ anyOf, children }: { anyOf: string[]; children: ReactNode }) {
  const perms = useAuth((s) => s.user?.permissions ?? [])
  if (!anyOf.some((p) => perms.includes(p))) {
    return <EmptyState icon={<ShieldAlert />} title="No access" description="Your role in this company does not include this area." />
  }
  return <>{children}</>
}

export function RequireSuperAdmin() {
  const user = useAuth((s) => s.user)
  if (!user?.is_super_admin) return <Navigate to="/app" replace />
  return <Outlet />
}

/** Sends each user to their natural home: admin console, driver app or back office. */
export function HomeRedirect() {
  const { status, user } = useAuth()
  if (status === 'idle' || status === 'loading') return <FullScreenLoader />
  if (status === 'guest') return <Navigate to="/login" replace />
  if (!user?.current_company) return <Navigate to={user?.is_super_admin ? '/admin' : '/onboarding'} replace />
  if (user.roles.length === 1 && user.roles[0] === 'driver') return <Navigate to="/driver" replace />
  return <Navigate to="/app" replace />
}
