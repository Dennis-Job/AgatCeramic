import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import {
  getContactStatuses,
  getContactStatusCounts,
} from '../src/features/contacts/services/contacts'

const fetch = vi.fn<typeof globalThis.fetch>()
beforeEach(() => {
  fetch.mockReset()
  vi.stubGlobal('fetch', fetch)
})
afterEach(() => vi.unstubAllGlobals())

function requestedPath() {
  return new URL(String(fetch.mock.calls[0]?.[0]), window.location.origin)
    .pathname
}

test('the contacts workspace loads allowed transitions through the additive workflow endpoint', async () => {
  const statuses = [
    {
      code: 'new',
      name: 'Новое',
      is_terminal: false,
      allowed_transitions: ['processing', 'rejected'],
    },
  ]
  fetch.mockResolvedValueOnce(new Response(JSON.stringify({ data: statuses })))
  await expect(getContactStatuses()).resolves.toEqual(statuses)
  expect(fetch).toHaveBeenCalledOnce()
  expect(requestedPath()).toBe('/api/v1/admin/contact-workflow')
})

test('loads global status counts through a separate unfiltered endpoint', async () => {
  const counts = { new: 9, processing: 4, awaiting_client: 2 }
  fetch.mockResolvedValueOnce(new Response(JSON.stringify({ data: counts })))
  await expect(getContactStatusCounts()).resolves.toEqual(counts)
  expect(fetch).toHaveBeenCalledOnce()
  expect(requestedPath()).toBe('/api/v1/admin/contact-request-counts')
})
