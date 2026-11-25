import { apiFetch } from '../../../services/api'
import type { EditableSection, HomePageContent } from '../types/homepage.types'

const errorMessage = 'Не удалось загрузить данные главной страницы.'

async function decode(response: Response): Promise<HomePageContent> {
  const body = (await response.json().catch(() => null)) as {
    data?: HomePageContent
  } | null
  if (!body?.data) throw new Error(errorMessage)
  return body.data
}

export async function getHomePage(): Promise<HomePageContent> {
  return decode(await apiFetch('/admin/home-page', { errorMessage }))
}

export async function publishHomePage(): Promise<HomePageContent> {
  return decode(
    await apiFetch('/admin/home-page/publish', {
      method: 'POST',
      errorMessage,
    }),
  )
}

export async function saveHomePageSection<K extends EditableSection>(
  section: K,
  value: HomePageContent[K],
): Promise<HomePageContent> {
  return decode(
    await apiFetch('/admin/home-page', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ [section]: value }),
      errorMessage,
    }),
  )
}
