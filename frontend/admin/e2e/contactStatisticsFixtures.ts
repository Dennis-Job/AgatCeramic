export const contactStatistics = (month: string, empty = false) => {
  const [year, monthNumber] = month.split('-').map(Number)
  const previous = new Date(Date.UTC(year!, monthNumber! - 2, 1))
  const previousMonth = `${previous.getUTCFullYear()}-${String(previous.getUTCMonth() + 1).padStart(2, '0')}`
  const metric = (count: number, previousCount: number) => ({
    count: empty ? 0 : count,
    previous_count: empty ? 0 : previousCount,
    change_percent: empty
      ? 0
      : previousCount === 0
        ? count === 0
          ? 0
          : null
        : Math.round(((count - previousCount) / previousCount) * 1000) / 10,
  })
  return {
    month,
    previous_month: previousMonth,
    timezone: 'Europe/Moscow',
    metrics: {
      partner: metric(15, 10),
      callback: metric(30, 40),
      completed: metric(12, 0),
      rejected: metric(0, 0),
    },
  }
}
