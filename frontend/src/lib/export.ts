import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import { label } from './format'

/** Client-side PDF export of report rows (same JSON the API returns; no fabricated data). */
export function exportPdf(title: string, rows: Record<string, unknown>[], columns?: string[], subtitle?: string) {
  const cols = columns ?? (rows[0] ? Object.keys(rows[0]) : [])
  const doc = new jsPDF({ orientation: cols.length > 6 ? 'landscape' : 'portrait', unit: 'pt' })
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(16)
  doc.text(title, 40, 44)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  doc.setTextColor(110)
  doc.text(subtitle ?? `Generated ${new Date().toLocaleString()} · FleetFlow`, 40, 60)
  autoTable(doc, {
    startY: 74,
    head: [cols.map((c) => label(c))],
    body: rows.map((r) => cols.map((c) => {
      const v = r[c]
      if (v === null || v === undefined) return '—'
      if (typeof v === 'boolean') return v ? 'Yes' : 'No'
      if (typeof v === 'object') return JSON.stringify(v)
      return String(v)
    })),
    styles: { fontSize: 8, cellPadding: 4 },
    headStyles: { fillColor: [11, 21, 48] },
    alternateRowStyles: { fillColor: [244, 246, 251] },
  })
  doc.save(`${title.toLowerCase().replace(/\s+/g, '-')}.pdf`)
}
