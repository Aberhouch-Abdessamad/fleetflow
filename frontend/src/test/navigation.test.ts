import { companyNavigation, filterNavigation } from '@/app/navigation'

const labels = (perms: string[]) => filterNavigation(companyNavigation, perms).flatMap((g) => g.items.map((i) => i.label))

describe('permission-based navigation', () => {
  it('shows a driver only what their role allows', () => {
    const items = labels(['company.view', 'deliveries.view_own', 'tracking.report_location'])
    expect(items).toContain('Dashboard')
    expect(items).toContain('Deliveries')
    expect(items).not.toContain('Vehicles')
    expect(items).not.toContain('Company Settings')
    expect(items).not.toContain('Invoices')
  })

  it('shows an accountant finance areas but not dispatch', () => {
    const items = labels(['expenses.view', 'invoices.view', 'reports.financial'])
    expect(items).toEqual(expect.arrayContaining(['Expenses', 'Invoices', 'Reports']))
    expect(items).not.toContain('Dispatch Board')
  })

  it('drops empty groups', () => {
    const groups = filterNavigation(companyNavigation, []).map((g) => g.label)
    expect(groups).toEqual(['Overview'])
  })
})
