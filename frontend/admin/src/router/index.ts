import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardView },
    { path: '/products', name: 'products', component: PlaceholderView, props: { title: 'Каталог' } },
    { path: '/orders', name: 'orders', component: PlaceholderView, props: { title: 'Заказы' } },
    { path: '/content', name: 'content', component: PlaceholderView, props: { title: 'Контент' } },
    { path: '/settings', name: 'settings', component: PlaceholderView, props: { title: 'Настройки' } },
  ],
})

export default router
