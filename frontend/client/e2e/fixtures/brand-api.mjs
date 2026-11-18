// Public brand-page fixtures; these never read or mutate the development DB.
const brands = [
  {
    id: 1,
    name: 'Atlas',
    slug: 'atlas',
    description: 'Керамическая плитка и мозаика Atlas.',
    logo_url: '/images/page-intro/catalog-wide.webp',
    logo_alt: 'Изображение бренда Atlas',
  },
  {
    id: 2,
    name: 'Другой бренд',
    slug: 'empty',
    description: null,
    logo_url: null,
    logo_alt: '',
  },
  {
    id: 3,
    name: 'Beta',
    slug: 'beta',
    description: 'Сантехника бренда Beta.',
    logo_url: '/images/page-intro/contacts-wide.webp',
    logo_alt: 'Изображение бренда Beta',
  },
]

export function handleBrands(request, response, url, scenario) {
  if (!/^\/api\/v1\/brands(?:\/[^/]+)?$/.test(url.pathname)) return false
  const send = (body, status = 200) => {
    response.statusCode = status
    response.end(JSON.stringify(body))
    return true
  }
  if (url.pathname !== '/api/v1/brands') {
    if (scenario === 'brand-page-error') return send({}, 503)
    const slug = decodeURIComponent(url.pathname.split('/').at(-1))
    const brand =
      slug === 'test-brand'
        ? {
            id: 1,
            name: 'Тестовый бренд',
            slug,
            description: null,
            logo_url: null,
            logo_alt: '',
          }
        : brands.find((item) => item.slug === slug)
    if (brand && scenario === 'brand-page-long-broken')
      return send({
        data: {
          ...brand,
          description:
            'Описание Atlas.\nОтдельная строка описания.\n' +
            'Сверхдлинноеназвание'.repeat(36) +
            '\n<script>window.brandLeak = true</script>',
          logo_url: '/images/page-intro/missing-brand-image.webp',
        },
      })
    return brand ? send({ data: brand }) : send({}, 404)
  }
  const page = Number(url.searchParams.get('page') || 1)
  if (
    scenario === 'sitemap-brands-error' ||
    (scenario === 'sitemap-brands-page-error' && page === 2)
  )
    return send({}, 503)
  if (scenario === 'sitemap-brands-malformed')
    return send({ data: {}, meta: {} })
  if (scenario === 'sitemap-brands-loop')
    return send({ data: [], meta: { current_page: page, last_page: 10001 } })
  if (scenario === 'sitemap-brands-moving-pages')
    return send({
      data: [],
      meta: { current_page: page, last_page: page === 1 ? 2 : 3 },
    })
  if (scenario === 'sitemap-brands-summary-malformed')
    return send({
      data: [{ slug: null }],
      meta: { current_page: 1, last_page: 1 },
    })
  const empty = scenario === 'sitemap-empty' || scenario === 'empty'
  return send({
    data: empty ? [] : page === 1 ? brands.slice(0, 2) : brands.slice(2),
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      last_page: empty ? 1 : 2,
      total: empty ? 0 : brands.length,
    },
  })
}
