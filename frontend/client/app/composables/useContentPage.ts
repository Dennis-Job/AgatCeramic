import { fetchContentPage } from '~/services/contentPages'

export function useContentPage(slug: string) {
  const { requestBase, publicBase } = usePublicApiConfig()
  return useAsyncData(`content-page:${slug}`, () =>
    fetchContentPage(slug, requestBase, publicBase),
  )
}
