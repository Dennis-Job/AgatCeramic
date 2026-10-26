import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import type {
  AppearanceSection,
  SiteAppearance,
} from '../types/appearance.types'

async function decode(response: Response): Promise<SiteAppearance> {
  const body = (await response.json().catch(() => ({}))) as {
    data?: SiteAppearance
    error?: { message?: string; details?: Record<string, string[]> }
  }
  if (!response.ok || !body.data)
    throw new Error(
      Object.values(body.error?.details ?? {}).flat()[0] ??
        body.error?.message ??
        'Не удалось загрузить общее оформление.',
    )
  return body.data
}

export async function getAppearance(): Promise<SiteAppearance> {
  return decode(await apiFetch('/admin/site-appearance'))
}

export async function saveAppearanceSection<K extends AppearanceSection>(
  section: K,
  value: SiteAppearance[K],
): Promise<SiteAppearance> {
  await requestCsrfCookie()
  const readOnlyKey = section === 'header' ? 'logo_url' : 'bottom_left'
  const sectionValue = Object.fromEntries(
    Object.entries(value).filter(([key]) => key !== readOnlyKey),
  )
  return decode(
    await apiFetch('/admin/site-appearance', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ [section]: sectionValue }),
    }),
  )
}

export async function publishAppearance(): Promise<SiteAppearance> {
  await requestCsrfCookie()
  return decode(
    await apiFetch('/admin/site-appearance/publish', { method: 'POST' }),
  )
}
