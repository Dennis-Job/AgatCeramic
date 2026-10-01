import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockContentWorkspace } from './contentWorkspaceApi'

test.use({ reducedMotion: 'reduce' })
const widths = [320, 640, 768, 1024, 1280, 1440, 1920, 2560]

for (const [path, title, mode, maxWidth, editor] of [
  ['/', 'Обзор магазина', 'overview', 1280, ''],
  ['/profile', 'Мой профиль', 'form', 960, ''],
  ['/settings', 'Настройки сайта', 'form', 960, ''],
  ['/content?section=stores', 'Магазины', 'overview', 1280, ''],
  ['/content?page=home', 'Главная страница', 'editor', Infinity, 'navigation'],
  ['/content?page=2', 'Страницы', 'editor', Infinity, 'navigation'],
  [
    '/content?section=appearance',
    'Общее оформление',
    'editor',
    Infinity,
    'appearance',
  ],
] as const) {
  test(`content workspace ${path} uses available width and readable zones`, async ({
    page,
  }) => {
    await mockContentWorkspace(page)
    await page.goto(path)
    await expect(
      page.getByRole('heading', { name: title, exact: true }),
    ).toBeVisible()
    await expect(
      page.getByRole('status').filter({ hasText: 'Загрузка' }),
    ).toHaveCount(0)
    if (editor) await expect(page.locator('iframe')).toBeVisible()
    await page.evaluate(() => document.fonts.ready)
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 })
      const workspace = page.locator(`.admin-workspace--${mode}`).first()
      await expect
        .poll(async () => (await workspace.boundingBox())?.width)
        .toBe(Math.min(maxWidth, width - (width < 640 ? 32 : 48)))
      const dimensions = await workspace.boundingBox()
      expect(
        Math.abs(dimensions!.x - (width - dimensions!.width) / 2),
      ).toBeLessThan(1)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
        `page overflow at ${width}`,
      ).toBe(true)
      if (editor) {
        const zones = await page
          .locator('.admin-editor-grid > div')
          .evaluateAll((elements) =>
            elements.map((element) => {
              const { x, y, width } = element.getBoundingClientRect()
              return { x, y, width }
            }),
          )
        if (editor === 'navigation') {
          expect(zones).toHaveLength(3)
          if (width >= 1024) {
            expect(zones[0]!.width).toBe(240)
            expect(zones[0]!.y).toBe(zones[1]!.y)
          } else expect(zones[1]!.y).toBeGreaterThan(zones[0]!.y)
          if (width >= 1440) {
            expect(zones[2]!.y).toBe(zones[1]!.y)
            expect(zones[2]!.width).toBeGreaterThanOrEqual(420)
          } else expect(zones[2]!.y).toBeGreaterThan(zones[1]!.y)
        } else {
          expect(zones).toHaveLength(2)
          if (width >= 1280) expect(zones[1]!.y).toBe(zones[0]!.y)
          else expect(zones[1]!.y).toBeGreaterThan(zones[0]!.y)
        }
      }
      await page.evaluate(() => window.scrollTo(0, 0))
      await page.screenshot({
        path: `.tmp/a061-visual/${path.replace(/\W+/g, '-')}-${width}.png`,
      })
    }
    expect(
      (await new AxeBuilder({ page }).exclude('iframe').analyze()).violations,
    ).toEqual([])
  })
}
