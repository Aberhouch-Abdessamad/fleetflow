import { lazy, Suspense, type ComponentType, type ReactNode } from 'react'
import RouteErrorPage from '@/pages/RouteErrorPage'
import { createBrowserRouter } from 'react-router-dom'
import { DriverLayout } from '@/features/driver-app/DriverApp'
import { AppLayout } from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'
import { FullScreenLoader, HomeRedirect, RequireAuth, RequireCompany, RequireGuest, RequirePermission, RequireSuperAdmin } from './guards'

const page = (f: () => Promise<{ default: ComponentType }>) => lazy(f)
const named = <K extends string>(f: () => Promise<Record<K, ComponentType>>, k: K) => lazy(() => f().then((m) => ({ default: m[k] })))

const LoginPage = page(() => import('@/features/auth/pages/LoginPage'))
const RegisterPage = page(() => import('@/features/auth/pages/RegisterPage'))
const ForgotPasswordPage = page(() => import('@/features/auth/pages/ForgotPasswordPage'))
const ResetPasswordPage = page(() => import('@/features/auth/pages/ResetPasswordPage'))
const VerifyEmailPage = page(() => import('@/features/auth/pages/VerifyEmailPage'))
const AcceptInvitationPage = page(() => import('@/features/auth/pages/AcceptInvitationPage'))
const OnboardingPage = page(() => import('@/features/onboarding/pages/OnboardingPage'))
const TrackingPage = page(() => import('@/features/public/pages/TrackingPage'))
const SystemStatusPage = page(() => import('@/features/system/pages/SystemStatusPage'))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'))

const DashboardPage = page(() => import('@/features/dashboard/pages/DashboardPage'))
const FleetOverviewPage = page(() => import('@/features/fleet/pages/FleetOverviewPage'))
const VehiclesPage = page(() => import('@/features/vehicles/pages/VehiclesPage'))
const VehicleDetailPage = page(() => import('@/features/vehicles/pages/VehicleDetailPage'))
const DriversPage = page(() => import('@/features/drivers/pages/DriversPage'))
const DriverDetailPage = page(() => import('@/features/drivers/pages/DriverDetailPage'))
const CustomersPage = page(() => import('@/features/customers/pages/CustomersPage'))
const CustomerDetailPage = page(() => import('@/features/customers/pages/CustomerDetailPage'))
const DeliveriesPage = page(() => import('@/features/deliveries/pages/DeliveriesPage'))
const DeliveryFormPage = page(() => import('@/features/deliveries/pages/DeliveryFormPage'))
const DeliveryDetailPage = page(() => import('@/features/deliveries/pages/DeliveryDetailPage'))
const DispatchBoardPage = page(() => import('@/features/dispatch/pages/DispatchBoardPage'))
const LiveTrackingPage = page(() => import('@/features/tracking/pages/LiveTrackingPage'))
const RoutesPage = page(() => import('@/features/routes/pages/RoutesPage'))
const RoutePlannerPage = page(() => import('@/features/routes/pages/RoutePlannerPage'))
const MaintenancePage = page(() => import('@/features/maintenance/pages/MaintenancePage'))
const FuelPage = page(() => import('@/features/fuel/pages/FuelPage'))
const ExpensesPage = page(() => import('@/features/expenses/pages/ExpensesPage'))
const InvoicesPage = page(() => import('@/features/invoices/pages/InvoicesPage'))
const InvoiceDetailPage = page(() => import('@/features/invoices/pages/InvoiceDetailPage'))
const ReportsPage = page(() => import('@/features/reports/pages/ReportsPage'))
const TeamPage = page(() => import('@/features/team/pages/TeamPage'))
const SettingsPage = page(() => import('@/features/settings/pages/SettingsPage'))
const NotificationsPage = page(() => import('@/features/notifications/pages/NotificationsPage'))
const ProfilePage = page(() => import('@/features/profile/pages/ProfilePage'))

const DriverHomePage = page(() => import('@/features/driver-app/pages/DriverHomePage'))
const DriverDeliveryPage = page(() => import('@/features/driver-app/pages/DriverDeliveryPage'))
const DriverHistoryPage = page(() => import('@/features/driver-app/pages/DriverHistoryPage'))
const DriverExpensesPage = page(() => import('@/features/driver-app/pages/DriverExpensesPage'))

const admin = () => import('@/features/admin/pages/AdminPages')
const AdminDashboardPage = named(admin, 'AdminDashboardPage')
const AdminCompaniesPage = named(admin, 'AdminCompaniesPage')
const AdminCompanyPage = named(admin, 'AdminCompanyPage')
const AdminUsersPage = named(admin, 'AdminUsersPage')
const AdminPlansPage = named(admin, 'AdminPlansPage')
const AdminSubscriptionsPage = named(admin, 'AdminSubscriptionsPage')
const AdminAnalyticsPage = named(admin, 'AdminAnalyticsPage')
const AdminActivityPage = named(admin, 'AdminActivityPage')
const AdminSettingsPage = named(admin, 'AdminSettingsPage')

