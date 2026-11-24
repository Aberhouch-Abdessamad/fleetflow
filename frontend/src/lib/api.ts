import type { ApiEnvelope } from '@/types/api'
import { http } from './http'

/** Typed helpers that unwrap the { success, data, meta } envelope. */
export const api = {
  async get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
    return (await http.get<ApiEnvelope<T>>(url, { params })).data.data
  },
  async post<T>(url: string, body?: unknown): Promise<T> {
    return (await http.post<ApiEnvelope<T>>(url, body)).data.data
  },
  async put<T>(url: string, body?: unknown): Promise<T> {
    return (await http.put<ApiEnvelope<T>>(url, body)).data.data
  },
  async patch<T>(url: string, body?: unknown): Promise<T> {
    return (await http.patch<ApiEnvelope<T>>(url, body)).data.data
  },
  async delete<T>(url: string): Promise<T> {
    return (await http.delete<ApiEnvelope<T>>(url)).data.data
  },
}
