// Synthetic standalone-catalog contract; never reads the development database.
export function handleCatalog(request, response, url, scenario) {
  if (!scenario.startsWith('standalone-')) return false
  const send = (body, status = 200) => {
    response.statusCode = status
    response.end(JSON.stringify(body))
    return true
  }
  if (url.pathname === '/api/v1/pages/catalog') {
    if (scenario === 'standalone-missing') return send({}, 404)
    if (scenario === 'standalone-cms-error') return send({}, 503)
    return send({
      data: {
        title: 'Каталог материалов',
        slug: 'catalog',
        body: '',
        blocks: [],
        is_indexable: scenario !== 'standalone-noindex',
        seo: {
          title: 'Каталог материалов',
          description: 'Подберите материал',
          og_image_url: null,
        },
      },
    })
  }
  if (url.pathname !== '/api/v1/catalog') return false
  if (scenario === 'standalone-error') return send({}, 503)
  let attributes = {}
  try {
    attributes = JSON.parse(url.searchParams.get('attributes') || '{}')
  } catch {
    return send({}, 422)
  }
  const category = url.searchParams.get('category') || ''
  if (
    Object.keys(attributes).some(
      (id) =>
        !(
          category === 'tile' &&
          ['1', '2', '3', '4', '5', '6', '7', '8'].includes(id)
        ) && !(category === 'bath' && id === '9'),
    )
  )
    return send({}, 422)
  const categories = [
    { id: 1, parent_id: null, slug: 'materials', name: 'Материалы' },
    { id: 2, parent_id: 1, slug: 'tile', name: 'Плитка' },
    { id: 3, parent_id: null, slug: 'mosaic', name: 'Мозаика' },
    { id: 4, parent_id: null, slug: 'plumbing', name: 'Сантехника' },
    { id: 5, parent_id: 4, slug: 'bath', name: 'Ванны' },
    { id: 6, parent_id: 4, slug: 'showers', name: 'Душевые кабины' },
    { id: 7, parent_id: 4, slug: 'sinks', name: 'Раковины' },
    { id: 8, parent_id: 4, slug: 'mixers', name: 'Смесители' },
    { id: 9, parent_id: 4, slug: 'toilets', name: 'Унитазы' },
  ]
  const definitions = [
    'select',
    'multiselect',
    'boolean',
    'integer',
    'decimal',
    'date',
    'string',
    'text',
  ].map((type, index) => ({
    id: index + 1,
    type,
    name: [
      'Цвет',
      'Поверхность',
      'Морозостойкость',
      'Длина',
      'Толщина',
      'Дата выпуска',
      'Рисунок',
      'Описание',
    ][index],
    slug: 'attr-' + index,
    unit: type === 'integer' || type === 'decimal' ? 'мм' : null,
    options:
      type === 'boolean'
        ? [
            { value: 'true', label: 'Да' },
            { value: 'false', label: 'Нет' },
          ]
        : [
            { value: 'gray', label: 'Серый' },
            { value: 'white', label: 'Белый' },
          ],
  }))
  let products = Array.from({ length: 27 }, (_, i) => ({
    id: i + 1,
    slug: 'tile-' + (i + 1),
    name:
      (category === 'bath' ? 'Ванна ' : 'Плитка ') +
      String(i + 1).padStart(2, '0'),
    description: null,
    price: i === 26 ? null : String(100 + i * 10),
    old_price: null,
    unit: 'square_meter',
    in_stock: i % 2 === 0,
    is_on_sale: false,
    image_url: null,
    image_alt: '',
    category: categories[1],
    brand: { id: 1, name: 'Atlas', slug: 'atlas' },
  }))
  if (
    category === 'mosaic' ||
    url.searchParams.get('brand') === 'empty' ||
    scenario === 'standalone-empty'
  )
    products = []
  if (url.searchParams.get('in_stock'))
    products = products.filter((p) => p.in_stock)
  if (url.searchParams.get('price_from'))
    products = products.filter(
      (p) =>
        p.price !== null &&
        Number(p.price) >= Number(url.searchParams.get('price_from')),
    )
  if (url.searchParams.get('price_to'))
    products = products.filter(
      (p) =>
        p.price !== null &&
        Number(p.price) <= Number(url.searchParams.get('price_to')),
    )
  if (Object.keys(attributes).length)
    products = products.filter((p) => p.id <= 3)
  const sort = url.searchParams.get('sort')
  if (sort === 'name_desc' || sort === 'newest') products.reverse()
  if (sort?.startsWith('price_'))
    products.sort((a, b) =>
      a.price === null
        ? 1
        : b.price === null
          ? -1
          : (Number(a.price) - Number(b.price)) *
            (sort === 'price_desc' ? -1 : 1),
    )
  const page = Number(url.searchParams.get('page') || 1)
  return send({
    data: products.slice((page - 1) * 24, page * 24),
    categories,
    meta: {
      current_page: page,
      last_page: Math.max(1, Math.ceil(products.length / 24)),
      total: products.length,
    },
    filters: {
      brands: [
        { id: 1, slug: 'atlas', name: 'Atlas' },
        { id: 2, slug: 'empty', name: 'Другой бренд' },
      ],
      collections:
        url.searchParams.get('brand') === 'atlas'
          ? [{ id: 1, name: 'Stone', brand_slug: 'atlas' }]
          : [],
      attributes:
        category === 'tile'
          ? definitions
          : category === 'bath'
            ? [
                {
                  id: 9,
                  type: 'select',
                  name: 'Материал сантехники',
                  slug: 'plumbing-material',
                  unit: null,
                  options: [
                    { value: 'acrylic', label: 'Акрил' },
                    { value: 'steel', label: 'Сталь' },
                  ],
                },
              ]
            : [],
    },
  })
}
