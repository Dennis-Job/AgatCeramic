import { apiData, apiFetch } from './api'

export type AdminUser = {
  id: number
  name: string
  email: string
  status: 'active' | 'blocked'
  last_login_at: string | null
  permissions?: string[]
}

export function currentAdmin(): Promise<AdminUser> {
  return apiData('/admin/auth/me', {
    errorMessage: 'Unauthenticated',
    errorMessageSource: 'fallback',
  })
}

export function updateCurrentAdmin(payload: {
  name?: string
  email?: string
  password?: string
  password_confirmation?: string
}): Promise<AdminUser> {
  return apiData('/admin/auth/me', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    errorMessage: 'Не удалось сохранить профиль.',
  })
}

export async function login(email: string, password: string): Promise<void> {
  await apiFetch('/admin/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
    errorMessage: 'Не удалось выполнить вход.',
    errorMessageSource: 'server',
  })
}

export async function logout(): Promise<void> {
  await apiFetch('/admin/auth/logout', {
    method: 'POST',
    errorMessage: 'Не удалось выйти. Попробуйте ещё раз.',
  })
}

export async function requestPasswordReset(email: string): Promise<void> {
  await apiFetch('/admin/auth/forgot-password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
    errorMessage: 'Не удалось отправить ссылку для сброса пароля.',
  })
}

export async function resetPassword(payload: {
  email: string
  token: string
  password: string
  password_confirmation: string
}): Promise<void> {
  await apiFetch('/admin/auth/reset-password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    errorMessage: 'Не удалось сбросить пароль.',
  })
}
