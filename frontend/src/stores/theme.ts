import { create } from 'zustand'

type Theme = 'light' | 'dark'

function read(): Theme {
  try {
    const t = localStorage.getItem('ff-theme')
    if (t === 'light' || t === 'dark') return t
  } catch { /* storage unavailable */ }
  return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export const useTheme = create<{ theme: Theme; toggle: () => void }>((set, get) => ({
  theme: read(),
  toggle() {
    const theme: Theme = get().theme === 'dark' ? 'light' : 'dark'
    document.documentElement.classList.toggle('dark', theme === 'dark')
    try { localStorage.setItem('ff-theme', theme) } catch { /* ignore */ }
    set({ theme })
  },
}))
