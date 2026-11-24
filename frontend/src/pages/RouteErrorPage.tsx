import { AlertTriangle, RotateCcw } from 'lucide-react'
import { isRouteErrorResponse, useRouteError } from 'react-router-dom'
import { Button, EmptyState } from '@/components/ui'

/** Friendly fallback for render errors anywhere in the route tree. */
export default function RouteErrorPage() {
  const err = useRouteError()
  const message = isRouteErrorResponse(err) ? `${err.status} ${err.statusText}` : err instanceof Error ? err.message : 'Unknown error'
  if (import.meta.env.DEV) console.error(err)
  return (
    <div className="grid min-h-dvh place-items-center p-6">
      <div className="ff-card w-full max-w-lg">
        <EmptyState
          icon={<AlertTriangle />}
          title="Something went wrong on this screen"
          description={import.meta.env.DEV ? message : 'An unexpected error occurred. Reload the page — if it keeps happening, contact support.'}
          action={<div className="flex gap-2"><Button onClick={() => window.location.reload()}><RotateCcw /> Reload</Button><Button variant="outline" onClick={() => { window.location.href = '/' }}>Go home</Button></div>}
        />
      </div>
    </div>
  )
}
