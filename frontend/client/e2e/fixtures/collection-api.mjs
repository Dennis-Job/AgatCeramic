// Shared public collection projection for the product story and collection page.
export const testProductCollection = {
  id: 1,
  slug: 'test-collection',
  name: 'Тестовая коллекция',
  brand_slug: 'test-brand',
  headline: 'Спокойный камень для светлых пространств',
  description:
    'Настоящее описание коллекции из API.\nМатериал для спокойных и светлых интерьеров.',
  country_code: 'RU',
  image_url: '/images/home/materials.webp',
  image_alt: 'Коллекция в интерьере светлой ванной',
}

const collections = [
  {
    ...testProductCollection,
    brand_name: 'Тестовый бренд',
  },
  {
    id: 2,
    slug: 'stone',
    name: 'Stone',
    brand_slug: 'atlas',
    brand_name: 'Atlas',
    headline: 'Камень Atlas',
    description: 'Плитка и мозаика Atlas из коллекции Stone.',
    country_code: 'IT',
    image_url: '/images/home/materials.webp',
    image_alt: 'Интерьер Atlas',
  },
  {
    id: 3,
    slug: 'stone',
    name: 'Stone',
    brand_slug: 'beta',
    brand_name: 'Beta',
    headline: null,
    description: null,
    country_code: null,
    image_url: null,
    image_alt: null,
  },
  {
    id: 4,
    slug: 'empty-series',
    name: 'Пустая коллекция',
    brand_slug: 'atlas',
    brand_name: 'Atlas',
    headline: null,
    description: null,
    country_code: null,
    image_url: null,
    image_alt: null,
  },
]

export function handleCollections(response, url, scenario) {
  const detail = url.pathname.match(
    /^\/api\/v1\/brands\/([a-z\d-]+)\/collections\/([a-z\d-]+)$/,
  )
  if (url.pathname !== '/api/v1/collections' && !detail) return false
  const send = (body, status = 200) => {
    response.statusCode = status
    response.end(JSON.stringify(body))
    return true
  }
  if (detail) {
    if (scenario === 'collection-page-error') return send({}, 503)
    const collection = collections.find(
      ({ brand_slug, slug }) => brand_slug === detail[1] && slug === detail[2],
    )
    return collection ? send({ data: collection }) : send({}, 404)
  }
  const page = Number(url.searchParams.get('page') || 1)
  if (scenario === 'sitemap-collections-error' && page === 2)
    return send({}, 503)
  const included = scenario.startsWith('sitemap-collections')
  return send({
    data: included ? collections.slice(page - 1, page) : [],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      last_page: included ? collections.length : 1,
      total: included ? collections.length : 0,
    },
  })
}
