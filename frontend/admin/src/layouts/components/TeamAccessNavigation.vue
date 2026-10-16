<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const rolesPath = computed(() =>
  auth.hasPermission('roles.view')
    ? '/roles'
    : auth.hasPermission('permissions.view')
      ? '/permissions'
      : null,
)
</script>

<template>
  <nav aria-label="Команда и доступ" class="team-access-navigation">
    <RouterLink
      v-if="auth.hasPermission('admin-users.view')"
      to="/employees"
      class="team-access-link admin-focus"
      :class="{ 'team-access-link--active': route.path === '/employees' }"
      :aria-current="route.path === '/employees' ? 'page' : undefined"
      >Сотрудники</RouterLink
    >
    <RouterLink
      v-if="rolesPath"
      :to="rolesPath"
      class="team-access-link admin-focus"
      :class="{ 'team-access-link--active': route.path !== '/employees' }"
      :aria-current="route.path !== '/employees' ? 'page' : undefined"
      >Роли и права</RouterLink
    >
  </nav>
</template>

<style scoped>
.team-access-navigation {
  display: flex;
  flex-wrap: wrap;
  gap: var(--admin-spacing-6);
  margin-top: var(--admin-spacing-6);
  padding-bottom: var(--admin-spacing-1);
}
.team-access-link {
  min-width: 0;
  padding-block: var(--admin-spacing-3);
  border-bottom: 2px solid transparent;
  color: var(--admin-color-gray-500);
  overflow-wrap: anywhere;
}
.team-access-link:hover,
.team-access-link--active {
  color: var(--admin-color-primary-500);
}
.team-access-link--active {
  border-color: var(--admin-color-primary-500);
}
</style>
