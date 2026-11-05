<script setup lang="ts">
import UiMonthPicker from '../ui/UiMonthPicker.vue'
import UiBadge from '../ui/UiBadge.vue'
import UiAlert from '../ui/UiAlert.vue'
import UiButton from '../ui/UiButton.vue'
import UiLoadingState from '../ui/UiLoadingState.vue'
import { formatMonth } from '../../utils/month'

defineProps<{
  label: string
  loadingLabel: string
  loading: boolean
  error: string
  previousMonth?: string
  metrics:
    | {
        code: string
        label: string
        count: number
        previousCount: number
        changePercent: number | null
      }[]
    | null
}>()
const selectedMonth = defineModel<string>({ required: true })
defineEmits<{ refresh: [] }>()
const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 })
function percent(value: number | null): string {
  if (value === null) return 'Нет базы'
  return `${value > 0 ? '+' : ''}${number.format(value)}%`
}
function tone(value: number | null) {
  if (value === null || value === 0) return 'neutral' as const
  return value > 0 ? ('success' as const) : ('danger' as const)
}
</script>

<template>
  <section
    class="admin-container contact-statistics"
    :aria-label="label"
    :aria-busy="loading"
  >
    <div class="contact-statistics-heading">
      <h2 class="text-lg font-bold text-gray-500">Статистика за месяц</h2>
      <UiMonthPicker
        v-model="selectedMonth"
        accessible-name="Месяц статистики"
      />
    </div>
    <UiLoadingState v-if="loading" :label="loadingLabel" />
    <div v-else-if="error" class="space-y-3">
      <UiAlert>{{ error }}</UiAlert>
      <UiButton variant="secondary" @click="$emit('refresh')"
        >Повторить загрузку статистики</UiButton
      >
    </div>
    <div v-else-if="metrics" class="contact-statistics-grid">
      <article
        v-for="metric in metrics"
        :key="metric.code"
        class="contact-statistic"
        :data-contact-metric="metric.code"
        :data-statistic-metric="metric.code"
      >
        <h3 class="text-sm text-gray-500">{{ metric.label }}</h3>
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <p class="min-w-0 break-words text-3xl font-bold text-gray-500">
            {{ number.format(metric.count) }}
          </p>
          <UiBadge
            :tone="tone(metric.changePercent)"
            class="shrink-0 whitespace-nowrap"
            :title="`Предыдущий месяц${previousMonth ? ' (' + formatMonth(previousMonth) + ')' : ''}: ${number.format(metric.previousCount)}`"
            >{{ percent(metric.changePercent) }}</UiBadge
          >
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.contact-statistics {
  margin-bottom: var(--admin-spacing-6);
}
.contact-statistics-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-3);
  margin-bottom: var(--admin-spacing-3);
}
.contact-statistics-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  border: var(--admin-border-width) solid var(--admin-color-gray-200);
  border-radius: var(--admin-radius-xl);
  overflow: hidden;
}
.contact-statistics-grid .contact-statistic {
  min-width: 0;
  padding: var(--admin-spacing-6);
}
.contact-statistics-grid .contact-statistic + .contact-statistic {
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
}
@media (min-width: 640px) {
  .contact-statistics-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .contact-statistics-grid .contact-statistic + .contact-statistic {
    border-top: 0;
  }
  .contact-statistics-grid .contact-statistic:nth-child(2n) {
    border-left: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
  .contact-statistics-grid .contact-statistic:nth-child(n + 3) {
    border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
}
@media (min-width: 1024px) {
  .contact-statistics-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .contact-statistics-grid .contact-statistic:nth-child(n + 3) {
    border-top: 0;
  }
  .contact-statistics-grid .contact-statistic + .contact-statistic {
    border-left: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
}
</style>
