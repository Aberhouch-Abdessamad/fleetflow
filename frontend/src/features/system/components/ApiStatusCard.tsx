import type { ReactNode } from 'react'
import { Activity, Database, Map, RefreshCw, Server } from 'lucide-react'
import { Badge, Button, Card, CardBody, CardHeader, Skeleton } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { systemApi } from '../api/systemApi'

/** Live SPA → API → database round-trip. */
export function ApiStatusCard() {
  const { data, error, loading, reload } = useAsync(() => systemApi.health(), [])
  return (
    <Card>
      <CardHeader
        icon={<Activity />}
        title="API connection"
        description="Live round-trip to the API and database"
        action={<Button variant="ghost" size="icon-sm" onClick={reload} aria-label="Re-check"><RefreshCw className={loading ? 'animate-spin' : ''} /></Button>}
      />
      <CardBody className="space-y-3.5">
        {loading && !data ? (
          <><Skeleton className="h-5" /><Skeleton className="h-5 w-2/3" /><Skeleton className="h-5 w-1/2" /></>
        ) : error ? (
          <div className="flex items-center justify-between rounded-lg bg-danger-soft px-3 py-2.5 text-sm text-danger">
            <span>{error.message}</span><Badge tone="danger">Offline</Badge>
          </div>
        ) : data && (
          <>
            <Row icon={<Server />} label={`REST API ${data.api_version}`} value={data.environment} tone="success" />
            <Row icon={<Database />} label={`Database · ${data.database.driver}`} value={data.database.ok ? `${data.database.latency_ms} ms` : 'unreachable'} tone={data.database.ok ? 'success' : 'danger'} />
            <Row icon={<Map />} label="Maps provider" value={data.maps_provider ?? 'Not configured'} tone={data.maps_provider ? 'success' : 'warning'} />
          </>
        )}
      </CardBody>
    </Card>
  )
}

function Row({ icon, label, value, tone }: { icon: ReactNode; label: string; value: string; tone: 'success' | 'danger' | 'warning' }) {
  return (
    <div className="flex items-center gap-3 text-sm">
      <span className="text-subtle [&_svg]:size-4">{icon}</span>
      <span className="flex-1 text-fg-soft">{label}</span>
      <Badge dot tone={tone}>{value}</Badge>
    </div>
  )
}
