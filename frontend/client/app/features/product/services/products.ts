import { imageUrl } from '~/services/homePage'
import type { ProductCard, ProductDetail } from '../types/product'
function mapCard<T extends ProductCard>(product: T, publicBase: string): T {
  return {
    ...product,
    image_url: imageUrl(product.image_url, publicBase) || null,
  }
}
export async function fetchProduct(
  slug: string,
  requestBase: string,
  publicBase: string,
): Promise<ProductDetail> {
  const endpoint = new URL(
    'products/' + encodeURIComponent(slug),
    requestBase.endsWith('/') ? requestBase : requestBase + '/',
  )
  const { data } = await $fetch<{ data: ProductDetail }>(endpoint.toString(), {
    retry: 0,
  })
  return {
    ...mapCard(data, publicBase),
    brand: data.brand
      ? {
          ...data.brand,
          logo_url: imageUrl(data.brand.logo_url, publicBase) || null,
        }
      : null,
    images: data.images
      .map((image) => ({ ...image, url: imageUrl(image.url, publicBase) }))
      .filter((image) => image.url),
    group: data.group
      ? {
          ...data.group,
          products: data.group.products.map((product) =>
            mapCard(product, publicBase),
          ),
        }
      : null,
    related: data.related.map((product) => mapCard(product, publicBase)),
    recommended: data.recommended.map((product) =>
      mapCard(product, publicBase),
    ),
  }
}
export function productPath(slug: string): string {
  return '/products/' + encodeURIComponent(slug)
}
