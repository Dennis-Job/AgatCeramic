import { fetchHomePage } from '~/services/homePage'

export function useHomePageContent() {
  const { requestBase, publicBase } = usePublicApiConfig()

  return useAsyncData('home-page', () => fetchHomePage(requestBase, publicBase))
}
