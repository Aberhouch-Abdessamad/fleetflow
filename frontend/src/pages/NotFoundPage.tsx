import { Compass } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button, EmptyState } from '@/components/ui'

export default function NotFoundPage({ inApp }: { inApp?: boolean }) {
  const body = (
    <EmptyState
      icon={<Compass />}
      title="This page took a wrong turn"
      description="The page you are looking for does not exist or has moved."
      action={<Button asChild><Link to={inApp ? '/app' : '/'}>Back to dashboard</Link></Button>}
    />
  )
  return inApp ? <div className="ff-card mt-6">{body}</div> : <div className="grid min-h-dvh place-items-center p-6"><div className="ff-card w-full max-w-lg">{body}</div></div>
}
