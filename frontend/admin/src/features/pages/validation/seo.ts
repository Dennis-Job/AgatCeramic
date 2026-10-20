import type { ContentPage } from '../types/page.types'

export function defaultPageSeo(title: string): NonNullable<ContentPage['seo']> {
  return {
    title,
    description: '',
    og_title: '',
    og_description: '',
    og_image_url: '',
    og_image_media_id: null,
  }
}
