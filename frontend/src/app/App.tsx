import { useEffect } from 'react'
import { RouterProvider } from 'react-router-dom'
import { Toaster } from 'sonner'
import { TooltipProvider } from '@/components/ui'
import { useAuth } from '@/stores/auth'
import { useTheme } from '@/stores/theme'
import { router } from './router'

export function App() {
  const bootstrap = useAuth((s) => s.bootstrap)
  const theme = useTheme((s) => s.theme)
  useEffect(() => { void bootstrap() }, [bootstrap])

  return (
    <TooltipProvider>
      <RouterProvider router={router} />
      <Toaster
        theme={theme}
        position="top-right"
        richColors
        closeButton
        toastOptions={{ classNames: { toast: '!rounded-xl !border-border !shadow-pop !font-sans' } }}
      />
    </TooltipProvider>
  )
}
