const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'
const backendBaseUrl = apiBaseUrl.replace(/\/api\/v1\/?$/, '')

export type AdminUser = {
  id: number
  name: string
  email: string
  status: 'active' | 'blocked'
  last_login_at: string | null
  permissions?: string[]
}

type ApiError = {
  error?: {
    message?: string
    details?: Record<string, string[]>
  }
}

async function throwApiError(response: Response, fallback: string): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as ApiError
  const firstDetail = body.error?.details ? Object.values(body.error.details).flat()[0] : undefined
  throw new Error(firstDetail ?? body.error?.message ?? fallback)
}

export async function requestCsrfCookie(): Promise<void> {
  try {
    await fetch(`${backendBaseUrl}/sanctum/csrf-cookie`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  } catch {
    throw new Error('Не удалось подключиться к серверу. Попробуйте ещё раз.')
  }
}

function csrfToken(): string | undefined {
  const token = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
    ?.split('=')[1]

  return token ? decodeURIComponent(token) : undefined
}

export async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')

  if (init.method && !['GET', 'HEAD'].includes(init.method)) {
    headers.set('X-XSRF-TOKEN', csrfToken() ?? '')
  }

  try {
    return await fetch(`${apiBaseUrl}${path}`, {
      ...init,
      credentials: 'include',
      headers,
    })
  } catch {
    throw new Error('Не удалось подключиться к серверу. Попробуйте ещё раз.')
  }
}

export async function currentAdmin(): Promise<AdminUser> {
  const response = await apiFetch('/admin/auth/me')

  if (!response.ok) throw new Error('Unauthenticated')

  return ((await response.json()) as { data: AdminUser }).data
}

export async function updateCurrentAdmin(payload: { name?: string; email?: string; password?: string; password_confirmation?: string }): Promise<AdminUser> {
  await requestCsrfCookie()
  const response = await apiFetch('/admin/auth/me', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })

  if (!response.ok) return throwApiError(response, 'Не удалось сохранить профиль.')

  return ((await response.json()) as { data: AdminUser }).data
}

export async function login(email: string, password: string): Promise<void> {
  await requestCsrfCookie()

  const response = await apiFetch('/admin/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as ApiError
    throw new Error(body.error?.message ?? 'Не удалось выполнить вход.')
  }
}

export async function logout(): Promise<void> {
  await requestCsrfCookie()
  await apiFetch('/admin/auth/logout', { method: 'POST' })
}

export async function requestPasswordReset(email: string): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch('/admin/auth/forgot-password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })

  if (!response.ok) return throwApiError(response, 'Не удалось отправить ссылку для сброса пароля.')
}

export async function resetPassword(payload: { email: string; token: string; password: string; password_confirmation: string }): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch('/admin/auth/reset-password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })

  if (!response.ok) return throwApiError(response, 'Не удалось сбросить пароль.')
}
