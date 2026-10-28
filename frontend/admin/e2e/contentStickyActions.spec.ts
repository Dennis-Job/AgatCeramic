import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockContentWorkspace } from './contentWorkspaceApi'

for (const [workspace, path, saveName, publishName] of [
  [
    'pages',
    '/content?page=home',
    'Сохранить черновик блоков',
    'Опубликовать блоки страницы',
  ],
  [
    'header',
    '/content?section=appearance&panel=header',
    'Сохранить черновик раздела',
    'Опубликовать оформление',
  ],
  [
    'footer',
    '/content?section=appearance&panel=footer',
    'Сохранить черновик раздела',
    'Опубликовать оформление',
  ],
] as const) {
  for (const width of [320, 640, 768, 1024, 1280]) {
    test(`${workspace} actions stay below the header while scrolling at ${width}px`, async ({
      page,
    }) => {
      await page.setViewportSize({ width, height: 883 })
      await mockContentWorkspace(page)
      await page.goto(path)
      const save = page.getByRole('button', {
        name: saveName,
        exact: true,
      })
      const publish = page.getByRole('button', {
        name: publishName,
        exact: true,
      })
      await expect(save).toBeVisible()
      await expect(publish).toBeVisible()
      await page.evaluate(() => document.fonts.ready)
      const editor = page.locator('.admin-editor-grid')
      const initialTop = await editor.evaluate(
        (el) => el.getBoundingClientRect().top + scrollY,
      )
      await page.evaluate(() => window.scrollTo(0, 600))
      const header = page.locator('.admin-header')
      await expect
        .poll(async () => (await save.boundingBox())!.y)
        .toBeGreaterThanOrEqual(65)
      const headerBox = (await header.boundingBox())!
      const saveBox = (await save.boundingBox())!
      expect(saveBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height)
      expect(saveBox.y).toBeLessThan(headerBox.height + 40)
      expect(
        await editor.evaluate((el) => el.getBoundingClientRect().top + scrollY),
      ).toBe(initialTop)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await expect(save).toBeDisabled()
      await expect(publish).toBeDisabled()
      await page.screenshot({
        path: `.tmp/sticky-actions/${workspace}-scrolled-${width}.png`,
      })
      expect(
        (await new AxeBuilder({ page }).exclude('iframe').analyze()).violations,
      ).toEqual([])
      await page.evaluate(() => window.scrollTo(0, 0))
      await expect(
        page.locator('.admin-editor-toolbar').getByRole('button', {
          name: saveName,
          exact: true,
        }),
      ).toBeVisible()
      await page.screenshot({
        path: `.tmp/sticky-actions/${workspace}-top-${width}.png`,
      })
    })
  }
}

