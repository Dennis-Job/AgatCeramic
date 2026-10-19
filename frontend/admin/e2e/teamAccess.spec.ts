import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import type {
  AccessRole,
  Permission,
} from '../src/features/access-control/types/access.types'
import type { Employee } from '../src/features/employees/types/employee.types'
import type { Locator } from '@playwright/test'

async function checkWithKeyboard(checkbox: Locator): Promise<void> {
  await checkbox.focus()
  await checkbox.press('Space')
  await expect(checkbox).toBeChecked()
}

const grantedPermissions = [
  'admin-users.view',
  'admin-users.manage',
  'roles.view',
  'roles.manage',
  'permissions.view',
]
const permissionFixtures: Permission[] = [
  {
    id: 1,
    name: 'Просмотр заказов',
    code: 'orders.view',
    description: 'Просмотр списка и состава заказов.',
  },
  {
    id: 2,
    name: 'Управление каталогом',
    code: 'catalog.manage',
    description: 'Редактирование товаров и справочников.',
  },
]
const roleFixtures: AccessRole[] = [
  {
    id: 1,
    name: 'Менеджер магазина',
    slug: 'store-manager',
    description: 'Обрабатывает заказы магазина.',
    is_system: false,
    permissions: [permissionFixtures[0]!],
  },
  {
    id: 2,
    name: 'Системная роль',
    slug: 'system-role',
    description: 'Базовый доступ.',
    is_system: true,
    permissions: [...permissionFixtures],
  },
]
const employeeFixtures: Employee[] = [
  {
    id: 2,
    name: 'Марина Тестовая',
    email: 'marina@example.test',
    status: 'active',
    last_login_at: '2026-10-01T10:00:00Z',
    roles: [roleFixtures[0]!],
  },
  {
    id: 3,
    name: 'Иван Тестовый',
    email: 'ivan@example.test',
    status: 'blocked',
    last_login_at: null,
    roles: [],
  },
]

