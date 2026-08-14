import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import EmployeesView from '../views/EmployeesView.vue'
import RolesView from '../views/RolesView.vue'
import PermissionsView from '../views/PermissionsView.vue'
import AuditLogView from '../views/AuditLogView.vue'
import CategoriesView from '../views/CategoriesView.vue'
import BrandsView from '../views/BrandsView.vue'
import AttributeGroupsView from '../views/AttributeGroupsView.vue'
import AttributesView from '../views/AttributesView.vue'
import ProfileView from '../views/ProfileView.vue'
import ProductsView from '../views/ProductsView.vue'
import ProductVariantsView from '../views/ProductVariantsView.vue'
import ProductAttributesView from '../views/ProductAttributesView.vue'
import ProductImagesView from '../views/ProductImagesView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/product-images', name: 'product-images', component: ProductImagesView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Product images' } },
    { path: '/product-attributes', name: 'product-attributes', component: ProductAttributesView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Product attributes' } },
    { path: '/product-variants', name: 'product-variants', component: ProductVariantsView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Product variants' } },
    { path: '/brands', name: 'brands', component: BrandsView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Бренды' } },
    { path: '/attributes', name: 'attributes', component: AttributesView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Характеристики' } },
    { path: '/attribute-groups', name: 'attribute-groups', component: AttributeGroupsView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Группы характеристик' } },
    { path: '/categories', name: 'categories', component: CategoriesView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Категории' } },
    { path: '/products', name: 'products', component: ProductsView, meta: { requiresAuth: true, requiredPermission: 'catalog.manage', title: 'Товары' } },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true, title: 'Вход' } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { guestOnly: true, title: 'Восстановление пароля' } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView, meta: { guestOnly: true, title: 'Сброс пароля' } },
    { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'Главная' } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true, title: 'Мой профиль' } },
    { path: '/employees', name: 'employees', component: EmployeesView, meta: { requiresAuth: true, requiredPermission: 'admin-users.view', title: 'Сотрудники' } },
    { path: '/roles', name: 'roles', component: RolesView, meta: { requiresAuth: true, requiredPermission: 'roles.view', title: 'Роли' } },
    { path: '/permissions', name: 'permissions', component: PermissionsView, meta: { requiresAuth: true, requiredPermission: 'permissions.view', title: 'Права доступа' } },
    { path: '/audit-log', name: 'audit-log', component: AuditLogView, meta: { requiresAuth: true, requiredPermission: 'audit-log.view', title: 'Журнал аудита' } },
    { path: '/orders', name: 'orders', component: PlaceholderView, props: { title: 'Заказы' }, meta: { requiresAuth: true, title: 'Заказы' } },
    { path: '/content', name: 'content', component: PlaceholderView, props: { title: 'Контент' }, meta: { requiresAuth: true, title: 'Контент' } },
    { path: '/settings', name: 'settings', component: PlaceholderView, props: { title: 'Настройки' }, meta: { requiresAuth: true, title: 'Настройки' } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) await auth.restoreSession()

  if (to.meta.requiresAuth && !auth.user) return { name: 'login' }
  if (to.meta.guestOnly && auth.user) return { name: 'dashboard' }
  if (to.meta.requiredPermission && !auth.hasPermission(to.meta.requiredPermission as string)) return { name: 'dashboard' }
})

router.afterEach((to) => {
  const pageTitle = to.meta.title as string | undefined
  document.title = pageTitle ? `${pageTitle} — AgatCeramic` : 'AgatCeramic'
})

export default router
