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
  materials: { ...intro, note: '', items: [] },
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
function scenarioFooter() {
  if (!scenario.startsWith('footer-')) return home.footer
  const full = scenario === 'footer-links'
  return {
    ...home.footer,
    navigation_links: full
      ? [{ label: 'Доставка плитки', to: '/about#delivery' }]
      : [],
    explore_links: full ? [{ label: 'Коллекции', to: '/catalog' }] : [],
    social_links: full
      ? [
          { label: 'Telegram', to: 'https://t.me/agat_test' },
          { label: 'ВКонтакте', to: 'https://vk.com/agat_test' },
        ]
      : scenario === 'footer-unsafe'
        ? [{ label: 'Опасно', to: 'javascript:alert(1)' }]
        : [],
    message: full
      ? {
          eyebrow: 'AgatCeramic',
          text: 'Поверхности, которые помогают создать пространство с характером.',
          link_label: 'Каталог',
          link_url: '/catalog',
        }
      : home.footer.message,
    bottom_right: 'Политика конфиденциальности',
    bottom_right_url: full
      ? '/about#privacy'
      : scenario === 'footer-unsafe'
        ? 'javascript:alert(1)'
        : '',
  }
}
function submenuNavigation() {
  if (!scenario.startsWith('submenu')) return null
  const children = [
    { label: 'Весь каталог', to: '/catalog' },
    { label: 'О материалах', to: '/about' },
    { label: 'Магазины', to: '/contacts' },
  ]
  if (scenario === 'submenu-unsafe')
    return [
      {
        label: 'Безопасный раздел',
        children: [
          ...children,
          { label: 'Опасный подпункт', to: 'javascript:alert(1)' },
          { label: 'С credentials', to: 'https://user:pass@example.test' },
          { label: 'С обратным слешем', to: '/\\example.test' },
        ],
      },
      {
        label: 'Пустая после фильтрации',
        children: [{ label: 'Опасно', to: '//example.test' }],
      },
    ]
  if (scenario === 'submenu-max')
    return [
      ...Array.from({ length: 15 }, (_, index) => ({
        label: `Раздел ${index + 1}: ${'ДлиннаяРусскаяСтрока'.repeat(10)}`,
        to: `/about#section-${index}`,
      })),
      {
        label: 'Последняя группа',
        children: Array.from({ length: 16 }, (_, index) => ({
          label: `Подраздел ${index + 1}: ${'Описание материала '.repeat(6)}`,
          to: `/catalog?section=${index + 1}`,
        })),
      },
    ]
  return [
    { label: 'Главная', to: '/' },
    { label: 'Каталог', children },
    {
      label: 'Информация',
      children: [
        { label: 'Контакты', to: '/contacts' },
        { label: 'Сотрудничество', to: '/cooperation' },
      ],
    },
    { label: 'Контакты повторно', to: '/contacts' },
  ]
}
function bannerBlocks() {
  const item = (id, name, image_url = null) => ({
    id,
    name,
    short_description: `Кратко: ${name}`,
    description: `Подробно: ${name}`,
    image_url,
    image_media_id: null,
    image_alt: `Фото: ${name}`,
  })
  const blocks = [
    {
      id: 'banner-grid',
      type: 'categories',
      enabled: scenario !== 'banners-grid-disabled',
      data: {
        ...intro,
        title: 'Сетка баннеров',
        items:
          scenario === 'banners-grid-empty'
            ? []
            : [
                item(
                  'grid-tile',
                  scenario === 'banners-grid-changed'
                    ? 'Изменённый баннер сетки'
                    : 'Плитка только в сетке',
                  '/images/home/category-porcelain.webp',
                ),
              ],
      },
    },
    {
      id: 'banner-menu',
      type: 'materials',
      enabled: scenario !== 'banners-menu-disabled',
      data: {
        ...intro,
        title: 'Меню баннеров',
        note: 'Уточните свойства у консультанта',
        items:
          scenario === 'banners-menu-empty'
            ? []
            : scenario === 'banners-menu-long'
              ? Array.from({ length: 12 }, (_, index) =>
                  item(
                    `menu-${index}`,
                    `Очень длинное русское название баннера ${index + 1}`,
                  ),
                )
              : scenario === 'banners-menu-changed'
                ? [item('menu-new', 'Новый баннер меню')]
                : [
                    item(
                      'menu-stone',
                      'Камень только в меню',
                      '/images/home/category-porcelain.webp',
                    ),
                    item('menu-mosaic', 'Мозаика только в меню'),
                  ],
      },
    },
  ].filter(
    (block) =>
      scenario !== 'banners-standalone-menu' || block.type === 'materials',
  )
  const menu = blocks.find((block) => block.type === 'materials')
  if (menu) {
    menu.data.items = menu.data.items.map((entry) => ({
      ...entry,
      note: 'Уточните свойства у консультанта',
    }))
  }
  if (scenario === 'banners-menu-links' && menu) {
    menu.data.note = 'Старое общее примечание'
    menu.data.items = [
      {
        ...item(
          'first',
          'Первый баннер меню',
          '/images/home/category-porcelain.webp',
        ),
        link_url: '/catalog?from=menu-first',
        note: 'Примечание первого баннера',
      },
      {
        ...item('second', 'Второй баннер меню'),
        link_url: 'https://example.test/menu-second',
        note: 'Примечание второго баннера',
      },
      { ...item('empty', 'Баннер без примечания'), link_url: null, note: '' },
      {
        ...item('unsafe', 'Небезопасная ссылка меню'),
        link_url: 'javascript:alert(1)',
        note: null,
      },
      item('legacy', 'Баннер без новых полей'),
    ]
  }
  if (scenario === 'banners-grid-links') {
    blocks[0].data.items = [
      {
        ...item(
          'grid-internal',
          'Баннер каталога',
          '/images/home/category-porcelain.webp',
        ),
        link_url: '/catalog?from=banner',
      },
      {
        ...item('grid-external', 'Внешний баннер'),
        link_url: 'https://example.test/banner',
      },
      item('grid-legacy', 'Баннер без ссылки'),
      { ...item('grid-empty', 'Пустая ссылка'), link_url: '' },
      { ...item('grid-null', 'Отсутствующая ссылка'), link_url: null },
      {
        ...item('grid-script', 'Небезопасная ссылка'),
        link_url: 'javascript:alert(1)',
      },
      {
        ...item('grid-protocol', 'Ссылка без протокола'),
        link_url: '//example.test/banner',
      },
      {
        ...item('grid-backslash', 'Обратный слеш'),
        link_url: '/\\example.test/banner',
      },
      {
        ...item('grid-credentials', 'Ссылка с паролем'),
        link_url: 'https://user:pass@example.test/banner',
      },
    ]
  }
  return blocks
}
createServer(async (request, response) => {
  response.setHeader('Content-Type', 'application/json; charset=utf-8')
  response.setHeader(
    'Access-Control-Allow-Origin',
    process.env.CLIENT_TEST_ORIGIN || 'http://127.0.0.1:3015',
  )
  response.setHeader('Access-Control-Allow-Credentials', 'true')
  response.setHeader('Access-Control-Allow-Headers', 'Accept')
  if (request.method === 'OPTIONS') {
    response.end()
    return
  }
  const url = new URL(request.url, 'http://127.0.0.1:8015')
  if (url.pathname === '/__editor') {
    const clientOrigin =
      process.env.CLIENT_TEST_ORIGIN || 'http://127.0.0.1:3015'
    response.setHeader('Content-Type', 'text/html; charset=utf-8')
    response.end(
      `<!doctype html><html lang="ru"><head><title>Редактор</title></head><body><h1>Редактор</h1><iframe title="Черновик" referrerpolicy="no-referrer" src="${clientOrigin}/preview/about" width="1280" height="720"></iframe></body></html>`,
    )
    return
  }
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
  if (url.pathname.startsWith('/api/v1/admin/content-preview/')) {
    response.setHeader('Cache-Control', 'private, no-store')
    if (!request.headers.cookie?.includes('preview_test_session=employee'))
      return fail(401)
    if (scenario === 'preview-revoked') return fail(403)
    if (scenario === 'preview-error') return fail(503)
    if (scenario === 'preview-missing') return fail(404)
    const slug = url.pathname.split('/').at(-1)
    const blocks =
      slug === 'home'
        ? (scenario.startsWith('banners-') ? bannerBlocks() : home.blocks).map(
            (block) =>
              block.type === 'hero'
                ? {
                    ...block,
                    data: {
                      ...block.data,
                      slides: [
                        slides[0],
                        {
                          ...slides[0],
                          id: 2,
                          title: 'Второй сохранённый слайд',
                        },
                      ],
                    },
                  }
                : block,
          )
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
          : slug === 'contacts'
            ? [
                {
                  id: 'stores',
                  type: 'stores',
                  enabled: true,
                  data: { title: 'Наши магазины' },
                },
              ]
            : []
    return send({
      data: {
        page: {
          title: 'Сохранённый черновик',
          slug,
          body: '',
          blocks: [
            ...blocks,
            {
              id: 'draft-text',
              type: 'text',
              enabled: true,
              data: {
                title: 'Сохранённый текст черновика',
                body: 'Черновик <script>window.unsafe = true</script>',
              },
            },
            {
              id: 'draft-hidden',
              type: 'text',
              enabled: false,
              data: { title: 'Выключенный блок черновика', body: '' },
            },
          ],
          seo: { ...seo, title: 'Сохранённый черновик' },
        },
        appearance: {
          header: {
            ...home.header,
            topbar_right: 'Черновая шапка',
            navigation: submenuNavigation() ?? home.header.navigation,
          },
          footer: scenarioFooter(),
        },
      },
    })
  }
  if (url.pathname === '/api/v1/site-appearance') {
    if (scenario === 'appearance-error') return fail(503)
    const longNavigation = [
      { label: 'Каталог керамической плитки и керамогранита', to: '/catalog' },
      { label: 'Условия доставки и обслуживания покупателей', to: '/about' },
      { label: 'Контакты наших магазинов и салонов', to: '/contacts' },
      { label: 'Советы по выбору материалов для ремонта', to: '/' },
    ]
    const navigation =
      submenuNavigation() ??
      (scenario === 'long-navigation'
        ? longNavigation
        : scenario === 'max-navigation'
          ? [
              ...longNavigation,
              ...Array.from({ length: 12 }, (_, index) => ({
                label: 'ДлиннаяРусскаяСтрока'.repeat(12),
                to: `/about#section-${index}`,
              })),
            ]
          : scenario === 'header-navigation'
            ? [...nav, { label: 'Сотрудничество', to: '/cooperation' }]
            : home.header.navigation)
    return send({
      data: {
        header: {
          ...home.header,
          topbar_left: 'Прежний текст слева',
          topbar_right: 'Общее оформление сайта',
          navigation,
        },
        footer: scenarioFooter(),
      },
    })
  }
  if (url.pathname === '/api/v1/home-page') {
    if (scenario.startsWith('banners-'))
      return send({ data: { ...home, blocks: bannerBlocks() } })
    if (scenario === 'missing') return fail(404)
    if (scenario === 'error') return fail(503)
    if (scenario === 'noindex')
      return send({ data: { ...home, is_indexable: false } })
    if (scenario === 'indexable')
      return send({ data: { ...home, is_indexable: true } })
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
  if (url.pathname === '/api/v1/pages') {
    const page = Number(url.searchParams.get('page') || 1)
    if (
      scenario === 'sitemap-error' ||
      (scenario === 'sitemap-page-error' && page === 2)
    )
      return fail(503)
    if (scenario === 'sitemap-malformed')
      return send({ data: [], meta: { current_page: 1 } })
    return send({
      data:
        scenario === 'sitemap-empty'
          ? []
          : page === 1
            ? [
                {
                  slug: 'home',
                  is_indexable: true,
                  published_at: '2026-10-19T10:00:00Z',
                },
                {
                  slug: 'delivery',
                  is_indexable: true,
                  published_at: '2026-10-18T10:00:00Z',
                },
                { slug: 'hidden-page', is_indexable: false },
              ]
            : [
                { slug: 'legacy-page' },
                { slug: 'published-address', is_indexable: true },
                { slug: 'preview', is_indexable: true },
                { slug: '//evil.example', is_indexable: true },
              ],
      meta: {
        current_page: page,
        last_page: scenario === 'sitemap-empty' ? 1 : 2,
      },
    })
  }
  if (url.pathname.startsWith('/api/v1/pages/')) {
    if (scenario === 'missing') return fail(404)
    if (scenario === 'error') return fail(503)
    const slug = url.pathname.split('/').at(-1)
    if (slug === 'draft-only') return fail(404)
    const title =
      {
        contacts: 'Контакты',
        about: 'О нас',
        catalog: 'Каталог',
        cooperation: 'Сотрудничество',
      }[slug] || 'Доставка керамической плитки и материалов для ремонта'
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
        ...(scenario === 'noindex' ? { is_indexable: false } : {}),
        ...(scenario === 'indexable' ? { is_indexable: true } : {}),
        title,
        slug,
        body: '',
        blocks:
          scenario === 'empty-page' || slug === 'cooperation' ? [] : blocks,
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
              phones: ['+7 (900) 000-00-00', '+7 (900) 111-11-11'],
              email: 'shop@example.test',
              ...(scenario === 'legacy-contacts'
                ? {}
                : {
                    partner_email:
                      scenario === 'partner-long'
                        ? `${'business'.repeat(25)}@example.test`
                        : 'partners@example.test',
                  }),
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