async function mockTeamAccess(
  page: Page,
  permissions = grantedPermissions,
  longData = false,
) {
  const roles = structuredClone(roleFixtures)
  const employees = structuredClone(employeeFixtures)
  const catalogue = structuredClone(permissionFixtures)
  const mutations: {
    method: string
    path: string
    body: Record<string, unknown> | null
  }[] = []
  const reads: string[] = []
  if (longData) {
    const longName =
      'Ответственный за каталог и оформление заказов магазина с длинным русским названием'
    const longCode =
      'catalog.permission.with.a.very.long.unbroken.identifier'.repeat(3)
    roles[0] = {
      ...roles[0]!,
      name: longName,
      slug: longCode,
      description: longName.repeat(3),
    }
    employees[0] = {
      ...employees[0]!,
      name: longName,
      email: `${longCode}@example.test`,
      roles: [roles[0]!, roles[1]!],
    }
    catalogue[0] = {
      ...catalogue[0]!,
      name: longName,
      code: longCode,
      description: longName.repeat(3),
    }
    roles[0].permissions = [catalogue[0]!]
  }
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname.replace('/api/v1', '')
    const method = request.method()
    const body =
      method === 'GET' || method === 'DELETE'
        ? null
        : (request.postDataJSON() as Record<string, unknown>)
    if (method === 'GET') reads.push(path)
    else mutations.push({ method, path, body })
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 99,
            name: 'Тестовый наблюдатель',
            email: 'observer@example.test',
            status: 'active',
            permissions,
          },
        },
      })
    if (path === '/admin/users' && method === 'GET') {
      const search = (url.searchParams.get('search') ?? '').toLocaleLowerCase(
        'ru',
      )
      const status = url.searchParams.get('status')
      const data = employees.filter(
        (employee) =>
          (!search ||
            `${employee.name} ${employee.email}`
              .toLocaleLowerCase('ru')
              .includes(search)) &&
          (!status || employee.status === status),
      )
      return route.fulfill({
        json: {
          data,
          meta: { current_page: 1, last_page: 1, total: data.length },
        },
      })
    }
    if (
      path === '/admin/users/roles' ||
      (path === '/admin/roles' && method === 'GET')
    )
      return route.fulfill({ json: { data: roles } })
    if (path === '/admin/roles/permissions' || path === '/admin/permissions')
      return route.fulfill({
        json: {
          data: catalogue.map((permission) => ({
            ...permission,
            roles: roles.filter((role) =>
              role.permissions.some((item) => item.id === permission.id),
            ),
          })),
        },
      })
    if (path === '/admin/users' && method === 'POST') {
      const employee: Employee = {
        id: 4,
        name: String(body!.name),
        email: String(body!.email),
        status: body!.status as Employee['status'],
        last_login_at: null,
        roles: roles.filter((role) =>
          (body!.role_ids as number[]).includes(role.id),
        ),
      }
      employees.push(employee)
      return route.fulfill({ status: 201, json: { data: employee } })
    }
    if (/^\/admin\/users\/\d+$/.test(path)) {
      const index = employees.findIndex(
        (employee) => employee.id === Number(path.split('/').at(-1)),
      )
      if (method === 'DELETE') {
        employees.splice(index, 1)
        return route.fulfill({ status: 204 })
      }
      if (method === 'PATCH') {
        employees[index] = {
          ...employees[index]!,
          name: String(body!.name),
          email: String(body!.email),
          status: body!.status as Employee['status'],
          roles: roles.filter((role) =>
            (body!.role_ids as number[]).includes(role.id),
          ),
        }
        return route.fulfill({ json: { data: employees[index] } })
      }
    }
    if (path === '/admin/roles' && method === 'POST') {
      const role: AccessRole = {
        id: 3,
        name: String(body!.name),
        slug: String(body!.slug),
        description: String(body!.description),
        is_system: false,
        permissions: catalogue.filter((permission) =>
          (body!.permission_ids as number[]).includes(permission.id),
        ),
      }
      roles.push(role)
      return route.fulfill({ status: 201, json: { data: role } })
    }
    if (/^\/admin\/roles\/\d+$/.test(path)) {
      const index = roles.findIndex(
        (role) => role.id === Number(path.split('/').at(-1)),
      )
      if (method === 'DELETE') {
        roles.splice(index, 1)
        return route.fulfill({ status: 204 })
      }
      if (method === 'PATCH') {
        if (
          roles[index]!.is_system &&
          (Object.hasOwn(body!, 'name') || Object.hasOwn(body!, 'slug'))
        ) {
          return route.fulfill({
            status: 422,
            json: {
              error: {
                message: 'System role name and slug cannot be changed.',
              },
            },
          })
        }
        roles[index] = {
          ...roles[index]!,
          ...body,
          permissions: catalogue.filter((permission) =>
            (body!.permission_ids as number[]).includes(permission.id),
          ),
        }
        return route.fulfill({ json: { data: roles[index] } })
      }
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: `Unhandled ${path}` } },
    })
  })
  return { mutations, reads, employees, roles }
}

test('team access keeps existing URLs within one local navigation', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await mockTeamAccess(page)
  for (const path of ['/employees', '/roles', '/permissions']) {
    await page.goto(path)
    await expect(
      page.getByRole('heading', {
        level: 1,
        name: 'Команда и доступ',
        exact: true,
      }),
    ).toBeVisible()
    const navigation = page.getByRole('navigation', {
      name: 'Команда и доступ',
      exact: true,
    })
    await expect(
      navigation.getByRole('link', { name: 'Сотрудники', exact: true }),
    ).toHaveAttribute('href', '/employees')
    await expect(
      navigation.getByRole('link', { name: 'Роли и права', exact: true }),
    ).toHaveAttribute('href', '/roles')
    await expect(navigation.locator('[aria-current="page"]')).toHaveCount(1)
    await expect(
      page.getByRole('status').filter({ hasText: 'Загрузка' }),
    ).toHaveCount(0)
    await page.evaluate(() => document.fonts.ready)
    await page.screenshot({
      path: `.tmp/team-access/${path.slice(1)}-default-1440.png`,
      fullPage: true,
      animations: 'disabled',
    })
  }
})

