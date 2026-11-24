import { Moon, Sun } from 'lucide-react'
import { Button } from '@/components/ui'
import { useTheme } from '@/stores/theme'

export function ThemeToggle() {
  const { theme, toggle } = useTheme()
  return (
    <Button variant="ghost" size="icon" onClick={toggle} aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}>
      {theme === 'dark' ? <Sun /> : <Moon />}
    </Button>
  )
}
