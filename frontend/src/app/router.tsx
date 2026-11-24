import { lazy, Suspense, type ReactNode } from 'react'
import { createBrowserRouter, Navigate } from 'react-router-dom'
import { AuthLayout } from '@/layouts/AuthLayout'
import { AppLayout } from '@/layouts/AppLayout'
import { FullScreenLoader, RequireAuth, RequireCompany, RequireGuest, RequirePermission } from './guards'

const LoginPage = lazy(() => import('@/features/auth/pages/LoginPage'))
const RegisterPage = lazy(() => import('@/features/auth/pages/RegisterPage'))
const OnboardingPage = lazy(() => import('@/features/onboarding/pages/OnboardingPage'))
const DashboardPage = lazy(() => import('@/features/dashboard/pages/DashboardPage'))
const CompanySettingsPage = lazy(() => import('@/features/settings/pages/CompanySettingsPage'))
const SystemStatusPage = lazy(() => import('@/features/system/pages/SystemStatusPage'))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'))

const s = (node: ReactNode) => <Suspense fallback={<FullScreenLoader />}>{node}</Suspense>

export const router = createBrowserRouter([
  { path: '/', element: <Navigate to="/app" replace /> },
  { path: '/system/status', element: s(<SystemStatusPage />) },
  {
    element: <RequireGuest />,
    children: [{ element: <AuthLayout />, children: [
      { path: '/login', element: s(<LoginPage />) },
      { path: '/register', element: s(<RegisterPage />) },
    ] }],
  },
  {
    element: <RequireAuth />,
    children: [
      { path: '/onboarding', element: s(<OnboardingPage />) },
      {
        element: <RequireCompany />,
        children: [{
          path: '/app',
          element: <AppLayout />,
          children: [
            { index: true, element: s(<DashboardPage />) },
            { path: 'settings', element: s(<RequirePermission anyOf={['company.update']}><CompanySettingsPage /></RequirePermission>) },
            { path: '*', element: s(<NotFoundPage inApp />) },
          ],
        }],
      },
    ],
  },
  { path: '*', element: s(<NotFoundPage />) },
])
