import { mockAdminBaseline } from './adminBaselineApi'
import type { Page } from './fixtures'
import { category, attribute, attributeGroup } from './catalogApi'

const group = { ...attributeGroup, id: 2, name: 'Основные', slug: 'main' }
const dimensions = { ...attributeGroup, name: 'Размеры' }
const makeAttribute = (
  id: number,
  name: string,
  groupId: number,
  required: boolean,
  filter: boolean,
  unit: string | null = null,
) => ({
  ...attribute,
  id,
  name,
  slug: `attribute-${id}`,
  attribute_group_id: groupId,
  unit,
  is_required: required,
  is_filterable: filter,
})
const brand = makeAttribute(1, 'Бренд', 2, true, true)
const rootAttributes = [
  brand,
  makeAttribute(2, 'Коллекция', 2, false, true),
  makeAttribute(3, 'Страна', 2, false, true),
  makeAttribute(4, 'Длина', 1, true, false, 'см'),
  makeAttribute(5, 'Ширина', 1, true, false, 'см'),
]
const floorAttributes = [
  brand,
  makeAttribute(6, 'Поверхность', 2, false, true),
  makeAttribute(7, 'Толщина', 1, true, false, 'мм'),
]
const wallAttributes = [makeAttribute(8, 'Цвет', 2, false, true)]
export const overviewCategories = [
  {
    ...category,
    description: null,
    sort_order: 1,
    children: [
      {
        ...category,
        id: 2,
        parent_id: 1,
        name: 'Для пола',
        slug: 'dlya-pola',
        description: null,
        is_parent: false,
        sort_order: 2,
      },
      {
        ...category,
        id: 3,
        parent_id: 1,
        name: 'Для стен',
        slug: 'dlya-sten',
        description: null,
        is_parent: false,
        is_active: false,
        sort_order: 3,
      },
    ],
  },
]

export async function mockCategoryOverview(page: Page, longText = false) {
  const categoryName = longText
    ? 'Керамогранит для общественных помещений с повышенной проходимостью и декоративными вставками '
        .repeat(3)
        .slice(0, 255)
    : 'Керамогранит'
  const mainGroup = longText
    ? {
        ...group,
        name: 'ТехническиеХарактеристикиМатериалаБезПробеловДляПроверкиПереносовНаУзкомЭкране',
      }
    : group
  const assigned = new Map([
    [1, rootAttributes],
    [2, floorAttributes],
    [3, wallAttributes],
  ])
  const fixtures: Record<string, unknown> = {
    '/admin/categories/overview': {
      data: overviewCategories.map((root) => ({
        ...root,
        name: categoryName,
        slug: longText
          ? 'technical-category-slug-with-a-very-long-unbroken-segment-for-responsive-validation'
          : root.slug,
        attributes: longText
          ? rootAttributes.map((value, index) =>
              index === 0
                ? {
                    ...value,
                    name: 'ОченьДлинноеНазваниеХарактеристикиМатериалаБезПробеловДляПроверкиПереноса',
                  }
                : value,
            )
          : rootAttributes,
        attribute_groups: [mainGroup, dimensions],
        children: root.children.map((child) => ({
          ...child,
          attributes: child.id === 2 ? floorAttributes : wallAttributes,
          attribute_groups: [mainGroup, dimensions],
        })),
      })),
    },
    '/admin/attribute-groups': {
      data: [mainGroup, dimensions],
      meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 },
    },
    '/admin/attributes': {
      data: [...rootAttributes, ...floorAttributes.slice(1), ...wallAttributes],
      meta: { current_page: 1, last_page: 1, per_page: 100, total: 8 },
    },
  }
  await mockAdminBaseline(page, false, 'default', undefined, fixtures)
  await page.route('**/api/v1/admin/categories/*/attributes', async (route) => {
    const id = Number(new URL(route.request().url()).pathname.split('/').at(-2))
    if (route.request().method() === 'PUT') {
      const payload = route.request().postDataJSON() as {
        attributes: { id: number; is_required: boolean }[]
      }
      assigned.set(
        id,
        (assigned.get(id) ?? [])
          .filter((item) =>
            payload.attributes.some((value) => value.id === item.id),
          )
          .map((item) => ({
            ...item,
            is_required:
              payload.attributes.find((value) => value.id === item.id)
                ?.is_required ?? false,
          })),
      )
    }
    await route.fulfill({ json: { data: assigned.get(id) ?? [] } })
  })
  await page.route('**/api/v1/admin/categories/*/attribute-groups', (route) =>
    route.fulfill({ json: { data: [mainGroup, dimensions] } }),
  )
}
