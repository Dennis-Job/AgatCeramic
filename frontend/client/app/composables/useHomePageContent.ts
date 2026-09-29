import { fetchHomePage } from '~/services/homePage'

export function useHomePageContent() {
  const config = useRuntimeConfig()
  const requestBase =
    import.meta.server && config.apiBaseInternal
      ? config.apiBaseInternal
      : config.public.apiBase

  return useAsyncData('home-page', () =>
    fetchHomePage(requestBase, config.public.apiBase),
  )
}
