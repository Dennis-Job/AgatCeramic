import { createServer } from 'node:http'

// Synthetic data, confined to the test server. No development database is used.
const intro = {
  eyebrow: 'Материалы',
  title: 'Материалы для интерьера',
  description: 'Описание материалов',
}
const seo = {
  title: 'AgatCeramic',
  description: 'Каталог материалов',
  og_title: 'AgatCeramic',
  og_description: 'Каталог материалов',
  og_image_url: null,
}
const nav = [
  { label: 'Главная', to: '/' },
  { label: 'Каталог', to: '/catalog' },
  { label: 'О нас', to: '/about' },
  { label: 'Контакты', to: '/contacts' },
]
const slides = [
  {
    id: 1,
    eyebrow: 'Коллекция',
    title: 'Материалы для вашего дома',
    description: 'Описание опубликованного слайда',
    image_url: '/images/home/hero-porcelain.webp',
    image_alt: 'Керамогранит',
    link_label: 'Каталог',
    link_url: '/catalog',
  },
]
const home = {
  hero_slides: slides,
  marquee: { topics: ['Керамика', 'Керамогранит'] },
  categories: { ...intro, items: [] },
  materials: { ...intro, note: '' },
  promo: { ...intro, link_label: 'Контакты', link_url: '/contacts' },
  about: {
    ...intro,
    image_url: null,
    image_alt: '',
    link_label: 'О нас',
    link_url: '/about',
  },
  guide: { eyebrow: 'Выбор', title: 'Как выбрать', items: [] },
  header: {
    topbar_left: '',
    topbar_right: '',
    navigation: nav,
    logo_url: null,
    logo_alt: '',
  },
  footer: {
    tagline: 'Материалы для дома',
    explore_links: [],
    message: { eyebrow: '', text: '', link_label: '', link_url: '' },
    bottom_left: 'AgatCeramic',
    bottom_right: '',
  },
  seo,
  blocks: [
    { id: 'hero', type: 'hero', enabled: true, data: { slider_id: 1, slides } },
    {
      id: 'intro',
      type: 'text',
      enabled: true,
      data: {
        title: 'Опубликованная главная',
        body: 'Содержимое главной из API',
      },
    },
    {
      id: 'hidden',
      type: 'text',
      enabled: false,
      data: { title: 'Скрытый блок', body: 'Невидимый текст' },
    },
  ],
}
let scenario = 'default'
createServer(async (request, response) => {
  response.setHeader('Content-Type', 'application/json; charset=utf-8')
  response.setHeader('Access-Control-Allow-Origin', '*')
  const url = new URL(request.url, 'http://127.0.0.1:8015')
  if (url.pathname === '/__scenario') {
    scenario = url.searchParams.get('name') || 'default'
    response.end('{}')
    return
  }
  const fail = (code) => {
    response.statusCode = code
    response.end(JSON.stringify({ message: 'Test response' }))
  }
  const send = (payload) => response.end(JSON.stringify(payload))
  if (url.pathname === '/api/v1/site-appearance') {
    if (scenario === 'appearance-error') return fail(503)
    const longNavigation = [
      { label: 'Каталог керамической плитки и керамогранита', to: '/catalog' },
      { label: 'Условия доставки и обслуживания покупателей', to: '/about' },
      { label: 'Контакты наших магазинов и салонов', to: '/contacts' },
      { label: 'Советы по выбору материалов для ремонта', to: '/' },
    ]
    const navigation =
      scenario === 'long-navigation'
        ? longNavigation
        : scenario === 'max-navigation'
          ? [
              ...longNavigation,
              ...Array.from({ length: 12 }, (_, index) => ({
                label: 'ДлиннаяРусскаяСтрока'.repeat(12),
                to: `/about#section-${index}`,
              })),
            ]
          : home.header.navigation
    return send({
      data: {
        header: {
          ...home.header,
          topbar_left: 'Общее оформление сайта',
          navigation,
        },
        footer: home.footer,
      },
    })
  }
  if (url.pathname === '/api/v1/home-page') {
    if (scenario === 'missing') return fail(404)
    if (scenario === 'standalone-categories')
      return send({
        data: {
          ...home,
          blocks: [
            {
              id: 'categories',
              type: 'categories',
              enabled: true,
              data: {
                ...intro,
                items: [
                  {
                    id: 'tile',
                    name: 'Керамическая плитка',
                    short_description: 'Описание',
                    description: 'Описание материала',
                    image_url: null,
                    image_alt: '',
                  },
                ],
              },
            },
            {
              id: 'materials',
              type: 'materials',
              enabled: false,
              data: home.materials,
            },
          ],
        },
      })
    return send({ data: home })
  }
  if (url.pathname.startsWith('/api/v1/pages/')) {
    if (scenario === 'missing') return fail(404)
    if (scenario === 'error') return fail(503)
    const slug = url.pathname.split('/').at(-1)
    const title = { contacts: 'Контакты', about: 'О нас', catalog: 'Каталог' }[
      slug
    ]
    let blocks =
      slug === 'contacts'
        ? [
            {
              id: 'stores',
              type: 'stores',
              enabled: true,
              data: { title: 'Наши магазины' },
            },
          ]
        : slug === 'catalog'
          ? [
              {
                id: 'catalog',
                type: 'catalog',
                enabled: true,
                data: {
                  title: 'Материалы',
                  description: 'Опубликованные товары',
                },
              },
            ]
          : [
              {
                id: 'second',
                type: 'text',
                enabled: true,
                data: {
                  title: 'Второй блок поставлен первым',
                  body: 'Текст из API <script>window.unsafe = true</script>',
                },
              },
              {
                id: 'first',
                type: 'text',
                enabled: true,
                data: {
                  title: 'Первый блок поставлен вторым',
                  body: 'Длинный русский текст о материалах и свойствах',
                },
              },
              {
                id: 'hidden',
                type: 'text',
                enabled: false,
                data: { title: 'Скрытый блок', body: 'Невидимый текст' },
              },
            ]
    if (scenario === 'long-strings')
      blocks = [
        {
          id: 'stores',
          type: 'stores',
          enabled: true,
          data: { title: 'Заголовок'.repeat(28) },
        },
      ]
    return send({
      data: {
        title,
        slug,
        body: '',
        blocks: scenario === 'empty-page' ? [] : blocks,
        seo: {
          ...seo,
          title: `${title} — AgatCeramic`,
          description: 'Опубликованное описание страницы',
        },
      },
    })
  }
  if (url.pathname === '/api/v1/site-settings') {
    if (scenario === 'contacts-error') return fail(503)
    return send({
      data:
        scenario === 'empty'
          ? { seller_name: null, address: null, phones: [], email: null }
          : {
              seller_name:
                scenario === 'long-strings'
                  ? 'Продавец'.repeat(30)
                  : 'Тестовый продавец',
              address:
                scenario === 'long-strings'
                  ? 'Адрес'.repeat(80)
                  : 'Тестовый адрес',
              phones: ['+7 (900) 000-00-00'],
              email: 'shop@example.test',
            },
    })
  }
  if (url.pathname === '/api/v1/stores') {
    if (scenario === 'contacts-error') return fail(503)
    const page = Number(url.searchParams.get('page') || 1)
    return send({
      data:
        scenario === 'empty'
          ? []
          : [
              {
                id: page,
                name: `Магазин ${page}`,
                address:
                  'Очень длинный адрес магазина для проверки адаптивности и переносов строк',
                phone: null,
                working_hours: Array.from({ length: 7 }, (_, index) => ({
                  weekday: index + 1,
                  is_closed: index > 4,
                  opens_at: index > 4 ? null : '09:00',
                  closes_at: index > 4 ? null : '18:00',
                })),
              },
            ],
      meta: { last_page: scenario === 'empty' ? 1 : 2 },
    })
  }
  if (url.pathname === '/api/v1/catalog') {
    if (scenario === 'catalog-error') return fail(503)
    const page = Number(url.searchParams.get('page') || 1)
    return send({
      data:
        scenario === 'empty'
          ? []
          : [
              {
                id: page,
                name: `Керамогранит с длинным русским названием ${page}`,
                slug: 'test',
                description: 'Описание материала из товарного модуля',
                price: '1234.50',
                old_price: null,
                unit: 'square_meter',
                is_on_sale: false,
                category: { id: 1, name: 'Керамогранит', slug: 'porcelain' },
                brand: null,
                image_url: '/images/home/category-porcelain.webp',
                image_alt: 'Поверхность материала',
              },
            ],
      categories: [
        {
          id: 1,
          name: 'Керамогранит',
          slug: 'porcelain',
          description: '',
          image_url: null,
        },
      ],
      meta: {
        current_page: page,
        last_page: scenario === 'empty' ? 1 : 2,
        total: scenario === 'empty' ? 0 : 2,
      },
    })
  }
  fail(404)
}).listen(8015, '127.0.0.1')
