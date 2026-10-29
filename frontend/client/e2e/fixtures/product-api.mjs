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
  category,
  brand,
  image_url: '/images/home/hero-porcelain.webp',
  image_alt: 'Поверхность материала',
})
const products = [
  card(1, 'Керамогранит с длинным русским названием 1'),
  card(2, 'Керамогранит Светлый'),
  card(3, 'Тестовая затирка', 'kilogram'),
  card(4, 'Рекомендуемый керамогранит'),
]
export function handleProducts(request, response, url, scenario) {
  const send = (data, status = 200) => {
    response.writeHead(status, { 'Content-Type': 'application/json' })
    response.end(JSON.stringify(data))
    return true
  }
  if (
    url.pathname === '/api/v1/catalog' &&
    ['category', 'brand', 'collection'].some((key) => url.searchParams.has(key))
  ) {
    return send({
      data: products
        .slice(0, 2)
        .map((product) => ({ ...product, description: 'Описание из API' })),
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
  configureCartProducts(
    products.map((item) => ({ ...item, stock_quantity: 10 })),
  )
  const axes = [{ slug: 'tsvet', name: 'Цвет' }]
  return send({
    data: {
      ...product,
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
      attributes: [
        {
          id: 1,
          slug: 'kollektsiya',
          name: 'Коллекция',
          type: 'text',
          unit: null,
          value: 'Тестовая коллекция',
          display_value: 'Тестовая коллекция',
        },
        {
          id: 2,
          slug: 'size',
          name: 'Размер',
          type: 'text',
          unit: 'мм',
          value: '300×300',
          display_value: '300×300',
        },
      ],
      collection: { name: 'Тестовая коллекция', brand_slug: brand.slug },
      group: empty
        ? null
        : {
            axes,
            products: products
              .slice(0, 2)
              .map((item, index) => ({
                ...item,
                axis_values: [
                  {
                    slug: 'tsvet',
                    value: index ? 'light' : 'gray',
                    display_value: index ? 'Светлый' : 'Серый',
                  },
                ],
              })),
          },
      related: empty ? [] : [products[2]],
      recommended: empty ? [] : [products[3]],
    },
  })
}
