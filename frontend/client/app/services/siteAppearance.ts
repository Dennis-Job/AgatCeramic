import { mapSiteAppearance, type HomePageDto } from './homePage'

export async function fetchSiteAppearance(
  requestBase: string,
  publicBase: string,
) {
  const response = await $fetch<{
    data: Pick<HomePageDto, 'header' | 'footer'>
  }>(`${requestBase}/site-appearance`)
  return mapSiteAppearance(response.data, publicBase)
}
