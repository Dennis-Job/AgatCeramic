import { describe, expect, test } from 'vitest'
import {
  isNavigationLinkActive,
  visibleNavigation,
} from '../src/layouts/navigation'

describe('team access navigation', () => {
  test.each([
    [['admin-users.view', 'roles.view', 'permissions.view'], '/employees'],
    [['roles.view'], '/roles'],
    [['permissions.view'], '/permissions'],
  ])(
    'offers one entry at the first accessible page for %j',
    (permissions, path) => {
      const management = visibleNavigation((permission) =>
        permissions.includes(permission),
      ).find((section) => section.id === 'management')
      const links = management?.groups?.flatMap((group) => group.links) ?? []
      expect(links.map((link) => link.label)).toEqual([
        'Команда и доступ',
        'UI-kit',
      ])
      const entry = links[0]!
      expect(entry.to).toBe(path)
      for (const page of ['/employees', '/roles', '/permissions']) {
        expect(isNavigationLinkActive(entry, { path: page, query: {} })).toBe(
          true,
        )
      }
    },
  )

  test('hides the entry when no part of the section can be viewed', () => {
    const links = visibleNavigation(() => false).flatMap(
      (section) => section.groups?.flatMap((group) => group.links) ?? [],
    )
    expect(links.some((link) => link.label === 'Команда и доступ')).toBe(false)
  })
})
