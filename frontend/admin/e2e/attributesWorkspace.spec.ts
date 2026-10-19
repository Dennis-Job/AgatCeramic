import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockAttributesWorkspace } from './attributesWorkspaceApi'

test('unified workspace loads every page and filters characteristics by group and search', async ({
  page,
}) => {
  await mockAttributesWorkspace(page)
  await page.goto('/attributes')
  await expect(
    page.getByRole('heading', { name: 'Характеристики', exact: true }),
  ).toBeVisible()
  const groups = page.getByRole('navigation', { name: 'Группы характеристик' })
  await expect(
    groups.getByRole('button', { name: /Все характеристики/ }),
  ).toContainText('5')
  await expect(groups.getByRole('button', { name: /^Размеры/ })).toContainText(
    '2',
  )
  await expect(
    groups.getByRole('button', { name: /^Внешний вид/ }),
  ).toContainText('2')
  await expect(
    groups.getByRole('button', { name: /^Без группы/ }),
  ).toContainText('1')
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(5)

  await groups.getByRole('button', { name: /^Внешний вид/ }).click()
  await expect(
    groups.getByRole('button', { name: /^Внешний вид/ }),
  ).toHaveAttribute('aria-current', 'true')
  await expect(groups.getByRole('button', { name: /^Внешний вид/ })).toHaveCSS(
    'background-color',
    'rgb(235, 244, 255)',
  )
  await expect(
    groups.getByRole('button', { name: /Все характеристики/ }),
  ).not.toHaveAttribute('aria-current')
  await expect(
    groups.getByRole('button', { name: /Все характеристики/ }),
  ).toHaveCSS('background-color', 'rgb(255, 255, 255)')
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(2)
  await expect(
    page.getByRole('article', { name: 'Характеристика Рисунок', exact: true }),
  ).toContainText('Камень')
  await page
    .getByRole('searchbox', { name: 'Поиск характеристик', exact: true })
    .fill('рисунок')
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(1)
  await page
    .getByRole('searchbox', { name: 'Поиск характеристик', exact: true })
    .fill('несуществующая')
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(0)
  await page
    .getByRole('searchbox', { name: 'Поиск характеристик', exact: true })
    .clear()
  await page
    .getByRole('searchbox', { name: 'Поиск групп', exact: true })
    .fill('размер')
  await expect(groups.getByRole('button', { name: /^Размеры/ })).toBeVisible()
  await expect(
    groups.getByRole('button', { name: /^Внешний вид/ }),
  ).toHaveCount(0)
  await page
    .getByRole('searchbox', { name: 'Поиск групп', exact: true })
    .clear()
  await groups.getByRole('button', { name: /^Без группы/ }).click()
  await expect(
    page.getByRole('article', {
      name: 'Характеристика Примечание',
      exact: true,
    }),
  ).toBeVisible()
  await groups.getByRole('button', { name: /^Пустая группа/ }).click()
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Добавить характеристику', exact: true }),
  ).toBeEnabled()
})

test('quick switches send only the changed field and disable competing row actions while saving', async ({
  page,
}) => {
  const api = await mockAttributesWorkspace(page)
  await page.goto('/attributes')
  const row = page.getByRole('article', {
    name: 'Характеристика Ширина',
    exact: true,
  })
  const filter = row.getByRole('switch', {
    name: 'Участие в фильтрах: Ширина',
    exact: true,
  })
  const visible = row.getByRole('switch', {
    name: 'Отображение в карточке товара: Ширина',
    exact: true,
  })
  await expect(filter).toBeChecked()
  await expect(visible).toBeChecked()
  const release = api.pauseNextPatch()
  await filter.focus()
  await filter.press('Space')
  await expect
    .poll(() => api.patches)
    .toEqual([{ id: 1, body: { is_filterable: false } }])
  await expect(filter).toBeDisabled()
  await expect(visible).toBeDisabled()
  await expect(
    row.getByRole('button', { name: /^Редактировать/ }),
  ).toBeDisabled()
  await expect(row.getByRole('button', { name: /^Удалить/ })).toBeDisabled()
  release()
  await expect(filter).toBeEnabled()
  await expect(filter).not.toBeChecked()
  await expect(visible).toBeChecked()
  await visible.click()
  await expect
    .poll(() => api.patches.at(-1))
    .toEqual({ id: 1, body: { is_visible_on_product_page: false } })
  await expect(visible).toBeEnabled()
  await expect(visible).not.toBeChecked()
  await page.reload()
  await expect(filter).not.toBeChecked()
  await expect(visible).not.toBeChecked()
})

