<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import type { PaginationMeta } from '../services/pagination'

const props = withDefaults(defineProps<{ meta: PaginationMeta; loading?: boolean; announce?: boolean }>(), { announce: true })
const emit = defineEmits<{ change: [page: number] }>()

const range = computed(() => {
  const start = props.meta.from ?? (props.meta.total === 0 ? 0 : (props.meta.current_page - 1) * props.meta.per_page + 1)
  const end = props.meta.to ?? Math.min(props.meta.current_page * props.meta.per_page, props.meta.total)
  return `${start}–${end} из ${props.meta.total}`
})
</script>

<template>
  <nav v-if="meta.total > 0" class="mt-4 flex flex-wrap items-center justify-between gap-3" :aria-label="`Пагинация: страница ${meta.current_page} из ${meta.last_page}`">
    <p class="text-sm text-gray-500" :role="announce ? 'status' : undefined" :aria-live="announce ? 'polite' : undefined">Показано {{ range }}</p>
    <div class="flex items-center gap-2">
      <button class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="loading || meta.current_page <= 1" aria-label="Предыдущая страница" @click="emit('change', meta.current_page - 1)">
        <ChevronLeft :size="17" />
      </button>
      <span class="text-sm font-medium text-gray-600" :aria-label="`Текущая страница ${meta.current_page} из ${meta.last_page}`">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="loading || meta.current_page >= meta.last_page" aria-label="Следующая страница" @click="emit('change', meta.current_page + 1)"><ChevronRight :size="17" />
      </button>
    </div>
  </nav>
</template>
