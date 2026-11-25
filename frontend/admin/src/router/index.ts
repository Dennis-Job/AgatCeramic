import { ref } from 'vue'
import {
  createRouter,
  createWebHistory,
  type RouteComponent,
  type RouteLocationNormalized,
} from 'vue-router'
import { useAuthStore } from '../stores/auth'

class RouteViewLoadError extends Error {}

function loadRouteView(load: () => Promise<{ default: RouteComponent }>) {
  return () =>
    load().catch((cause: unknown) => {
      throw new RouteViewLoadError('Admin route view failed to load', { cause })
    })
}

export const failedRouteHref = ref<string>()
let latestNavigation: RouteLocationNormalized

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/collections',
      name: 'collections',
      component: loadRouteView(() => import('../views/CollectionsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Коллекции',
      },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: loadRouteView(() => import('../views/NotificationsView.vue')),
      meta: { requiresAuth: true, title: 'Уведомления' },
    },
    {
      path: '/brands',
      name: 'brands',
      component: loadRouteView(() => import('../views/BrandsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Бренды',
      },
    },
    {
      path: '/attributes',
      name: 'attributes',
      component: loadRouteView(() => import('../views/AttributesView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Характеристики',
      },
    },
    {
      path: '/attribute-groups',
      name: 'attribute-groups',
      component: loadRouteView(
        () => import('../views/AttributeGroupsView.vue'),
      ),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Характеристики',
      },
    },
    {
      path: '/categories',
      name: 'categories',
      component: loadRouteView(() => import('../views/CategoriesView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Категории',
      },
    },
    {
      path: '/products',
      name: 'products',
      component: loadRouteView(() => import('../views/ProductsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'catalog.manage',
        title: 'Товары',
      },
    },
    {
      path: '/products/import',
      component: loadRouteView(() => import('../views/ProductImportView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'imports.manage',
        additionalPermission: 'catalog.manage',
        title: 'Добавить массово товары',
      },
    },
    {
      path: '/products/price-status',
      component: loadRouteView(
        () => import('../views/ProductPriceStatusImportView.vue'),
      ),
      meta: {
        requiresAuth: true,
        requiredPermission: 'imports.manage',
        additionalPermission: 'catalog.manage',
        title: 'Цены и статусы',
      },
    },
    {
      path: '/products/combine',
      component: loadRouteView(
        () => import('../views/ProductGroupImportView.vue'),
      ),
      meta: {
        requiresAuth: true,
        requiredPermission: 'imports.manage',
        additionalPermission: 'catalog.manage',
        title: 'Объединить товары',
      },
    },
    {
      path: '/login',
      name: 'login',
      component: loadRouteView(() => import('../views/LoginView.vue')),
      meta: { guestOnly: true, title: 'Вход' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: loadRouteView(() => import('../views/ForgotPasswordView.vue')),
      meta: { guestOnly: true, title: 'Восстановление пароля' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: loadRouteView(() => import('../views/ResetPasswordView.vue')),
      meta: { guestOnly: true, title: 'Сброс пароля' },
    },
    {
      path: '/',
      name: 'dashboard',
      component: loadRouteView(() => import('../views/DashboardView.vue')),
      meta: { requiresAuth: true, title: 'Главная' },
    },
    {
      path: '/profile',
      name: 'profile',
      component: loadRouteView(() => import('../views/ProfileView.vue')),
      meta: { requiresAuth: true, title: 'Мой профиль' },
    },
    {
      path: '/employees',
      name: 'employees',
      component: loadRouteView(() => import('../views/EmployeesView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'admin-users.view',
        title: 'Команда и доступ — Сотрудники',
      },
    },
    {
      path: '/roles',
      name: 'roles',
      component: loadRouteView(() => import('../views/RolesView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'roles.view',
        title: 'Команда и доступ — Роли и права',
      },
    },
    {
      path: '/permissions',
      name: 'permissions',
      component: loadRouteView(() => import('../views/PermissionsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'permissions.view',
        title: 'Команда и доступ — Справочник прав',
      },
    },
    {
      path: '/audit-log',
      name: 'audit-log',
      component: loadRouteView(() => import('../views/AuditLogView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'audit-log.view',
        title: 'Журнал аудита',
      },
    },
    {
      path: '/orders',
      name: 'orders',
      component: loadRouteView(() => import('../views/OrdersView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'orders.view',
        title: 'Заказы',
      },
    },
    {
      path: '/contacts',
      name: 'contacts',
      component: loadRouteView(() => import('../views/ContactsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'contacts.view',
        title: 'Обращения',
      },
    },
    {
      path: '/ui-kit',
      name: 'ui-kit',
      component: loadRouteView(() => import('../views/UiKitView.vue')),
      meta: { requiresAuth: true, title: 'UI-kit' },
    },
    {
      path: '/home-page',
      name: 'home-page',
      redirect: (to) => ({
        path: '/content',
        query: { ...to.query, section: 'pages', page: 'home' },
      }),
      meta: {
        requiresAuth: true,
        requiredPermission: 'content.manage',
        title: 'Главная сайта',
      },
    },
    {
      path: '/content',
      name: 'content',
      component: loadRouteView(() => import('../views/ContentView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'content.manage',
        title: 'Контент',
      },
    },
    {
      path: '/media',
      name: 'media',
      component: loadRouteView(() => import('../views/MediaView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'media.manage',
        title: 'Медиатека',
      },
    },
    {
      path: '/settings',
      name: 'settings',
      component: loadRouteView(() => import('../views/SettingsView.vue')),
      meta: {
        requiresAuth: true,
        requiredPermission: 'settings.manage',
        title: 'Настройки',
      },
    },
  ],
})

router.beforeEach(async (to) => {
  latestNavigation = to
  const auth = useAuthStore()

  if (!auth.initialized) await auth.restoreSession()

  if (to.meta.requiresAuth && !auth.user) return { name: 'login' }
  if (to.meta.guestOnly && auth.user) return { name: 'dashboard' }
  if (
    (to.meta.requiredPermission &&
      !auth.hasPermission(to.meta.requiredPermission as string)) ||
    (to.meta.additionalPermission &&
      !auth.hasPermission(to.meta.additionalPermission as string))
  )
    return { name: 'dashboard' }
})

router.onError((error, to) => {
  if (!(error instanceof RouteViewLoadError)) {
    console.error(error)
    return
  }
  if (to === latestNavigation)
    failedRouteHref.value = router.resolve(to.fullPath).href
})

router.afterEach((to, _from, failure) => {
  if (failure) return
  failedRouteHref.value = undefined
  const pageTitle = to.meta.title as string | undefined
  document.title = pageTitle ? `${pageTitle} | AgatCeramic` : 'AgatCeramic'
})

export default router
