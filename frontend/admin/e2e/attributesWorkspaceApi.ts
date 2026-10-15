import type { Page } from './fixtures'
import { attribute, attributeGroup, mockCatalogApi } from './catalogApi'
import type { Attribute } from '../src/features/attributes/types/attribute.types'
import type { AttributeGroup } from '../src/features/attribute-groups/types/attributeGroup.types'

type WorkspaceOptions = { longText?: boolean; empty?: boolean }
type RecordedPatch = { id: number; body: Record<string, unknown> }

function paginated<T>(items: T[], url: URL) {
  // Deliberately return multiple pages even for per_page=100: the workspace must
  // load the entire reference catalog before computing group counts/search.
  const currentPage = Number(url.searchParams.get('page') ?? '1')
  const perPage = 2
  const data = items.slice((currentPage - 1) * perPage, currentPage * perPage)
  return {
    data,
    meta: {
      current_page: currentPage,
      last_page: Math.max(1, Math.ceil(items.length / perPage)),
      per_page: perPage,
      total: items.length,
      from: data.length ? (currentPage - 1) * perPage + 1 : null,
      to: data.length ? (currentPage - 1) * perPage + data.length : null,
    },
  }
}

export async function mockAttributesWorkspace(
  page: Page,
  options: WorkspaceOptions = {},
) {
  await mockCatalogApi(page)
  const groups: AttributeGroup[] = options.empty
    ? []
    : [
        { ...attributeGroup },
        {
          ...attributeGroup,
          id: 2,
          name: 'Внешний вид',
          slug: 'appearance',
          description: 'Цвет и рисунок поверхности',
          sort_order: 20,
        },
        {
          ...attributeGroup,
          id: 3,
          name: 'Пустая группа',
          slug: 'empty-group',
          description: null,
          sort_order: 30,
        },
      ]
  const attributes: Attribute[] = options.empty
    ? []
    : [
        { ...attribute, type: 'decimal' },
        { ...attribute, id: 2, name: 'Длина', slug: 'length', type: 'decimal' },
        {
          ...attribute,
          id: 3,
          name: 'Цвет',
          slug: 'color',
          attribute_group_id: 2,
          type: 'string',
          unit: null,
          is_filterable: false,
        },
        {
          ...attribute,
          id: 4,
          name: 'Рисунок',
          slug: 'pattern',
          attribute_group_id: 2,
          type: 'select',
          unit: null,
          options: [
            { value: 'stone', label: 'Камень', sort_order: 0 },
            { value: 'wood', label: 'Дерево', sort_order: 1 },
          ],
        },
        {
          ...attribute,
          id: 5,
          name: options.longText
            ? 'ДополнительныеТехническиеХарактеристикиМатериалаБезПробеловДляПроверкиПереноса'
            : 'Примечание',
          slug: 'additional-technical-material-description-with-a-long-unbroken-segment',
          attribute_group_id: null,
          type: 'text',
          unit: null,
          is_filterable: false,
          is_visible_on_product_page: false,
        },
      ]
  const patches: RecordedPatch[] = []
  let patchError = ''
  let patchGate: Promise<void> | undefined

  await page.route(
    /\/api\/v1\/admin\/attribute(?:s|-groups)(?:[/?].*)?$/,
    async (route) => {
      const request = route.request()
      const url = new URL(request.url())
      const path = url.pathname.replace('/api/v1', '')
      const method = request.method()
      if (path === '/admin/attributes' && method === 'GET') {
        await route.fulfill({ json: paginated(attributes, url) })
        return
      }
      if (path === '/admin/attribute-groups' && method === 'GET') {
        await route.fulfill({ json: paginated(groups, url) })
        return
      }
      if (path === '/admin/attribute-groups' && method === 'POST') {
        const created = {
          ...attributeGroup,
          ...(request.postDataJSON() as Partial<AttributeGroup>),
          id: Math.max(0, ...groups.map((item) => item.id)) + 1,
        }
        groups.push(created)
        await route.fulfill({ status: 201, json: { data: created } })
        return
      }
      if (path === '/admin/attributes' && method === 'POST') {
        const created: Attribute = {
          ...attribute,
          type: 'string',
          ...(request.postDataJSON() as Partial<Attribute>),
          id: Math.max(0, ...attributes.map((item) => item.id)) + 1,
        }
        attributes.push(created)
        await route.fulfill({ status: 201, json: { data: created } })
        return
      }
      const attributeId = path.match(/^\/admin\/attributes\/(\d+)$/)?.[1]
      if (attributeId && method === 'PATCH') {
        const id = Number(attributeId)
        const body = request.postDataJSON() as Record<string, unknown>
        patches.push({ id, body })
        const gate = patchGate
        patchGate = undefined
        await gate
        if (patchError) {
          const message = patchError
          patchError = ''
          await route.fulfill({ status: 500, json: { error: { message } } })
          return
        }
        const index = attributes.findIndex((item) => item.id === id)
        const current = attributes[index]
        if (!current) throw new Error(`Unknown fixture attribute ${id}`)
        const updated = { ...current, ...body } as Attribute
        attributes[index] = updated
        await route.fulfill({ json: { data: updated } })
        return
      }
      await route.fallback()
    },
  )

  return {
    attributes,
    groups,
    patches,
    failNextPatch(message = 'Не удалось сохранить настройку характеристики.') {
      patchError = message
    },
    pauseNextPatch() {
      let release!: () => void
      patchGate = new Promise<void>((resolve) => {
        release = resolve
      })
      return release
    },
  }
}
