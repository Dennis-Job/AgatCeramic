import { defineStore } from 'pinia'
import { currentAdmin, login, logout, updateCurrentAdmin, type AdminUser } from '../services/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AdminUser | null,
    initialized: false,
  }),
  actions: {
    hasPermission(permission: string): boolean {
      return this.user?.permissions?.includes(permission) ?? false
    },
    async restoreSession(): Promise<boolean> {
      try {
        this.user = await currentAdmin()
        return true
      } catch {
        this.user = null
        return false
      } finally {
        this.initialized = true
      }
    },
    async login(email: string, password: string): Promise<void> {
      await login(email, password)
      this.user = await currentAdmin()
      this.initialized = true
    },
    async logout(): Promise<void> {
      await logout()
      this.user = null
    },
    async updateProfile(payload: { name?: string; email?: string; password?: string; password_confirmation?: string }): Promise<void> {
      this.user = await updateCurrentAdmin(payload)
    },
  },
})
