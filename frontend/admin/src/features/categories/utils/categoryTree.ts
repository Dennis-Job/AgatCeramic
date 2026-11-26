import type { Category } from '../types/category.types'

export function flattenCategoryTree(
  categories: readonly Category[],
  compareSiblings?: (left: Category, right: Category) => number,
): Category[] {
  const flattened: Category[] = []

  function visit(siblings: readonly Category[]): void {
    const ordered = compareSiblings
      ? [...siblings].sort(compareSiblings)
      : siblings
    for (const category of ordered) {
      flattened.push(category)
      if (category.children) visit(category.children)
    }
  }

  visit(categories)
  return flattened
}

export function getCategoryDescendantIds(category: Category): number[] {
  return flattenCategoryTree(category.children ?? []).map(({ id }) => id)
}
