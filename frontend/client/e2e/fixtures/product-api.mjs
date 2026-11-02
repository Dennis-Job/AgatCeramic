import { configureCartProducts } from './cart-api.mjs'

// Synthetic storefront data. No connection to the development database.
const category = { id: 1, name: 'Керамогранит', slug: 'porcelain' }
const brand = {
  id: 1,
  name: 'Тестовый бренд',
  slug: 'test-brand',
  logo_url: null,
  logo_alt: '',
}
const card = (id, name, unit = 'square_meter') => ({
  id,
  name,
  slug: `product-${id}`,
  price: '1234.50',
  old_price: null,
  unit,
  is_on_sale: true,
  in_stock: true,
  size: null,
  category,
  brand,
  image_url: '/images/home/hero-porcelain.webp',
  image_alt: 'Поверхность материала',
})
const products = [
  card(1, 'Керамогранит с длинным русским названием 1'),
  card(2, 'Керамогранит Светлый'),
  card(3, 'Тестовая затирка', 'kilogram'),
  { ...card(4, 'Рекомендуемый керамогранит'), size: '300×300 мм' },
]
export function handleProducts(request, response, url, scenario) {
  const send = (data, status = 200) => {
    response.writeHead(status, { 'Content-Type': 'application/json' })
    response.end(JSON.stringify(data))
    return true
  }
  if (
    url.pathname === '/api/v1/catalog' &&
    (scenario === 'catalog-card-standard' ||
      ['category', 'brand', 'collection'].some((key) =>
        url.searchParams.has(key),
      ))
  ) {
    return send({
      data: (scenario === 'catalog-card-standard'
        ? [
            { ...products[0], size: '600×600 мм' },
            {
              ...products[1],
              is_on_sale: false,
              price: null,
              brand: null,
              image_url: null,
            },
            { ...products[2], in_stock: false },
          ]
        : products.slice(0, 2)
      ).map((product) => ({ ...product, description: 'Описание из API' })),
      categories: [category],
      meta: { current_page: 1, last_page: 1, total: 2 },
    })
  }
  if (!url.pathname.startsWith('/api/v1/products/')) return false
  if (scenario === 'product-error') return send({ message: 'Unavailable' }, 503)
  const product = products.find(
    (product) =>
      product.slug === decodeURIComponent(url.pathname.split('/').at(-1)),
  )
  if (!product) return send({ message: 'Not found' }, 404)
  const empty = scenario === 'product-empty'
  let collection = { name: 'Тестовая коллекция', brand_slug: brand.slug }
  if (scenario.startsWith('product-collection')) {
    collection =
      product.id === 2 || scenario === 'product-collection-null'
        ? null
        : {
            id: 1,
            slug: 'test-collection',
            name: 'Тестовая коллекция',
            brand_slug: brand.slug,
            headline: 'Спокойный камень для светлых пространств',
            description:
              'Настоящее описание коллекции из API.\nМатериал для спокойных и светлых интерьеров.',
            country_code: 'RU',
            image_url: '/images/home/materials.webp',
            image_alt: 'Коллекция в интерьере светлой ванной',
          }
    if (collection && scenario === 'product-collection-no-description')
      collection.description = '  \n '
    if (collection && scenario === 'product-collection-no-image')
      collection.image_url = null
    if (collection && scenario === 'product-collection-private-image')
      collection.image_url = 'javascript:alert(1)'
    if (collection && scenario === 'product-collection-broken-image')
      collection.image_url = '/images/home/missing-collection.webp'
    if (collection && scenario === 'product-collection-fallback') {
      collection.headline = ' '
      collection.image_alt = null
      collection.country_code = null
    }
    if (collection && scenario === 'product-collection-long') {
      collection.headline =
        'Коллекция натурального камня с выразительной фактурой для просторных светлых интерьеров и современных пространств'
      collection.description =
        'Описание коллекции с длинными русскими предложениями и сохранёнными переносами.\n'.repeat(
          8,
        )
    }
  }
  configureCartProducts(
    products.map((item) => ({ ...item, stock_quantity: 10 })),
  )
  const axes = [{ slug: 'tsvet', name: 'Цвет' }]
  const specs = [
    {
      id: 11,
      slug: 'weight',
      name: 'Вес',
      type: 'decimal',
      unit: 'кг',
      value: 26.16,
      display_value: '26,16',
      group: { id: 2, name: 'Размеры и вес', sort_order: 2 },
    },
    {
      id: 12,
      slug: 'texture',
      name: 'Текстура',
      type: 'text',
      unit: null,
      value: 'Матовая',
      display_value: 'Матовая',
      group: { id: 1, name: 'Поверхность', sort_order: 1 },
    },
    {
      id: 13,
      slug: 'size',
      name: 'Размер',
      type: 'text',
      unit: 'мм',
      value: '300×300',
      display_value: '300×300',
      group: { id: 2, name: 'Размеры и вес', sort_order: 2 },
    },
    {
      id: 14,
      slug: 'extra',
      name: 'Особенность',
      type: 'text',
      unit: null,
      value: 'Камень',
      display_value: 'Камень',
      group: null,
    },
    {
      id: 15,
      slug: 'finish',
      name: 'Фактура',
      type: 'text',
      unit: null,
      value: 'Текстурированная',
      display_value: 'Текстурированная',
      group: { id: 3, name: 'Поверхность', sort_order: 3 },
    },
  ]
  if (scenario === 'product-specs-long') {
    specs[1].group.name =
      'Дополнительные характеристики поверхности и декоративного оформления материала'
    specs[1].name = 'Особенностиобработкиповерхностибезпробелов'
    specs[1].display_value = 'Оченьдлинноезначениехарактеристикибезпробелов'
  }
  return send({
    data: {
      ...product,
      ...(scenario === 'product-not-sale'
        ? { is_on_sale: false, old_price: '2345.00' }
        : {}),
      ...(empty ? { price: null, in_stock: false, image_url: null } : {}),
      sku: `TEST-${product.id}`,
      article_number: `TEST-ARTICLE-${product.id}`,
      description: empty ? null : 'Описание из API',
      images: empty
        ? []
        : ['hero-porcelain', 'category-porcelain', 'materials'].map(
            (name, index) => ({
              id: index + 1,
              url: `/images/home/${name}.webp`,
              alt: `Фото ${index + 1}`,
              is_primary: index === 0,
              sort_order: index,
            }),
          ),
      attributes:
        scenario === 'product-specs-empty'
          ? []
          : scenario.startsWith('product-specs')
            ? specs.map((attribute) => {
                if (scenario !== 'product-specs-legacy') return attribute
                const { group, ...legacy } = attribute
                return legacy
              })
            : [
                {
                  id: 1,
                  slug: 'kollektsiya',
                  name: 'Коллекция',
                  type: 'text',
                  unit: null,
                  value: 'Тестовая коллекция',
                  display_value: 'Тестовая коллекция',
                  group: { id: 1, name: 'Общие характеристики', sort_order: 0 },
                },
                {
                  id: 2,
                  slug: 'size',
                  name: 'Размер',
                  type: 'text',
                  unit: 'мм',
                  value: '300×300',
                  display_value: '300×300',
                  group: { id: 2, name: 'Размеры', sort_order: 1 },
                },
              ],
      collection,
      group: empty
        ? null
        : {
            axes,
            products: products.slice(0, 2).map((item, index) => ({
              ...item,
              sku: `TEST-${item.id}`,
              ...(scenario === 'product-duplicate-names'
                ? { name: products[0].name }
                : {}),
              axis_values: [
                {
                  slug: 'tsvet',
                  value: scenario.startsWith('product-duplicate-')
                    ? 'gray'
                    : index
                      ? 'light'
                      : 'gray',
                  display_value: scenario.startsWith('product-duplicate-')
                    ? 'Серый'
                    : index
                      ? 'Светлый'
                      : 'Серый',
                },
              ],
            })),
          },
      related: empty ? [] : [products[2]],
      recommended: empty
        ? []
        : scenario === 'product-recommendations'
          ? [products[3], { ...products[1], brand: null }, products[2]]
          : [products[3]],
    },
  })
}
