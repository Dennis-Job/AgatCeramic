<script setup lang="ts">
import { useRoute } from 'vue-router'
import { isNavigationLinkActive, type NavigationGroup } from '../navigation'
import UiNotificationCount from '../../components/ui/UiNotificationCount.vue'

withDefaults(
  defineProps<{
    groups: NavigationGroup[]
    label: string
    counts?: Record<string, number>
  }>(),
  { counts: () => ({}) },
)
defineEmits<{ select: [] }>()
const route = useRoute()
</script>

<template>
  <nav :aria-label="label" class="admin-navigation-groups">
    <div
      v-for="(group, index) in groups"
      :key="`${group.label}-${index}`"
      class="min-w-0"
    >
      <h3 v-if="group.label" class="mb-3 px-3 text-sm font-bold text-gray-500">
        {{ group.label }}
      </h3>
      <RouterLink
        v-for="link in group.links"
        :key="link.label"
        :to="link.to"
        class="admin-nav-link"
        :class="{ 'is-active': isNavigationLinkActive(link, route) }"
        :aria-current="isNavigationLinkActive(link, route) ? 'page' : undefined"
        :aria-description="
          counts[link.path.slice(1)]
            ? `Новых ${link.path === '/contacts' ? 'обращений' : 'заказов'}: ${counts[link.path.slice(1)]}`
            : undefined
        "
        @click="$emit('select')"
        >{{ link.label
        }}<UiNotificationCount
          class="ml-2"
          :count="counts[link.path.slice(1)] ?? 0"
      /></RouterLink>
    </div>
  </nav>
</template>