test('a failed quick switch restores its saved value and announces the error', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockAttributesWorkspace(page)
  api.failNextPatch()
  browserIssueGuard.allowApiError(500, '/api/v1/admin/attributes/1')
  await page.goto('/attributes')
  const row = page.getByRole('article', {
    name: 'Характеристика Ширина',
    exact: true,
  })
  const filter = row.getByRole('switch', {
    name: 'Участие в фильтрах: Ширина',
    exact: true,
  })
  await filter.click()
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось сохранить настройку характеристики.',
  )
  await expect(filter).toBeEnabled()
  await expect(filter).toBeChecked()
  await expect(
    row.getByRole('switch', {
      name: 'Отображение в карточке товара: Ширина',
      exact: true,
    }),
  ).toBeChecked()
  await filter.click()
  await expect(filter).not.toBeChecked()
  await expect(filter).toBeEnabled()
})

test('an initial load failure keeps the catalog unknown until retry succeeds', async ({
  page,
  browserIssueGuard,
}) => {
  await mockAttributesWorkspace(page)
  let fail = true
  browserIssueGuard.allowApiError(500, '/api/v1/admin/attributes')
  await page.route(/\/api\/v1\/admin\/attributes(?:\?.*)?$/, async (route) => {
    if (route.request().method() !== 'GET' || !fail) return route.fallback()
    await route.fulfill({
      status: 500,
      json: {
        error: { message: 'Не удалось загрузить характеристики и группы.' },
      },
    })
  })
  await page.goto('/attributes')
  await expect(page.getByRole('main')).toContainText(
    'Не удалось загрузить характеристики и группы.',
  )
  await expect(
    page.getByRole('button', { name: 'Повторить загрузку', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Добавить характеристику', exact: true }),
  ).toBeDisabled()
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('navigation', { name: 'Группы характеристик' }),
  ).toHaveCount(0)
  await expect(page.getByText(/Характеристик пока нет/)).toHaveCount(0)
  fail = false
  await page
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(
    page.getByRole('article', { name: /^Характеристика / }),
  ).toHaveCount(5)
  await expect(
    page
      .getByRole('navigation', { name: 'Группы характеристик' })
      .getByRole('button', { name: /Все характеристики/ }),
  ).toContainText('5')
  await expect(
    page.getByRole('button', { name: 'Добавить характеристику', exact: true }),
  ).toBeEnabled()
  await expect(
    page.getByRole('button', { name: 'Повторить загрузку', exact: true }),
  ).toHaveCount(0)
})

test('creating a group inside the characteristic form keeps its draft and selects the new group', async ({
  page,
}) => {
  const api = await mockAttributesWorkspace(page)
  await page.goto('/attributes')
  await page
    .getByRole('navigation', { name: 'Группы характеристик' })
    .getByRole('button', { name: /^Размеры/ })
    .click()
  await page
    .getByRole('button', { name: 'Добавить характеристику', exact: true })
    .click()
  const form = page.getByRole('dialog', { name: 'Новая характеристика' })
  const groupSelect = form.getByRole('button', {
    name: 'Группа характеристики',
    exact: true,
  })
  await expect(groupSelect).toContainText('Размеры')
  await form
    .getByRole('textbox', { name: 'Название', exact: true })
    .fill('Вес упаковки')
  await form
    .getByRole('textbox', { name: /^Технический код/ })
    .fill('package-weight')
  await form
    .getByRole('textbox', { name: 'Единица измерения', exact: true })
    .fill('кг')
  await form
    .getByRole('button', { name: 'Создать группу', exact: true })
    .click()
  const groupForm = page.getByRole('dialog', {
    name: 'Новая группа характеристик',
  })
  await groupForm
    .getByRole('textbox', { name: 'Название', exact: true })
    .fill('Упаковка')
  await groupForm
    .getByRole('textbox', { name: /^Технический код/ })
    .fill('packaging')
  await groupForm
    .getByRole('textbox', { name: 'Описание', exact: true })
    .fill('Параметры упаковки товара')
  await groupForm
    .getByRole('button', { name: 'Сохранить', exact: true })
    .click()
  await expect(groupForm).toHaveCount(0)
  await expect(
    form.getByRole('textbox', { name: /^Название(?:\s|$)/ }),
  ).toHaveValue('Вес упаковки')
  await expect(
    form.getByRole('textbox', { name: /^Технический код/ }),
  ).toHaveValue('package-weight')
  await expect(
    form.getByRole('textbox', { name: /^Единица измерения(?:\s|$)/ }),
  ).toHaveValue('кг')
  await expect(groupSelect).toContainText('Упаковка')
  await form.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(form).toHaveCount(0)
  await expect
    .poll(
      () =>
        api.attributes.find((item) => item.slug === 'package-weight')
          ?.attribute_group_id,
    )
    .toBe(4)
  await page
    .getByRole('navigation', { name: 'Группы характеристик' })
    .getByRole('button', { name: /^Упаковка/ })
    .click()
  await expect(
    page.getByRole('article', {
      name: 'Характеристика Вес упаковки',
      exact: true,
    }),
  ).toBeVisible()
})

test('legacy groups URL exposes the unified workspace and keyboard dialogs return focus', async ({
  page,
}) => {
  await mockAttributesWorkspace(page)
  await page.goto('/attribute-groups')
  await expect(page).toHaveURL(/\/attribute-groups$/)
  await expect(
    page.getByRole('heading', { name: 'Характеристики', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('navigation', { name: 'Группы характеристик' }),
  ).toBeVisible()
  const create = page.getByRole('button', {
    name: 'Добавить характеристику',
    exact: true,
  })
  await create.focus()
  await create.press('Enter')
  const dialog = page.getByRole('dialog')
  await expect(
    dialog.getByRole('textbox', { name: 'Название', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(create).toBeFocused()
})

for (const width of [320, 1440]) {
  test(`nested group Escape keeps the characteristic draft and returns focus at ${width}px`, async ({
    page,
  }) => {
    await mockAttributesWorkspace(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/attributes')
    if (width === 1440) {
      const selected = page
        .getByRole('navigation', { name: 'Группы характеристик' })
        .getByRole('button', { name: /^Размеры/ })
      await selected.click()
      await expect(selected).toHaveAttribute('aria-current', 'true')
      await expect(selected).toHaveCSS('background-color', 'rgb(235, 244, 255)')
      await page.screenshot({
        path: '../../.tmp/attributes-workspace-selected-group-1440.png',
        fullPage: true,
        animations: 'disabled',
      })
    }
    await page
      .getByRole('button', { name: 'Добавить характеристику', exact: true })
      .click()
    const dialog = page.getByRole('dialog', { name: 'Новая характеристика' })
    const name = dialog.getByRole('textbox', { name: /^Название(?:\s|$)/ })
    await name.fill('Толщина упаковки')
    await dialog
      .getByRole('textbox', { name: /^Технический код/ })
      .fill('packaging-thickness')
    await page.screenshot({
      path: `../../.tmp/attributes-workspace-attribute-form-${width}.png`,
      animations: 'disabled',
    })
    const createGroup = dialog.getByRole('button', {
      name: 'Создать группу',
      exact: true,
    })
    await createGroup.click()
    const groupDialog = page.getByRole('dialog', {
      name: 'Новая группа характеристик',
    })
    await expect(
      groupDialog.getByRole('textbox', { name: 'Название', exact: true }),
    ).toBeFocused()
    await expect(page.getByRole('dialog')).toHaveCount(1)
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await page.screenshot({
      path: `../../.tmp/attributes-workspace-group-form-${width}.png`,
      animations: 'disabled',
    })
    await page.keyboard.press('Escape')
    await expect(groupDialog).toHaveCount(0)
    await expect(name).toHaveValue('Толщина упаковки')
    await expect(
      dialog.getByRole('textbox', { name: /^Технический код/ }),
    ).toHaveValue('packaging-thickness')
    await expect(createGroup).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
  })
}

for (const width of [320, 640, 768, 1024, 1280, 1440]) {
  test(`unified characteristic workspace fits ${width}px and remains accessible`, async ({
    page,
  }) => {
    await mockAttributesWorkspace(page, { longText: true })
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/attributes')
    const longRow = page.getByRole('article', {
      name: /Характеристика ДополнительныеТехнические/,
    })
    await expect(longRow).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await page.screenshot({
      path: `../../.tmp/attributes-workspace-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
    await page
      .getByRole('button', { name: 'Добавить характеристику', exact: true })
      .click()
    const dialog = page.getByRole('dialog')
    await expect(
      dialog.getByRole('textbox', { name: 'Название', exact: true }),
    ).toBeFocused()
    const bounds = (await dialog.boundingBox())!
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width)
    const formAccessibility = await new AxeBuilder({ page }).analyze()
    expect(
      formAccessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
  })
}
