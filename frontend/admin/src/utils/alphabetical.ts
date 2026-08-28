const russianCollator = new Intl.Collator('ru-RU', {
  numeric: true,
  sensitivity: 'base',
})

export function compareAlphabetically(left: string, right: string): number {
  return russianCollator.compare(left.trim(), right.trim())
}

export function sortByLabel<T extends { label: string }>(items: readonly T[]): T[] {
  return [...items].sort((left, right) => compareAlphabetically(left.label, right.label))
}
