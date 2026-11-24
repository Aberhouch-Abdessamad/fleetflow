import { api } from '@/lib/api'
import type { HealthReport } from '@/types/api'

export const systemApi = {
  health: () => api.get<HealthReport>('/health'),
}
