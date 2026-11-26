<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2 } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiProgressBar from '../../../components/ui/UiProgressBar.vue'

const props = defineProps<{
  titleId: string
  visible: boolean
  statusText: string
  jobStatus?: 'pending' | 'processing' | 'completed' | 'failed'
  failureCount?: number
  progress: number | undefined
  progressLabel: string
  busy: boolean
  pollingError: boolean
}>()
defineEmits<{ retry: [] }>()

const outcome = computed(() => {
  if (props.jobStatus === 'failed') return 'failed'
  if (props.jobStatus !== 'completed') return undefined
  return props.failureCount ? 'partial' : 'success'
})
</script>

<template>
  <section
    class="admin-panel--state min-h-32 p-4 sm:p-5"
    :aria-labelledby="titleId"
    :class="
      outcome === 'success'
        ? 'border-success-200 bg-success-50'
        : outcome
          ? 'border-warning-200 bg-warning-50'
          : 'border-gray-200 bg-gray-25'
    "
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 :id="titleId" class="text-base font-semibold text-gray-500">
        Статус импорта
      </h2>
      <span
        data-import-progress-label
        class="text-sm font-semibold tabular-nums text-gray-500"
        aria-hidden="true"
      >
        {{ progress === undefined ? 'Выполняется…' : `${progress}%` }}
      </span>
    </div>
    <div role="status" :aria-live="visible ? 'polite' : 'off'">
      <template v-if="outcome">
        <h3 class="mt-2 flex items-center gap-2 font-semibold text-gray-500">
          <CheckCircle2
            v-if="outcome === 'success'"
            :size="20"
            class="shrink-0 text-success-500"
            aria-hidden="true"
          />
          {{
            outcome === 'failed'
              ? 'Загрузка не завершена'
              : outcome === 'partial'
                ? 'Загрузка завершена с ошибками'
                : 'Загрузка завершена'
          }}
        </h3>
        <slot name="summary" />
      </template>
      <p v-else class="mt-2 text-sm text-gray-500">{{ statusText }}</p>
    </div>
    <slot v-if="outcome" name="details" />
    <template v-else>
      <p v-if="busy" class="mt-3 text-xs text-gray-500">
        Можно перейти на другую страницу — обработка продолжится. Вернитесь в
        «Товары» → «Добавить массово товары», чтобы посмотреть результат.
      </p>
      <UiAlert v-if="visible && pollingError" class="mt-3">
        Не удалось получить статус. Обработка на сервере продолжается.
        <UiButton
          type="button"
          variant="danger-ghost"
          size="sm"
          @click="$emit('retry')"
        >
          Обновить статус
        </UiButton>
      </UiAlert>
    </template>
    <UiProgressBar
      class="mt-4"
      :label="progressLabel"
      :value="progress"
      show-scale
    />
  </section>
</template>
