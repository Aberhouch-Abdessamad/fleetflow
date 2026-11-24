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
  /** Optional badge key (e.g. unread counts). */
  badge?: string
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
      { label: 'Deliveries', to: '/app/deliveries', icon: Package, anyOf: ['deliveries.view', 'deliveries.view_own'] },
      { label: 'Dispatch Board', to: '/app/dispatch', icon: Workflow, anyOf: ['dispatch.board'] },
      { label: 'Live Tracking', to: '/app/tracking', icon: MapPinned, anyOf: ['tracking.view'] },
      { label: 'Routes', to: '/app/routes', icon: RouteIcon, anyOf: ['routes.view'] },
      { label: 'Customers', to: '/app/customers', icon: Users, anyOf: ['customers.view'] },
    ],
  },
  {
    label: 'Fleet',
    items: [
      { label: 'Fleet Overview', to: '/app/fleet', icon: CarFront, anyOf: ['vehicles.view'] },
      { label: 'Vehicles', to: '/app/vehicles', icon: Truck, anyOf: ['vehicles.view'] },
      { label: 'Drivers', to: '/app/drivers', icon: UserRound, anyOf: ['drivers.view'] },
      { label: 'Maintenance', to: '/app/maintenance', icon: Wrench, anyOf: ['maintenance.view'] },
      { label: 'Fuel', to: '/app/fuel', icon: Fuel, anyOf: ['fuel.view'] },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Expenses', to: '/app/expenses', icon: Wallet, anyOf: ['expenses.view'] },
      { label: 'Invoices', to: '/app/invoices', icon: Receipt, anyOf: ['invoices.view'] },
      { label: 'Reports', to: '/app/reports', icon: BarChart3, anyOf: ['reports.fleet', 'reports.deliveries', 'reports.drivers', 'reports.financial'] },
    ],
  },
  {
    label: 'Workspace',
    items: [
      { label: 'Team', to: '/app/team', icon: ShieldCheck, anyOf: ['users.view'] },
      { label: 'Settings', to: '/app/settings', icon: Settings, anyOf: ['company.view'] },
    ],
  },
]

export const adminNavigation: NavGroup[] = [
  {
    label: 'Platform',
    items: [
      { label: 'Dashboard', to: '/admin', icon: LayoutDashboard },
      { label: 'Companies', to: '/admin/companies', icon: Building2 },
      { label: 'Users', to: '/admin/users', icon: Users },
      { label: 'Plans', to: '/admin/plans', icon: Receipt },
      { label: 'Subscriptions', to: '/admin/subscriptions', icon: Wallet },
    ],
  },
  {
    label: 'System',
    items: [
      { label: 'Analytics', to: '/admin/analytics', icon: BarChart3 },
      { label: 'Activity Logs', to: '/admin/activity', icon: Workflow },
      { label: 'Settings', to: '/admin/settings', icon: Settings },
    ],
  },
]

export function filterNavigation(groups: NavGroup[], permissions: string[]): NavGroup[] {
  return groups
    .map((g) => ({ ...g, items: g.items.filter((i) => !i.anyOf || i.anyOf.some((p) => permissions.includes(p))) }))
    .filter((g) => g.items.length > 0)
}
