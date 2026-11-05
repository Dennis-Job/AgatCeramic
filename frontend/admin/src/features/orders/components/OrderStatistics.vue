<script setup lang="ts">
import { computed } from 'vue'
import AdminMonthlyStatistics from '../../../components/shared/AdminMonthlyStatistics.vue'
import { useOrderStatistics } from '../composables/useOrderStatistics'
const { selectedMonth, statistics, loading, error, refresh } =
  useOrderStatistics()
const labels = [
  { code: 'total', label: 'Всего заказов' },
  { code: 'new', label: 'Новые' },
  { code: 'completed', label: 'Завершено' },
  { code: 'cancelled', label: 'Отменено' },
] as const
const metrics = computed(() =>
  statistics.value
    ? labels.map(({ code, label }) => ({
        code,
        label,
        count: statistics.value!.metrics[code].count,
        previousCount: statistics.value!.metrics[code].previous_count,
        changePercent: statistics.value!.metrics[code].change_percent,
      }))
    : null,
)
</script>

<template>
  <AdminMonthlyStatistics
    v-model="selectedMonth"
    label="Статистика заказов"
    loading-label="Загрузка статистики заказов…"
    :loading="loading"
    :error="error"
    :metrics="metrics"
    :previous-month="statistics?.previous_month"
    @refresh="refresh"
  />
</template>