test('employee cards preserve search, multiple roles and CRUD payloads', async ({
  page,
}) => {
  const api = await mockTeamAccess(page)
  await page.goto('/employees')
  const region = page.getByRole('region', {
    name: 'Список сотрудников',
    exact: true,
  })
  await expect(region.locator('table')).toHaveCount(0)
  await expect(
    region.getByRole('heading', { name: 'Марина Тестовая', exact: true }),
  ).toBeVisible()
  await page
    .getByLabel('Поиск сотрудников', { exact: true })
    .fill('marina@example.test')
  await page.getByRole('button', { name: 'Найти', exact: true }).click()
  await expect(region.getByText('Иван Тестовый', { exact: true })).toHaveCount(
    0,
  )
  await page.getByLabel('Поиск сотрудников', { exact: true }).fill('')
  await page.getByRole('button', { name: 'Найти', exact: true }).click()
  await page
    .getByRole('button', {
      name: 'Редактировать сотрудника Марина Тестовая',
      exact: true,
    })
    .click()
  const editor = page.getByRole('dialog', {
    name: 'Редактировать сотрудника',
    exact: true,
  })
  await editor.getByLabel('Имя', { exact: false }).fill('Марина Обновлённая')
  await checkWithKeyboard(
    editor.getByRole('checkbox', { name: 'Системная роль', exact: true }),
  )
  await editor
    .getByRole('button', { name: 'Статус сотрудника', exact: true })
    .click()
  await page.getByRole('button', { name: 'Заблокирован', exact: true }).click()
  await editor.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(editor).toBeHidden()
  expect(api.mutations[0]).toMatchObject({
    method: 'PATCH',
    path: '/admin/users/2',
    body: { name: 'Марина Обновлённая', status: 'blocked', role_ids: [1, 2] },
  })
  await expect(
    region.getByRole('heading', { name: 'Марина Обновлённая', exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Добавить сотрудника', exact: true })
    .click()
  const create = page.getByRole('dialog', {
    name: 'Новый сотрудник',
    exact: true,
  })
  await create.getByLabel('Имя', { exact: false }).fill('Ольга Новая')
  await create.getByLabel('Email', { exact: false }).fill('olga@example.test')
  await create.getByLabel(/^Пароль/).fill('ExamplePassword123!')
  await create
    .getByLabel('Подтверждение пароля', { exact: false })
    .fill('ExamplePassword123!')
  await checkWithKeyboard(
    create.getByRole('checkbox', { name: 'Менеджер магазина', exact: true }),
  )
  await create.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(create).toBeHidden()
  expect(api.mutations[1]).toMatchObject({
    method: 'POST',
    path: '/admin/users',
    body: { email: 'olga@example.test', role_ids: [1], status: 'active' },
  })
  await page
    .getByRole('button', {
      name: 'Удалить сотрудника Ольга Новая',
      exact: true,
    })
    .click()
  const confirm = page.getByRole('dialog', {
    name: 'Удалить сотрудника?',
    exact: true,
  })
  await confirm.getByRole('button', { name: 'Отмена', exact: true }).click()
  expect(api.mutations).toHaveLength(2)
  await page
    .getByRole('button', {
      name: 'Удалить сотрудника Ольга Новая',
      exact: true,
    })
    .click()
  await confirm.getByRole('button', { name: 'Удалить', exact: true }).click()
  await expect(confirm).toBeHidden()
  await expect(
    region.getByRole('heading', { name: 'Ольга Новая', exact: true }),
  ).toHaveCount(0)
  expect(api.mutations[2]).toMatchObject({
    method: 'DELETE',
    path: '/admin/users/4',
  })
})

test('role browser preserves selection, search and system role identity', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  const api = await mockTeamAccess(page)
  await page.goto('/roles')
  const region = page.getByRole('region', { name: 'Роли и права', exact: true })
  await expect(region.locator('table')).toHaveCount(0)
  await expect(
    region.getByRole('heading', {
      level: 2,
      name: 'Менеджер магазина',
      exact: true,
    }),
  ).toBeVisible()
  const system = region
    .locator('button[aria-pressed]')
    .filter({ hasText: 'Системная роль' })
  await system.click()
  await expect(system).toHaveAttribute('aria-pressed', 'true')
  await expect(
    region.getByRole('heading', {
      level: 2,
      name: 'Системная роль',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    region.getByText('Просмотр заказов', { exact: true }),
  ).toBeVisible()
  await expect(
    region.getByText('Управление каталогом', { exact: true }),
  ).toBeVisible()
  await expect(
    region.getByRole('button', {
      name: 'Удалить роль Системная роль',
      exact: true,
    }),
  ).toHaveCount(0)
  await region
    .getByRole('button', {
      name: 'Редактировать роль Системная роль',
      exact: true,
    })
    .click()
  const editor = page.getByRole('dialog', {
    name: 'Роль: Системная роль',
    exact: true,
  })
  await expect(editor.getByLabel('Название', { exact: false })).toBeDisabled()
  await expect(
    editor.getByLabel('Технический код', { exact: false }),
  ).toBeDisabled()
  await editor
    .getByLabel('Описание', { exact: true })
    .fill('Обновлённое описание системной роли')
  await editor.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(editor).toBeHidden()
  expect(api.mutations[0]).toMatchObject({
    method: 'PATCH',
    path: '/admin/roles/2',
    body: {
      description: 'Обновлённое описание системной роли',
      permission_ids: [1, 2],
    },
  })
  expect(api.mutations[0]!.body).not.toHaveProperty('name')
  expect(api.mutations[0]!.body).not.toHaveProperty('slug')
  await page
    .getByLabel('Поиск ролей', { exact: true })
    .filter({ visible: true })
    .fill('store-manager')
  await expect(
    region
      .locator('button[aria-pressed]')
      .filter({ hasText: 'Системная роль' }),
  ).toHaveCount(0)
  await region
    .locator('button[aria-pressed]')
    .filter({ hasText: 'Менеджер магазина' })
    .click()
  await expect(
    region.getByRole('heading', {
      level: 2,
      name: 'Менеджер магазина',
      exact: true,
    }),
  ).toBeVisible()
  await page
    .getByLabel('Поиск ролей', { exact: true })
    .filter({ visible: true })
    .fill('несуществующая роль')
  await expect(region.locator('button[aria-pressed]')).toHaveCount(0)
  await page
    .getByLabel('Поиск ролей', { exact: true })
    .filter({ visible: true })
    .fill('')
  await expect(region.locator('button[aria-pressed]')).toHaveCount(2)
  await expect(
    page.getByRole('link', { name: 'Справочник прав', exact: true }),
  ).toHaveAttribute('href', '/permissions')
})

test('custom role CRUD preserves permissions and delete confirmation', async ({
  page,
}) => {
  const api = await mockTeamAccess(page)
  await page.goto('/roles')
  await page.getByRole('button', { name: 'Добавить роль', exact: true }).click()
  const create = page.getByRole('dialog', { name: 'Новая роль', exact: true })
  await create.getByLabel('Название', { exact: false }).fill('Контент менеджер')
  await checkWithKeyboard(
    create.getByRole('checkbox', { name: /Управление каталогом/ }),
  )
  await create.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(create).toBeHidden()
  expect(api.mutations[0]).toMatchObject({
    method: 'POST',
    path: '/admin/roles',
    body: { name: 'Контент менеджер', permission_ids: [2] },
  })
  const region = page.getByRole('region', { name: 'Роли и права', exact: true })
  await region
    .locator('button[aria-pressed]')
    .filter({ hasText: 'Контент менеджер' })
    .click()
  await region
    .getByRole('button', {
      name: 'Редактировать роль Контент менеджер',
      exact: true,
    })
    .click()
  const edit = page.getByRole('dialog', {
    name: 'Роль: Контент менеджер',
    exact: true,
  })
  await checkWithKeyboard(
    edit.getByRole('checkbox', { name: /Просмотр заказов/ }),
  )
  await edit.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(edit).toBeHidden()
  expect(api.mutations[1]).toMatchObject({
    method: 'PATCH',
    path: '/admin/roles/3',
    body: { permission_ids: [2, 1] },
  })
  await region
    .getByRole('button', { name: 'Удалить роль Контент менеджер', exact: true })
    .click()
  const confirm = page.getByRole('dialog', {
    name: 'Удалить роль?',
    exact: true,
  })
  await confirm.getByRole('button', { name: 'Отмена', exact: true }).click()
  expect(api.mutations).toHaveLength(2)
  await region
    .getByRole('button', { name: 'Удалить роль Контент менеджер', exact: true })
    .click()
  await confirm.getByRole('button', { name: 'Удалить', exact: true }).click()
  await expect(confirm).toBeHidden()
  expect(api.mutations[2]).toMatchObject({
    method: 'DELETE',
    path: '/admin/roles/3',
  })
  await expect(
    region
      .locator('button[aria-pressed]')
      .filter({ hasText: 'Контент менеджер' }),
  ).toHaveCount(0)
})

test('permission directory groups and filters existing system rights without mutations', async ({
  page,
}) => {
  const api = await mockTeamAccess(page)
  await page.goto('/permissions')
  const region = page.getByRole('region', { name: 'Каталог прав', exact: true })
  await expect(region.locator('table')).toHaveCount(0)
  await expect(
    region.getByText('Просмотр заказов', { exact: true }),
  ).toBeVisible()
  await expect(
    region.getByText('Управление каталогом', { exact: true }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Модуль прав', exact: true }).click()
  await page.getByRole('button', { name: 'Заказы', exact: true }).click()
  await expect(
    region.getByText('Просмотр заказов', { exact: true }),
  ).toBeVisible()
  await expect(
    region.getByText('Управление каталогом', { exact: true }),
  ).toHaveCount(0)
  await page.getByLabel('Поиск прав', { exact: true }).fill('catalog.manage')
  await expect(
    page.getByText('По выбранным условиям права не найдены.', { exact: true }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Модуль прав', exact: true }).click()
  await page.getByRole('button', { name: 'Все разделы', exact: true }).click()
  await expect(
    region.getByText('Управление каталогом', { exact: true }),
  ).toBeVisible()
  await expect(
    region.getByText('Просмотр заказов', { exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('link', { name: 'К ролям', exact: true }),
  ).toHaveAttribute('href', '/roles')
  expect(api.mutations).toEqual([])
})

for (const profile of [
  {
    permissions: ['admin-users.view'],
    path: '/employees',
    employees: true,
    roles: false,
    rolesPath: '',
  },
  {
    permissions: ['roles.view'],
    path: '/roles',
    employees: false,
    roles: true,
    rolesPath: '/roles',
  },
  {
    permissions: ['permissions.view'],
    path: '/permissions',
    employees: false,
    roles: true,
    rolesPath: '/permissions',
  },
] as const) {
  test(`team access preserves independent read permissions for ${profile.path}`, async ({
    page,
  }) => {
    const api = await mockTeamAccess(page, [...profile.permissions])
    await page.goto(profile.path)
    const navigation = page.getByRole('navigation', {
      name: 'Команда и доступ',
      exact: true,
    })
    await expect(
      navigation.getByRole('link', { name: 'Сотрудники', exact: true }),
    ).toHaveCount(profile.employees ? 1 : 0)
    await expect(
      navigation.getByRole('link', { name: 'Роли и права', exact: true }),
    ).toHaveCount(profile.roles ? 1 : 0)
    if (profile.roles)
      await expect(
        navigation.getByRole('link', { name: 'Роли и права', exact: true }),
      ).toHaveAttribute('href', profile.rolesPath)
    await expect(
      page.getByRole('button', {
        name: /Добавить (?:роль|сотрудника)|Редактировать (?:роль|сотрудника)|Удалить (?:роль|сотрудника)/,
      }),
    ).toHaveCount(0)
    if (profile.path !== '/permissions')
      expect(api.reads).not.toContain('/admin/permissions')
    if (profile.path === '/permissions') {
      expect(api.reads).not.toContain('/admin/roles')
      await expect(
        page.getByRole('link', { name: 'К ролям', exact: true }),
      ).toHaveCount(0)
    }
    if (profile.path === '/roles') {
      expect(api.reads).not.toContain('/admin/roles/permissions')
      await expect(
        page.getByRole('link', { name: 'Справочник прав', exact: true }),
      ).toHaveCount(0)
    }
    expect(api.mutations).toEqual([])
  })
}

for (const [path, label] of [
  ['/employees', 'Список сотрудников'],
  ['/roles', 'Роли и права'],
  ['/permissions', 'Каталог прав'],
] as const) {
  test(`team access ${path} preserves long data and captures responsive UI Guard evidence`, async ({
    page,
  }) => {
    const api = await mockTeamAccess(page, grantedPermissions, true)
    await page.goto(path)
    const region = page.getByRole('region', { name: label, exact: true })
    await expect(region).toBeVisible()
    await page.evaluate(() => document.fonts.ready)
    for (const width of [320, 640, 768, 1024, 1280, 1920, 2560]) {
      await page.setViewportSize({ width, height: 900 })
      if (path === '/roles') {
        const longName = api.roles[0]!.name
        if (width < 1024) {
          await page
            .getByRole('button', { name: 'Выбор роли', exact: true })
            .click()
          await page
            .getByRole('group', { name: 'Выбор роли: варианты', exact: true })
            .getByRole('button', { name: longName, exact: true })
            .click()
        } else {
          await region
            .getByRole('navigation', { name: 'Выбор роли', exact: true })
            .getByRole('button', { name: longName, exact: true })
            .click()
        }
        const title = region.getByRole('heading', {
          level: 2,
          name: longName,
          exact: true,
        })
        await expect(title).toBeVisible()
        const dimensions = await title.evaluate((element) => ({
          titleWidth: element.getBoundingClientRect().width,
          headerWidth: element.closest('header')!.getBoundingClientRect().width,
          overflows: element.scrollWidth > element.clientWidth,
        }))
        expect(
          dimensions.overflows,
          `long role title overflow at ${width}px`,
        ).toBe(false)
        if (width < 640)
          expect(
            dimensions.titleWidth,
            'mobile title uses full header width',
          ).toBeGreaterThanOrEqual(dimensions.headerWidth - 1)
        else
          expect(
            dimensions.titleWidth,
            `long role title width at ${width}px`,
          ).toBeGreaterThanOrEqual(Math.min(240, dimensions.headerWidth / 2))
        await page.screenshot({
          path: `.tmp/team-access/roles-long-${width}.png`,
          fullPage: true,
          animations: 'disabled',
        })
      }
      await expect(region.locator('table')).toHaveCount(0)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
        `${path} overflow at ${width}px`,
      ).toBe(true)
      expect(
        await region.evaluate(
          (element) => element.scrollWidth <= element.clientWidth,
        ),
        `${path} region overflow at ${width}px`,
      ).toBe(true)
      if (path === '/roles' && width < 1024) {
        await page
          .getByRole('button', { name: 'Выбор роли', exact: true })
          .click()
        await page
          .getByRole('group', { name: 'Выбор роли: варианты', exact: true })
          .getByRole('button', { name: 'Системная роль', exact: true })
          .click()
        await expect(
          region.getByRole('heading', {
            level: 2,
            name: 'Системная роль',
            exact: true,
          }),
        ).toBeVisible()
      }
      await page.screenshot({
        path: `.tmp/team-access/${path.slice(1)}-${width}.png`,
        fullPage: true,
        animations: 'disabled',
      })
      if (width === 320 || width === 1280) {
        const accessibility = await new AxeBuilder({ page }).analyze()
        expect(accessibility.violations).toEqual([])
      }
    }
  })
}

test('team access editors preserve keyboard close, focus and responsive accessibility', async ({
  page,
}) => {
  await mockTeamAccess(page)
  for (const width of [320, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    for (const [path, button, dialog] of [
      ['/employees', 'Добавить сотрудника', 'Новый сотрудник'],
      ['/roles', 'Добавить роль', 'Новая роль'],
    ] as const) {
      await page.goto(path)
      const opener = page.getByRole('button', { name: button, exact: true })
      await opener.click()
      const editor = page.getByRole('dialog', { name: dialog, exact: true })
      await expect(editor).toBeVisible()
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      const accessibility = await new AxeBuilder({ page }).analyze()
      expect(accessibility.violations).toEqual([])
      await page.screenshot({
        path: `.tmp/team-access/${path.slice(1)}-editor-${width}.png`,
        animations: 'disabled',
      })
      await page.keyboard.press('Escape')
      await expect(editor).toBeHidden()
      await expect(opener).toBeFocused()
    }
  }
})
