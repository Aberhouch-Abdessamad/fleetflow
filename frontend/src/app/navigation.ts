import {
  BarChart3, Building2, CarFront, Fuel, LayoutDashboard, MapPinned, Package, Receipt, Route as RouteIcon,
  Settings, ShieldCheck, Truck, UserRound, Users, Wallet, Workflow, Wrench, type LucideIcon,
} from 'lucide-react'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  /** Shown only when the user holds at least one of these permissions in the current company. */
  anyOf?: string[]
  /** Module not built yet — rendered disabled with its delivery phase. */
  phase?: number
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

export const companyNavigation: NavGroup[] = [
  {
    label: 'Overview',
    items: [{ label: 'Dashboard', to: '/app', icon: LayoutDashboard }],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Deliveries', to: '/app/deliveries', icon: Package, anyOf: ['deliveries.view', 'deliveries.view_own'], phase: 6 },
      { label: 'Dispatch Board', to: '/app/dispatch', icon: Workflow, anyOf: ['dispatch.board'], phase: 7 },
      { label: 'Live Tracking', to: '/app/tracking', icon: MapPinned, anyOf: ['tracking.view'], phase: 8 },
      { label: 'Routes', to: '/app/routes', icon: RouteIcon, anyOf: ['routes.view'], phase: 7 },
      { label: 'Customers', to: '/app/customers', icon: Users, anyOf: ['customers.view'], phase: 6 },
    ],
  },
  {
    label: 'Fleet',
    items: [
      { label: 'Vehicles', to: '/app/vehicles', icon: Truck, anyOf: ['vehicles.view'], phase: 4 },
      { label: 'Fleet Overview', to: '/app/fleet', icon: CarFront, anyOf: ['vehicles.view'], phase: 4 },
      { label: 'Drivers', to: '/app/drivers', icon: UserRound, anyOf: ['drivers.view'], phase: 5 },
      { label: 'Maintenance', to: '/app/maintenance', icon: Wrench, anyOf: ['maintenance.view'], phase: 10 },
      { label: 'Fuel', to: '/app/fuel', icon: Fuel, anyOf: ['fuel.view'], phase: 10 },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Expenses', to: '/app/expenses', icon: Wallet, anyOf: ['expenses.view'], phase: 10 },
      { label: 'Invoices', to: '/app/invoices', icon: Receipt, anyOf: ['invoices.view'], phase: 11 },
      { label: 'Reports', to: '/app/reports', icon: BarChart3, anyOf: ['reports.fleet', 'reports.deliveries', 'reports.drivers', 'reports.financial'], phase: 12 },
    ],
  },
  {
    label: 'Workspace',
    items: [
      { label: 'Team', to: '/app/team', icon: ShieldCheck, anyOf: ['users.view'], phase: 3 },
      { label: 'Company Settings', to: '/app/settings', icon: Settings, anyOf: ['company.update'] },
    ],
  },
]

export const adminNavigation: NavGroup[] = [
  { label: 'Platform', items: [{ label: 'Admin Dashboard', to: '/admin', icon: Building2, phase: 13 }] },
]

export function filterNavigation(groups: NavGroup[], permissions: string[]): NavGroup[] {
  return groups
    .map((g) => ({ ...g, items: g.items.filter((i) => !i.anyOf || i.anyOf.some((p) => permissions.includes(p))) }))
    .filter((g) => g.items.length > 0)
}
