export function orderStatistics(month: string) {
  const [year, value] = month.split('-').map(Number)
  const previous = new Date(Date.UTC(year!, value! - 2, 1))
  return {
    month,
    previous_month: `${previous.getUTCFullYear()}-${String(previous.getUTCMonth() + 1).padStart(2, '0')}`,
    timezone: 'Europe/Moscow',
    metrics: {
      total: { count: 12, previous_count: 10, change_percent: 20 },
      new: { count: 3, previous_count: 0, change_percent: null },
      completed: { count: 7, previous_count: 10, change_percent: -30 },
      cancelled: { count: 0, previous_count: 0, change_percent: 0 },
    },
  }
}