for (const width of [320, 640, 768, 1024, 1059, 1280]) {
  test(`floating actions grow for save and publish loaders at ${width}px`, async ({
    page,
  }) => {
    await page.setViewportSize({ width, height: 883 })
    await mockContentWorkspace(page)
    let saved: Record<string, unknown> = {}
    let release: (() => void) | undefined
    await page.route('**/api/v1/admin/pages/1', async (route) => {
      if (route.request().method() !== 'PATCH') return route.fallback()
      saved = {
        ...route.request().postDataJSON(),
        id: 1,
        slug: 'home',
        is_published: true,
        has_unpublished_changes: true,
      }
      await new Promise<void>((resolve) => {
        release = resolve
      })
      await route.fulfill({ json: { data: saved } })
    })
    await page.route('**/api/v1/admin/pages/1/publish', async (route) => {
      await new Promise<void>((resolve) => {
        release = resolve
      })
      await route.fulfill({
        json: { data: { ...saved, has_unpublished_changes: false } },
      })
    })
    await page.goto('/content?page=home')
    await page
      .getByRole('textbox', { name: /^Заголовок блока/ })
      .fill('Проверка прелоадера')
    await page.evaluate(() => document.fonts.ready)
    await page.evaluate(() => window.scrollTo(0, 600))
    const panel = page.locator('.admin-sticky-actions--floating')
    const save = page.getByRole('button', {
      name: 'Сохранить черновик блоков',
      exact: true,
    })
    const publish = page.getByRole('button', {
      name: 'Опубликовать блоки страницы',
      exact: true,
    })
    await expect(panel).toBeVisible()
    for (const [action, button] of [
      ['save', save],
      ['publish', publish],
    ] as const) {
      await button.click({ trial: true })
      const idle = (await panel.boundingBox())!
      const gap = await panel.evaluate((el) =>
        parseFloat(getComputedStyle(el).columnGap),
      )
      await page.screenshot({
        path: `.tmp/sticky-actions/loading-idle-${action}-${width}.png`,
      })
      release = undefined
      await button.click()
      await expect.poll(() => !!release).toBe(true)
      await expect(panel.locator('.animate-spin')).toBeVisible()
      await expect(save).toBeDisabled()
      await expect(publish).toBeDisabled()
      if (width >= 640) {
        await expect
          .poll(async () => (await panel.boundingBox())!.width)
          .toBeGreaterThan(idle.width + 10)
        const busy = (await panel.boundingBox())!
        expect(busy.height).toBeCloseTo(idle.height, 0)
        expect(busy.x + busy.width).toBeCloseTo(
          Math.max(idle.x + idle.width, busy.width + gap),
          0,
        )
      }
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      for (const control of [save, publish]) {
        const box = (await control.boundingBox())!
        expect(box.x).toBeGreaterThanOrEqual(0)
        expect(box.x + box.width).toBeLessThanOrEqual(width)
        expect(
          await control.evaluate((el) => el.scrollWidth <= el.clientWidth),
        ).toBe(true)
      }
      await page.screenshot({
        path: `.tmp/sticky-actions/loading-${action}-${width}.png`,
      })
      release!()
      await expect(panel.locator('.animate-spin')).toHaveCount(0)
      if (action === 'save') await expect(publish).toBeEnabled()
      await expect
        .poll(async () => (await panel.boundingBox())!.width)
        .toBeCloseTo(idle.width, 0)
    }
  })
}

test('floating actions preserve focus, resize, save and publish the same draft', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 883 })
  await mockContentWorkspace(page)
  let saved: Record<string, unknown> = {}
  let releaseSave: (() => void) | undefined
  await page.route('**/api/v1/admin/pages/1', async (route) => {
    if (route.request().method() !== 'PATCH') return route.fallback()
    saved = {
      ...route.request().postDataJSON(),
      id: 1,
      slug: 'home',
      is_published: true,
      has_unpublished_changes: true,
    }
    await new Promise<void>((resolve) => {
      releaseSave = resolve
    })
    await route.fulfill({ json: { data: saved } })
  })
  await page.route('**/api/v1/admin/pages/1/publish', (route) =>
    route.fulfill({
      json: { data: { ...saved, has_unpublished_changes: false } },
    }),
  )
  await page.goto('/content?page=home')
  const save = page.getByRole('button', {
    name: 'Сохранить черновик блоков',
    exact: true,
  })
  const publish = page.getByRole('button', {
    name: 'Опубликовать блоки страницы',
    exact: true,
  })
  await page
    .getByRole('textbox', { name: /^Заголовок блока/ })
    .fill('Проверка закреплённых действий')
  await save.focus()
  await page.evaluate(() => window.scrollTo(0, 600))
  await expect(page.locator('.admin-sticky-actions--floating')).toBeVisible()
  await expect(save).toBeFocused()
  await page.setViewportSize({ width: 320, height: 883 })
  await expect(save).toBeInViewport()
  await expect(publish).toBeInViewport()
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await page.setViewportSize({ width: 1280, height: 883 })
  await save.press('Enter')
  await expect.poll(() => !!releaseSave).toBe(true)
  await expect(save).toBeDisabled()
  await expect(publish).toBeDisabled()
  releaseSave!()
  await expect(publish).toBeEnabled()
  await publish.click()
  await expect(publish).toBeDisabled()
  expect(JSON.stringify(saved.blocks)).toContain(
    'Проверка закреплённых действий',
  )
  await page.evaluate(() => window.scrollTo(0, 0))
  await page
    .getByRole('button', { name: 'Добавить страницу', exact: true })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Новая страница' })
  await expect(dialog).toBeVisible()
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Магазины', exact: true }).click()
  await expect(page.locator('.admin-sticky-actions')).toHaveCount(0)
})
