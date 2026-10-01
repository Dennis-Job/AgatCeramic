import type {
  RouteLocationNormalizedLoaded,
  RouteLocationRaw,
} from 'vue-router'

export type NavigationLink = {
  label: string
  to: RouteLocationRaw
  path: string
  section?: 'pages' | 'appearance' | 'stores'
  requiredPermission?: string
}
export type NavigationGroup = { label: string; links: NavigationLink[] }
export type NavigationSection = {
  id: string
  label: string
  link?: NavigationLink
  groups?: NavigationGroup[]
}

export const adminNavigation: NavigationSection[] = [
  {
    id: 'home',
    label: 'Главная',
    link: { label: 'Главная', to: '/', path: '/' },
  },
  {
    id: 'products',
    label: 'Товары',
    groups: [
      {
        label: 'Работа с товарами',
        links: [
          {
            label: 'Список товаров',
            to: '/products',
            path: '/products',
            requiredPermission: 'catalog.manage',
          },
        ],
      },
      {
        label: 'Справочники каталога',
        links: [
          {
            label: 'Категории',
            to: '/categories',
            path: '/categories',
            requiredPermission: 'catalog.manage',
          },
          {
            label: 'Бренды',
            to: '/brands',
            path: '/brands',
            requiredPermission: 'catalog.manage',
          },
        ],
      },
      {
        label: 'Характеристики товаров',
        links: [
          {
            label: 'Группы характеристик',
            to: '/attribute-groups',
            path: '/attribute-groups',
            requiredPermission: 'catalog.manage',
          },
          {
            label: 'Характеристики',
            to: '/attributes',
            path: '/attributes',
            requiredPermission: 'catalog.manage',
          },
        ],
      },
    ],
  },
  {
    id: 'orders',
    label: 'Заказы',
    link: {
      label: 'Заказы',
      to: '/orders',
      path: '/orders',
      requiredPermission: 'orders.view',
    },
  },
  {
    id: 'contacts',
    label: 'Обращения',
    link: {
      label: 'Обращения',
      to: '/contacts',
      path: '/contacts',
      requiredPermission: 'contacts.view',
    },
  },
  {
    id: 'content',
    label: 'Контент',
    groups: [
      {
        label: 'Сайт и страницы',
        links: [
          {
            label: 'Страницы',
            to: '/content',
            path: '/content',
            section: 'pages',
            requiredPermission: 'content.manage',
          },
          {
            label: 'Общее оформление',
            to: { path: '/content', query: { section: 'appearance' } },
            path: '/content',
            section: 'appearance',
            requiredPermission: 'content.manage',
          },
          {
            label: 'Магазины',
            to: { path: '/content', query: { section: 'stores' } },
            path: '/content',
            section: 'stores',
            requiredPermission: 'content.manage',
          },
        ],
      },
      {
        label: 'Файлы и изображения',
        links: [
          {
            label: 'Медиатека',
            to: '/media',
            path: '/media',
            requiredPermission: 'media.manage',
          },
        ],
      },
    ],
  },
  {
    id: 'management',
    label: 'Управление',
    groups: [
      {
        label: 'Сотрудники и доступ',
        links: [
          {
            label: 'Сотрудники',
            to: '/employees',
            path: '/employees',
            requiredPermission: 'admin-users.view',
          },
          {
            label: 'Роли',
            to: '/roles',
            path: '/roles',
            requiredPermission: 'roles.view',
          },
          {
            label: 'Права',
            to: '/permissions',
            path: '/permissions',
            requiredPermission: 'permissions.view',
          },
        ],
      },
      {
        label: 'Контроль действий',
        links: [
          {
            label: 'Журнал аудита',
            to: '/audit-log',
            path: '/audit-log',
            requiredPermission: 'audit-log.view',
          },
        ],
      },
      {
        label: 'Служебное',
        links: [{ label: 'UI-kit', to: '/ui-kit', path: '/ui-kit' }],
      },
    ],
  },
  {
    id: 'settings',
    label: 'Настройки',
    link: {
      label: 'Настройки',
      to: '/settings',
      path: '/settings',
      requiredPermission: 'settings.manage',
    },
  },
]

export function visibleNavigation(
  hasPermission: (permission: string) => boolean,
): NavigationSection[] {
  const allowed = (link: NavigationLink) =>
    !link.requiredPermission || hasPermission(link.requiredPermission)
  return adminNavigation.flatMap((section) => {
    if (section.link) return allowed(section.link) ? [section] : []
    const groups = section.groups
      ?.map((group) => ({ ...group, links: group.links.filter(allowed) }))
      .filter((group) => group.links.length)
    return groups?.length ? [{ ...section, groups }] : []
  })
}

export function isNavigationLinkActive(
  link: NavigationLink,
  route: Pick<RouteLocationNormalizedLoaded, 'path' | 'query'>,
): boolean {
  const pathMatches =
    route.path === link.path ||
    (link.path !== '/' && route.path.startsWith(`${link.path}/`))
  if (!pathMatches || !link.section) return pathMatches
  const section =
    route.query.section === 'appearance' || route.query.section === 'stores'
      ? route.query.section
      : 'pages'
  return section === link.section
}
