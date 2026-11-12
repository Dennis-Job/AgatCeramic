import type { StandaloneCatalog } from '../types/catalog'

export interface CatalogCategoryNode {
  category: StandaloneCatalog['categories'][number]
  children: CatalogCategoryNode[]
}

export function buildCategoryTree(
  categories: StandaloneCatalog['categories'],
): CatalogCategoryNode[] {
  const categoriesById = new Map(
    categories.map((category) => [category.id, category]),
  )
  const childrenById = new Map<number, StandaloneCatalog['categories']>()
  for (const category of categories) {
    if (category.parent_id == null) continue
    const children = childrenById.get(category.parent_id) ?? []
    children.push(category)
    childrenById.set(category.parent_id, children)
  }
  const seen = new Set<number>()
  function visit(
    category: StandaloneCatalog['categories'][number],
  ): CatalogCategoryNode | undefined {
    if (seen.has(category.id)) return
    seen.add(category.id)
    const children: CatalogCategoryNode[] = []
    for (const child of childrenById.get(category.id) ?? []) {
      const node = visit(child)
      if (node) children.push(node)
    }
    return { category, children }
  }
  const roots: CatalogCategoryNode[] = []
  for (const category of categories) {
    if (category.parent_id != null && categoriesById.has(category.parent_id))
      continue
    const node = visit(category)
    if (node) roots.push(node)
  }
  // Retain disconnected cycles as finite branches instead of dropping categories.
  for (const category of categories) {
    const node = visit(category)
    if (node) roots.push(node)
  }
  return roots
}

export function categoryBranchContains(
  node: CatalogCategoryNode,
  slug?: string,
): boolean {
  return (
    !!slug &&
    (node.category.slug === slug ||
      node.children.some((child) => categoryBranchContains(child, slug)))
  )
}

export function flattenCategoryTree(
  nodes: CatalogCategoryNode[],
): StandaloneCatalog['categories'] {
  return nodes.flatMap((node) => [
    node.category,
    ...flattenCategoryTree(node.children),
  ])
}
