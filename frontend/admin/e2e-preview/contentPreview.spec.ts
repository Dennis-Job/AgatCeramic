import { expect, test } from '../e2e/fixtures'
import { mockContentWorkspace } from '../e2e/contentWorkspaceApi'

for (const [path, slug] of [
  ['/content?page=home', 'home'],
  ['/content?page=2', 'about'],
  ['/content?section=appearance', 'home'],
] as const) {
  test(`real Nuxt preview inside ${path} stays local and keyboard accessible`, async ({
    page,
    context,
    request,
  }) => {
    await request.get('http://127.0.0.1:8015/__scenario?name=default')
    await context.addCookies([
      {
        name: 'preview_test_session',
        value: 'employee',
        url: 'http://127.0.0.1:8015',
        sameSite: 'Lax',
      },
    ])
    await mockContentWorkspace(page)
    // Let the actual Nuxt iframe reach its HTTP API instead of Admin's fixtures.
    await page.route('http://127.0.0.1:8015/**', (route) => route.continue())
    await page.goto(path)
    const iframe = page.locator('iframe')
    await expect(iframe).toHaveAttribute(
      'src',
      `http://127.0.0.1:3015/preview/${slug}`,
    )
    const frame = page.frameLocator('iframe')
    await expect(
      frame.getByRole('heading', { name: 'Сохранённый текст черновика' }),
    ).toBeVisible()
    await expect(frame.getByText('Черновая шапка')).toBeVisible()
    const region = page.getByRole('region', {
      name: 'Область просмотра сайта',
      exact: true,
    })
    async function capture(path: string) {
      await region.scrollIntoViewIfNeeded()
      await expect(
        frame.getByRole('heading', { name: 'Сохранённый текст черновика' }),
      ).toBeVisible()
      await expect(frame.getByText('Черновая шапка')).toBeVisible()
      await iframe
        .contentFrame()
        .locator('body')
        .evaluate(async () => {
          await document.fonts.ready
          await new Promise<void>((resolve) =>
            requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
          )
        })
      await page.screenshot({ path, animations: 'disabled' })
    }
    for (const width of [320, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
      await page.setViewportSize({ width, height: 900 })
      await expect(iframe).toHaveCSS('width', '1280px')
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await capture(
        `.tmp/a061-nuxt/${slug}-${path.includes('appearance') ? 'appearance' : 'pages'}-${width}-desktop.png`,
      )
      const scrollable = await region.evaluate(
        (element) => element.scrollWidth > element.clientWidth,
      )
      if (scrollable) {
        await region.focus()
        await page.keyboard.press('ArrowRight')
        await expect
          .poll(() => region.evaluate((element) => element.scrollLeft))
          .toBeGreaterThan(0)
      }
      await page.getByRole('button', { name: 'Телефон', exact: true }).click()
      await expect(iframe).toHaveCSS('width', '375px')
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      if (width >= 1440) {
        const left = await iframe.evaluate(
          (element) => element.getBoundingClientRect().left,
        )
        const box = (await region.boundingBox())!
        expect(Math.abs(left - (box.x + (box.width - 375) / 2))).toBeLessThan(2)
      }
      await capture(
        `.tmp/a061-nuxt/${slug}-${path.includes('appearance') ? 'appearance' : 'pages'}-${width}-mobile.png`,
      )
      await page.getByRole('button', { name: 'Компьютер', exact: true }).click()
    }
  })
}
