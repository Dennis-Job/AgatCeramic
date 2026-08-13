import { defineStore } from 'pinia'
import { currentAdmin, login, logout, type AdminUser } from '../services/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AdminUser | null,
    initialized: false,
  }),
  actions: {
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
  },
})