const s = (node: ReactNode) => <Suspense fallback={<FullScreenLoader />}>{node}</Suspense>
const p = (anyOf: string[], node: ReactNode) => s(<RequirePermission anyOf={anyOf}>{node}</RequirePermission>)

export const router = createBrowserRouter([{
  errorElement: <RouteErrorPage />,
  children: [
  { path: '/', element: <HomeRedirect /> },
  { path: '/system/status', element: s(<SystemStatusPage />) },
  { path: '/track/:token', element: s(<TrackingPage />) },
  { path: '/verify-email', element: s(<VerifyEmailPage />) },
  { path: '/invitations/:token', element: s(<AcceptInvitationPage />) },
  {
    element: <RequireGuest />,
    children: [{ element: <AuthLayout />, children: [
      { path: '/login', element: s(<LoginPage />) },
      { path: '/register', element: s(<RegisterPage />) },
      { path: '/forgot-password', element: s(<ForgotPasswordPage />) },
      { path: '/reset-password', element: s(<ResetPasswordPage />) },
    ] }],
  },
  {
    element: <RequireAuth />,
    children: [
      { path: '/onboarding', element: s(<OnboardingPage />) },
      {
        path: '/admin',
        element: <RequireSuperAdmin />,
        children: [{
          element: <AppLayout variant="admin" />,
          children: [
            { index: true, element: s(<AdminDashboardPage />) },
            { path: 'companies', element: s(<AdminCompaniesPage />) },
            { path: 'companies/:id', element: s(<AdminCompanyPage />) },
            { path: 'users', element: s(<AdminUsersPage />) },
            { path: 'plans', element: s(<AdminPlansPage />) },
            { path: 'subscriptions', element: s(<AdminSubscriptionsPage />) },
            { path: 'analytics', element: s(<AdminAnalyticsPage />) },
            { path: 'activity', element: s(<AdminActivityPage />) },
            { path: 'settings', element: s(<AdminSettingsPage />) },
          ],
        }],
      },
      {
        element: <RequireCompany />,
        children: [
          {
            path: '/driver',
            element: <DriverLayout />,
            children: [
              { index: true, element: s(<DriverHomePage />) },
              { path: 'deliveries/:id', element: s(<DriverDeliveryPage />) },
              { path: 'history', element: s(<DriverHistoryPage />) },
              { path: 'expenses', element: s(<DriverExpensesPage />) },
            ],
          },
          {
            path: '/app',
            element: <AppLayout />,
            children: [
              { index: true, element: s(<DashboardPage />) },
              { path: 'fleet', element: p(['vehicles.view'], <FleetOverviewPage />) },
              { path: 'vehicles', element: p(['vehicles.view'], <VehiclesPage />) },
              { path: 'vehicles/:id', element: p(['vehicles.view'], <VehicleDetailPage />) },
              { path: 'drivers', element: p(['drivers.view'], <DriversPage />) },
              { path: 'drivers/:id', element: p(['drivers.view'], <DriverDetailPage />) },
              { path: 'customers', element: p(['customers.view'], <CustomersPage />) },
              { path: 'customers/:id', element: p(['customers.view'], <CustomerDetailPage />) },
              { path: 'deliveries', element: p(['deliveries.view', 'deliveries.view_own'], <DeliveriesPage />) },
              { path: 'deliveries/new', element: p(['deliveries.create'], <DeliveryFormPage />) },
              { path: 'deliveries/:id', element: p(['deliveries.view', 'deliveries.view_own'], <DeliveryDetailPage />) },
              { path: 'deliveries/:id/edit', element: p(['deliveries.update'], <DeliveryFormPage />) },
              { path: 'dispatch', element: p(['dispatch.board'], <DispatchBoardPage />) },
              { path: 'tracking', element: p(['tracking.view'], <LiveTrackingPage />) },
              { path: 'routes', element: p(['routes.view'], <RoutesPage />) },
              { path: 'routes/:id', element: p(['routes.view'], <RoutePlannerPage />) },
              { path: 'maintenance', element: p(['maintenance.view'], <MaintenancePage />) },
              { path: 'fuel', element: p(['fuel.view', 'fuel.create_own'], <FuelPage />) },
              { path: 'expenses', element: p(['expenses.view', 'expenses.create_own'], <ExpensesPage />) },
              { path: 'invoices', element: p(['invoices.view'], <InvoicesPage />) },
              { path: 'invoices/:id', element: p(['invoices.view'], <InvoiceDetailPage />) },
              { path: 'reports', element: p(['reports.fleet', 'reports.deliveries', 'reports.drivers', 'reports.financial'], <ReportsPage />) },
              { path: 'team', element: p(['users.view'], <TeamPage />) },
              { path: 'settings', element: p(['company.view'], <SettingsPage />) },
              { path: 'notifications', element: s(<NotificationsPage />) },
              { path: 'profile', element: s(<ProfilePage />) },
              { path: '*', element: s(<NotFoundPage inApp />) },
            ],
          },
        ],
      },
    ],
  },
  { path: '*', element: s(<NotFoundPage />) },
]}])
