<script setup lang="ts">
import { computed } from 'vue'
import UiMonthPicker from '../../../components/ui/UiMonthPicker.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { currentMoscowMonth, formatMonth } from '../../../utils/month'
import { useContactStatistics } from '../composables/useContactStatistics'
import type { ContactStatisticMetric } from '../types/contact.types'

const { selectedMonth, statistics, loading, error, refresh } =
  useContactStatistics()
const metrics = [
  { code: 'partner', label: 'Заявки от партнёров' },
  { code: 'callback', label: 'Заявки перезвонить' },
  { code: 'completed', label: 'Завершено' },
  { code: 'rejected', label: 'Отклонено' },
] as const
const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 })
const periodNote = computed(() =>
  selectedMonth.value === currentMoscowMonth()
    ? 'Текущий месяц ещё не завершён.'
    : selectedMonth.value > currentMoscowMonth()
      ? 'Выбранный месяц ещё не начался.'
      : '',
)
function percent(metric: ContactStatisticMetric): string {
  if (metric.change_percent === null) return 'Нет базы'
  return `${metric.change_percent > 0 ? '+' : ''}${number.format(metric.change_percent)}%`
}
function tone(metric: ContactStatisticMetric) {
  if (metric.change_percent === null || metric.change_percent === 0)
    return 'neutral' as const
  return metric.change_percent > 0 ? ('success' as const) : ('danger' as const)
}
</script>

<template>
  <section
    class="admin-container contact-statistics"
    aria-label="Статистика обращений"
    :aria-busy="loading"
  >
    <div class="contact-statistics-heading">
      <h2 class="text-lg font-bold text-gray-500">Статистика за месяц</h2>
      <UiMonthPicker
        v-model="selectedMonth"
        accessible-name="Месяц статистики"
      />
    </div>
    <p class="mb-4 text-xs text-gray-500">
      По дате поступления. «Завершено» и «Отклонено» — текущие статусы.
      {{ periodNote }}
    </p>
    <UiLoadingState v-if="loading" label="Загрузка статистики обращений…" />
    <div v-else-if="error" class="space-y-3">
      <UiAlert>{{ error }}</UiAlert>
      <UiButton variant="secondary" @click="refresh"
        >Повторить загрузку статистики</UiButton
      >
    </div>
    <template v-else-if="statistics">
      <div class="contact-statistics-grid">
        <article
          v-for="metric in metrics"
          :key="metric.code"
          class="contact-statistic"
          :data-contact-metric="metric.code"
        >
          <h3 class="text-sm text-gray-500">{{ metric.label }}</h3>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <p class="min-w-0 break-words text-3xl font-bold text-gray-500">
              {{ number.format(statistics.metrics[metric.code].count) }}
            </p>
            <UiBadge
              :tone="tone(statistics.metrics[metric.code])"
              class="shrink-0 whitespace-nowrap"
              :title="`Предыдущий месяц: ${number.format(statistics.metrics[metric.code].previous_count)}`"
              >{{ percent(statistics.metrics[metric.code]) }}</UiBadge
            >
          </div>
        </article>
      </div>
      <p class="mt-3 text-xs text-gray-500">
        Предыдущий месяц: {{ formatMonth(statistics.previous_month) }}. «Нет
        базы» — в предыдущем месяце значение было равно нулю.
      </p>
    </template>
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
