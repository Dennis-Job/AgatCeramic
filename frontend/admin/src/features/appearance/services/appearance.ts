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
  const result = body.data
  result.header.menu_note =
    result.header.menu_note === undefined
      ? result.footer.tagline
      : (result.header.menu_note ?? '')
  const seen = new Set<string>()
  result.footer.navigation_links ??= result.header.navigation.flatMap((item) =>
    ('children' in item ? item.children : [item]).filter((link) => {
      if (seen.has(link.to)) return false
      seen.add(link.to)
      return true
    }),
  )
  result.footer.social_links ??= []
  result.footer.bottom_right_url ??= ''
  return result
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
