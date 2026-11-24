import { Save } from 'lucide-react'
import { toast } from 'sonner'
import { Button, Card, PageHeader, Skeleton } from '@/components/ui'
import { companyApi } from '@/features/auth/api/authApi'
import { CompanyForm } from '@/features/onboarding/components/CompanyForm'
import { useAsync } from '@/hooks/useAsync'
import { useAuth } from '@/stores/auth'

export default function CompanySettingsPage() {
  const refresh = useAuth((s) => s.refresh)
  const { data: company, loading, error, reload } = useAsync(() => companyApi.current(), [])

  return (
    <>
      <PageHeader eyebrow="Workspace" title="Company settings" description="Profile, location and regional preferences for this workspace." />
      <Card className="p-6 sm:p-8">
        {loading ? (
          <div className="space-y-4">{[...Array(6)].map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
        ) : error ? (
          <div className="text-sm text-danger">{error.message} <Button variant="link" onClick={reload}>Retry</Button></div>
        ) : company && (
          <CompanyForm
            defaultValues={company}
            onSubmit={async (values) => {
              await companyApi.update(values)
              await refresh()
              toast.success('Company settings saved')
            }}
            footer={({ isSubmitting, isDirty }) => (
              <div className="mt-8 flex justify-end border-t border-border pt-6">
                <Button type="submit" loading={isSubmitting} disabled={!isDirty}><Save /> Save changes</Button>
              </div>
            )}
          />
        )}
      </Card>
    </>
  )
}
