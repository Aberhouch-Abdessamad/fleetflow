import { create } from 'zustand'
import { authApi, type LoginInput, type RegisterInput } from '@/features/auth/api/authApi'
import { onUnauthenticated, setActiveCompany } from '@/lib/http'
import type { User } from '@/types/api'

type Status = 'idle' | 'loading' | 'authenticated' | 'guest'

interface AuthState {
  user: User | null
  status: Status
  bootstrap: () => Promise<void>
  refresh: () => Promise<User | null>
  login: (input: LoginInput) => Promise<User | null>
  register: (input: RegisterInput) => Promise<User | null>
  logout: () => Promise<void>
  can: (permission: string) => boolean
  hasRole: (...roles: string[]) => boolean
}

export const useAuth = create<AuthState>((set, get) => ({
  user: null,
  status: 'idle',

  async bootstrap() {
    if (get().status !== 'idle') return
    set({ status: 'loading' })
    await get().refresh()
  },

  async refresh() {
    try {
      const user = await authApi.me()
      setActiveCompany(user.current_company?.id ?? null)
      set({ user, status: 'authenticated' })
      return user
    } catch {
      setActiveCompany(null)
      set({ user: null, status: 'guest' })
      return null
    }
  },

  async login(input) {
    await authApi.login(input)
    return get().refresh()
  },

  async register(input) {
    await authApi.register(input)
    return get().refresh()
  },

  async logout() {
    try {
      await authApi.logout()
    } finally {
      setActiveCompany(null)
      set({ user: null, status: 'guest' })
    }
  },

  can: (permission) => get().user?.permissions.includes(permission) ?? false,
  hasRole: (...roles) => roles.some((r) => get().user?.roles.includes(r as never)),
}))

// A 401 anywhere means the session is gone.
onUnauthenticated(() => {
  if (useAuth.getState().status === 'authenticated') useAuth.setState({ user: null, status: 'guest' })
})
