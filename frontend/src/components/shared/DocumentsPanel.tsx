import { FileText, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Badge, Button, ConfirmDialog, DataTable, Field, FormModal, Input, Select, type Column } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api, fileUrl } from '@/lib/api'
import { date, label } from '@/lib/format'
import { toApiError } from '@/lib/http'
import type { DocumentRecord } from '@/types/domain'

const TYPES = ['insurance', 'registration', 'inspection', 'permit', 'license', 'medical', 'contract', 'id_card', 'other']

export function DocumentsPanel({ url, canManage, deletable }: { url: string; canManage: boolean; deletable?: boolean }) {
  const docs = useAsync(() => api.get<DocumentRecord[]>(url), [url])
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [form, setForm] = useState({ document_type: 'insurance', document_number: '', issue_date: '', expiry_date: '' })
  const [file, setFile] = useState<File | null>(null)

  const columns: Column<DocumentRecord>[] = [
    { key: 'type', header: 'Document', cell: (d) => <div className="flex items-center gap-2.5"><FileText className="size-4 text-subtle" /><div><p className="font-semibold text-fg">{label(d.document_type)}</p><p className="text-xs text-muted">{d.document_number ?? d.original_name ?? '—'}</p></div></div> },
    { key: 'issued', header: 'Issued', cell: (d) => date(d.issue_date), hideOnMobile: true },
    { key: 'expiry', header: 'Expires', cell: (d) => date(d.expiry_date) },
    { key: 'status', header: 'Status', cell: (d) => <Badge dot tone={d.status === 'expired' ? 'danger' : d.status === 'expiring' ? 'warning' : 'success'}>{label(d.status)}</Badge> },
    { key: 'file', header: '', className: 'text-right', cell: (d) => (
      <div className="flex justify-end gap-1">
        {d.file_url && <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); window.open(fileUrl(d.file_url)!, '_blank', 'noopener') }}>View</Button>}
        {deletable && canManage && <ConfirmDialog title="Remove document?" confirmLabel="Remove" onConfirm={async () => { await api.delete(`${url}/${d.id}`); void docs.reload() }} trigger={<Button size="icon-sm" variant="ghost" aria-label="Remove"><Trash2 /></Button>} />}
      </div>
    ) },
  ]

  return (
    <div>
      {canManage && <div className="flex justify-end border-b border-border p-3"><Button size="sm" onClick={() => setOpen(true)}><Plus /> Upload document</Button></div>}
      <DataTable columns={columns} rows={docs.data ?? []} loading={docs.loading} />
      <FormModal open={open} onOpenChange={setOpen} title="Upload document" size="md" submitting={busy} submitLabel="Upload" onSubmit={async (e) => {
        e.preventDefault()
        setBusy(true)
        try {
          await api.upload(url, { ...form, file })
          toast.success('Document uploaded')
          setOpen(false)
          setFile(null)
          void docs.reload()
        } catch (err) {
          toast.error(toApiError(err).message)
        } finally { setBusy(false) }
      }}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Type" required><Select value={form.document_type} onChange={(e) => setForm({ ...form, document_type: e.target.value })}>{TYPES.map((t) => <option key={t} value={t}>{label(t)}</option>)}</Select></Field>
          <Field label="Number"><Input value={form.document_number} onChange={(e) => setForm({ ...form, document_number: e.target.value })} /></Field>
          <Field label="Issue date"><Input type="date" value={form.issue_date} onChange={(e) => setForm({ ...form, issue_date: e.target.value })} /></Field>
          <Field label="Expiry date"><Input type="date" value={form.expiry_date} onChange={(e) => setForm({ ...form, expiry_date: e.target.value })} /></Field>
          <Field label="File" hint="PDF or image, up to 10 MB" className="sm:col-span-2"><Input type="file" accept="application/pdf,image/*" className="pt-1.5" onChange={(e) => setFile(e.target.files?.[0] ?? null)} /></Field>
        </div>
      </FormModal>
    </div>
  )
}
