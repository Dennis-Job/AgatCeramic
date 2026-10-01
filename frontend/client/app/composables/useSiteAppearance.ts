import { fetchSiteAppearance } from '~/services/siteAppearance'

export function useSiteAppearance() {
  const { requestBase, publicBase } = usePublicApiConfig()
  return useAsyncData('site-appearance', () =>
    fetchSiteAppearance(requestBase, publicBase),
  )
}
