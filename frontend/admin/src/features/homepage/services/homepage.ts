import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import type { EditableSection, HomePageContent } from '../types/homepage.types'

async function decode(response: Response): Promise<HomePageContent> {
  const body = (await response.json().catch(() => ({}))) as {
    data?: HomePageContent
    error?: { message?: string; details?: Record<string, string[]> }
  }
  if (!response.ok || !body.data)
    throw new Error(
      Object.values(body.error?.details ?? {}).flat()[0] ??
        body.error?.message ??
        'Не удалось загрузить данные главной страницы.',
    )
  return body.data
}

export async function getHomePage(): Promise<HomePageContent> {
  return decode(await apiFetch('/admin/home-page'))
}

export async function publishHomePage(): Promise<HomePageContent> {
  await requestCsrfCookie()
  return decode(await apiFetch('/admin/home-page/publish', { method: 'POST' }))
}

export async function saveHomePageSection<K extends EditableSection>(
  section: K,
  value: HomePageContent[K],
): Promise<HomePageContent> {
  await requestCsrfCookie()
  return decode(
    await apiFetch('/admin/home-page', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ [section]: value }),
    }),
  )
}
