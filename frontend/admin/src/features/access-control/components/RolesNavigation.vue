<script setup lang="ts">
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { AccessRole } from '../types/access.types'

defineProps<{
  roles: AccessRole[]
  search: string
  selectedId: number | null
}>()
defineEmits<{ 'update:search': [value: string]; select: [id: number] }>()
</script>

<template>
  <h2 class="mb-4 text-xl font-bold text-gray-500">Роли</h2>
  <UiInput
    :model-value="search"
    searchable
    type="search"
    aria-label="Поиск ролей"
    placeholder="Найти роль"
    class="mb-4"
    @update:model-value="$emit('update:search', $event)"
  />
  <nav aria-label="Выбор роли">
    <ul class="space-y-1">
      <li v-for="role in roles" :key="role.id">
        <UiButton
          variant="surface"
          class="role-nav-button"
          :class="{ 'role-nav-button--selected': selectedId === role.id }"
          :aria-pressed="selectedId === role.id"
          @click="$emit('select', role.id)"
        >
          <span class="min-w-0 break-words [overflow-wrap:anywhere]">{{
            role.name
          }}</span>
          <UiBadge v-if="role.is_system" tone="neutral">Системная</UiBadge>
        </UiButton>
      </li>
    </ul>
    <p v-if="!roles.length" role="status" class="py-3 text-sm text-gray-500">
      Роли не найдены. Попробуйте другой запрос.
    </p>
  </nav>
  <slot />
</template>

<style scoped>
.role-nav-button {
  width: 100%;
  flex-wrap: wrap;
  justify-content: flex-start;
  text-align: left;
  font-weight: 400;
}
.role-nav-button--selected {
  background: var(--admin-color-primary-50);
  color: var(--admin-color-primary-500);
}
</style>
