import type { NuxtApp } from '#app'
import {
  CartApiError,
  changeCartItem,
  createCartItem,
  deleteCartItem,
  fetchCart,
} from '../services/cart'
import { cartTotal } from '../utils/totals'
import { productUnitLabel } from '~/utils/productUnits'
import type { CartItem, CartProduct } from '../types/cart'

const storageKey = 'agatceramic.cart.token'
const pendingRestores = new WeakMap<NuxtApp, Promise<void>>()
const validToken = (value: string | null): value is string =>
  value !== null && /^[a-f0-9]{64}$/.test(value)

export function useCart() {
  const nuxtApp = useNuxtApp()
  const { publicBase } = usePublicApiConfig()
  const items = useState<CartItem[]>('cart-items', () => [])
  const open = useState('cart-open', () => false)
  const busy = useState('cart-busy', () => false)
  const problem = useState<{ message: string; canRetry: boolean } | null>(
    'cart-problem',
    () => null,
  )
  const error = computed(() => problem.value?.message ?? '')
  const canRetry = computed(() => problem.value?.canRetry ?? false)
  const initialized = useState('cart-initialized', () => false)
  // Token stays null during SSR. Storage is read on the client.
  const token = useState<string | null>('cart-token', () => null)
  const restored = useState('cart-token-restored', () => false)
  const itemCount = computed(() => items.value.length)
  const total = computed(() => cartTotal(items.value))

  function persistToken(value: string | null) {
    token.value = value
    if (!import.meta.client) return
    try {
      if (value) localStorage.setItem(storageKey, value)
      else localStorage.removeItem(storageKey)
    } catch {
      // Blocked storage still permits this tab's in-memory cart.
    }
  }

  function restoreToken() {
    if (!import.meta.client || restored.value) return
    restored.value = true
    try {
      const saved = localStorage.getItem(storageKey)
      if (validToken(saved)) token.value = saved
      else localStorage.removeItem(storageKey)
    } catch {
      // Do not block shopping when browser storage is unavailable.
    }
  }

  async function readCart(savedOnly = false) {
    restoreToken()
    let cart
    try {
      cart = await fetchCart(publicBase, token.value)
    } catch (failure) {
      if (
        !token.value ||
        !(failure instanceof CartApiError) ||
        failure.status !== 404
      )
        throw failure
      // Only a confirmed failed read discards an expired token; never replay writes.
      persistToken(null)
      items.value = []
      initialized.value = false
      if (savedOnly) return
      cart = await fetchCart(publicBase, null)
    }
    if (!validToken(cart.identifier)) throw new Error('Invalid cart response')
    persistToken(cart.identifier)
    items.value = cart.items
    initialized.value = true
  }

  function reportFailure(failure: unknown, product?: CartProduct | null) {
    const apiError = failure instanceof CartApiError ? failure : null
    const available = apiError?.availableQuantity
    let message =
      apiError?.message ?? 'Не удалось обновить корзину. Попробуйте ещё раз.'
    if (available === 0)
      message = product
        ? `Товара «${product.name}» больше нет в наличии.`
        : 'Товара больше нет в наличии.'
    else if (available != null && product)
      message = `В наличии ${available} ${productUnitLabel(product.unit)} товара «${product.name}».`

    problem.value = { message, canRetry: apiError?.status !== 422 }
    if (apiError?.status === 404) initialized.value = false
  }

  function restoreSavedCart() {
    if (!import.meta.client || busy.value || initialized.value) return
    restoreToken()
    if (!token.value || pendingRestores.has(nuxtApp)) return

    const restoration = readCart(true)
      .catch(() => {
        // Opening the cart or adding a product can retry the background read.
      })
      .finally(() => pendingRestores.delete(nuxtApp))
    pendingRestores.set(nuxtApp, restoration)
  }

  async function loadCart(): Promise<void> {
    if (!import.meta.client || busy.value) return
    busy.value = true
    problem.value = null
    try {
      const restoration = pendingRestores.get(nuxtApp)
      if (restoration) {
        await restoration
        if (initialized.value) return
      }
      await readCart()
    } catch (failure) {
      reportFailure(failure)
    } finally {
      busy.value = false
    }
  }

  function validQuantity(quantity: number) {
    return Number.isInteger(quantity) && quantity >= 1 && quantity <= 9999
  }

  async function addItem(
    productId: number,
    quantity: number,
  ): Promise<boolean> {
    if (!import.meta.client || busy.value) return false
    if (
      !validQuantity(quantity) ||
      !Number.isInteger(productId) ||
      productId < 1
    ) {
      problem.value = {
        message: 'Укажите целое количество от 1 до 9999.',
        canRetry: false,
      }
      return false
    }
    busy.value = true
    problem.value = null
    try {
      await pendingRestores.get(nuxtApp)
      if (!initialized.value) await readCart()
      if (!token.value) throw new Error('Cart unavailable')
      const added = await createCartItem(
        publicBase,
        token.value,
        productId,
        quantity,
      )
      const index = items.value.findIndex((item) => item.id === added.id)
      if (index === -1) items.value.push(added)
      else items.value[index] = added
      return true
    } catch (failure) {
      reportFailure(failure)
      return false
    } finally {
      busy.value = false
    }
  }

  async function updateItem(
    itemId: number,
    quantity: number,
  ): Promise<boolean> {
    if (!import.meta.client || busy.value || !token.value) return false
    if (!validQuantity(quantity)) {
      problem.value = {
        message: 'Укажите целое количество от 1 до 9999.',
        canRetry: false,
      }
      return false
    }
    busy.value = true
    problem.value = null
    try {
      const updated = await changeCartItem(
        publicBase,
        token.value,
        itemId,
        quantity,
      )
      items.value = items.value.map((item) =>
        item.id === itemId ? updated : item,
      )
      return true
    } catch (failure) {
      reportFailure(
        failure,
        items.value.find((item) => item.id === itemId)?.product,
      )
      return false
    } finally {
      busy.value = false
    }
  }

  async function removeItem(itemId: number): Promise<boolean> {
    if (!import.meta.client || busy.value || !token.value) return false
    busy.value = true
    problem.value = null
    try {
      await deleteCartItem(publicBase, token.value, itemId)
      items.value = items.value.filter((item) => item.id !== itemId)
      return true
    } catch (failure) {
      reportFailure(failure)
      return false
    } finally {
      busy.value = false
    }
  }

  return {
    items,
    open,
    busy,
    error,
    canRetry,
    itemCount,
    total,
    restoreSavedCart,
    loadCart,
    addItem,
    updateItem,
    removeItem,
  }
}
