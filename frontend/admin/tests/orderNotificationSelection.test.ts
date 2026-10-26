import { createPinia, setActivePinia } from 'pinia'
import { expect, test, vi } from 'vitest'
import { effectScope } from 'vue'
import { useOrdersWorkspace } from '../src/features/orders/composables/useOrdersWorkspace'
import type { Order } from '../src/features/orders/types/order.types'

const api = vi.hoisted(() => ({
  getOrder: vi.fn(),
  getOrderHistory: vi.fn(),
  getOrderComments: vi.fn(),
}))
vi.mock('../src/features/orders/services/orders', () => api)

test('changing the linked order cannot restore details from a slower previous request', async () => {
  setActivePinia(createPinia())
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  let finish!: (order: Order) => void
  api.getOrder.mockReturnValueOnce(
    new Promise<Order>((resolve) => {
      finish = resolve
    }),
  )
  api.getOrder.mockResolvedValueOnce({ id: 2, order_number: 'AG-2' })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  const stale = state.selectOrder({ id: 1 })
  await state.selectOrder({ id: 2 })
  finish({ id: 1, order_number: 'AG-1' } as Order)
  await stale
  expect(state.selected.value?.id).toBe(2)
  expect(api.getOrderHistory).not.toHaveBeenCalledWith(1)
  scope.stop()
})
