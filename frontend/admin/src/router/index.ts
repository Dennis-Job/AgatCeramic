import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import EmployeesView from '../views/EmployeesView.vue'
import RolesView from '../views/RolesView.vue'
import PermissionsView from '../views/PermissionsView.vue'
import AuditLogView from '../views/AuditLogView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/employees', name: 'employees', component: EmployeesView, meta: { requiresAuth: true, requiredPermission: 'admin-users.view' } },
    { path: '/roles', name: 'roles', component: RolesView, meta: { requiresAuth: true, requiredPermission: 'roles.view' } },
    { path: '/permissions', name: 'permissions', component: PermissionsView, meta: { requiresAuth: true, requiredPermission: 'permissions.view' } },
    { path: '/audit-log', name: 'audit-log', component: AuditLogView, meta: { requiresAuth: true, requiredPermission: 'audit-log.view' } },
    { path: '/products', name: 'products', component: PlaceholderView, props: { title: 'Каталог' }, meta: { requiresAuth: true } },
    { path: '/orders', name: 'orders', component: PlaceholderView, props: { title: 'Заказы' }, meta: { requiresAuth: true } },
    { path: '/content', name: 'content', component: PlaceholderView, props: { title: 'Контент' }, meta: { requiresAuth: true } },
    { path: '/settings', name: 'settings', component: PlaceholderView, props: { title: 'Настройки' }, meta: { requiresAuth: true } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) await auth.restoreSession()

  if (to.meta.requiresAuth && !auth.user) return { name: 'login' }
  if (to.meta.guestOnly && auth.user) return { name: 'dashboard' }
  if (to.meta.requiredPermission && !auth.hasPermission(to.meta.requiredPermission as string)) return { name: 'dashboard' }
})

export default router
