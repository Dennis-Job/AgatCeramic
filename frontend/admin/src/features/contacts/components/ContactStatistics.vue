<script setup lang="ts">
import { computed } from 'vue'
import AdminMonthlyStatistics from '../../../components/shared/AdminMonthlyStatistics.vue'
import { useContactStatistics } from '../composables/useContactStatistics'
const { selectedMonth, statistics, loading, error, refresh } =
  useContactStatistics()
const labels = [
  { code: 'partner', label: 'Заявки от партнёров' },
  { code: 'callback', label: 'Заявки перезвонить' },
  { code: 'completed', label: 'Завершено' },
  { code: 'rejected', label: 'Отклонено' },
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
    label="Статистика обращений"
    loading-label="Загрузка статистики обращений…"
    :loading="loading"
    :error="error"
    :metrics="metrics"
    :previous-month="statistics?.previous_month"
    @refresh="refresh"
  />
</template>
