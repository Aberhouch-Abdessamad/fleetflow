import { create } from 'zustand'
import { api } from '@/lib/api'
import type { AppNotification } from '@/types/domain'

interface State {
  unread: number
  items: AppNotification[]
  loading: boolean
  refreshCount: () => Promise<void>
  load: () => Promise<void>
  markRead: (id: string) => Promise<void>
  markAll: () => Promise<void>
}

export const useNotifications = create<State>((set, get) => ({
  unread: 0,
  items: [],
  loading: false,
  async refreshCount() {
    try {
      const r = await api.get<{ unread: number }>('/notifications/unread-count')
      set({ unread: r.unread })
    } catch { /* offline or signed out */ }
  },
  async load() {
    set({ loading: true })
    try {
      const r = await api.page<AppNotification>('/notifications', { per_page: 8 })
      set({ items: r.data, unread: Number(r.meta.unread ?? 0) })
    } finally {
      set({ loading: false })
    }
  },
  async markRead(id) {
    await api.post(`/notifications/${id}/read`)
    set({ items: get().items.map((n) => (n.id === id ? { ...n, read: true } : n)), unread: Math.max(0, get().unread - 1) })
  },
  async markAll() {
    await api.post('/notifications/read-all')
    set({ items: get().items.map((n) => ({ ...n, read: true })), unread: 0 })
  },
}))
