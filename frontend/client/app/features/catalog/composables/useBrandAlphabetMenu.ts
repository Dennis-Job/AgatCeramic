import type { BrandAlphabetGroup } from '../utils/brandAlphabet'

export function useBrandAlphabetMenu(
  groups: ComputedRef<BrandAlphabetGroup[]>,
) {
  const root = ref<HTMLElement | null>(null)
  const openKey = ref<string | null>(null)
  const positions = ref<Record<string, Record<string, string>>>({})
  let openedByHover = false
  let observer: ResizeObserver | undefined
  const trigger = (key: string) =>
    root.value?.querySelector<HTMLButtonElement>(
      `[data-brand-trigger="${key}"]`,
    )
  const panel = (key: string) =>
    root.value?.querySelector<HTMLElement>(`[data-brand-panel="${key}"]`)

  function close(restoreFocus = false) {
    const key = openKey.value
    if (!key) return
    openKey.value = null
    openedByHover = false
    const element = panel(key)
    if (element?.matches(':popover-open')) element.hidePopover()
    if (restoreFocus) trigger(key)?.focus({ preventScroll: true })
  }

  function updatePosition() {
    if (!openKey.value || !root.value) return
    const bounds = root.value.getBoundingClientRect()
    const headerBottom =
      document.querySelector('.site-header')?.getBoundingClientRect().bottom ||
      0
    const viewportHeight = window.innerHeight
    const viewportGap = 12
    // Keep room for one 44px link, the 24px panel padding and its border.
    const minimumPanelHeight = 94
    const below = viewportHeight - bounds.bottom - viewportGap
    const above = bounds.top - headerBottom - viewportGap
    const opensAbove = below < minimumPanelHeight && above > below
    const maxHeight = opensAbove ? above : below
    if (
      bounds.top < headerBottom ||
      bounds.top >= viewportHeight ||
      maxHeight < minimumPanelHeight
    ) {
      close()
      return
    }
    positions.value[openKey.value] = {
      left: `${bounds.left}px`,
      top: opensAbove ? 'auto' : `${bounds.bottom}px`,
      bottom: opensAbove ? `${viewportHeight - bounds.top}px` : 'auto',
      width: `${bounds.width}px`,
      maxHeight: `${maxHeight}px`,
    }
  }

  async function open(key: string, hover = false) {
    if (openKey.value === key) return
    close()
    openKey.value = key
    openedByHover = hover
    await nextTick()
    if (openKey.value !== key) return
    updatePosition()
    if (openKey.value === key) panel(key)?.showPopover()
  }

  function enter(key: string, event: PointerEvent) {
    if (event.pointerType === 'mouse') void open(key, true)
  }

  function toggle(key: string, event: MouseEvent) {
    if (openKey.value !== key) {
      void open(key)
    } else if (openedByHover && event.detail > 0) {
      // pointerenter precedes the first mouse click; that click should retain the panel.
      openedByHover = false
    } else close()
  }

  function leave(event: PointerEvent) {
    if (event.pointerType !== 'mouse') return
    if (
      event.relatedTarget instanceof Node &&
      root.value?.contains(event.relatedTarget)
    )
      return
    // Keep a keyboard user's focused link available even when the mouse leaves.
    const focused = document.activeElement
    if (
      focused instanceof HTMLElement &&
      focused.closest('[data-brand-panel]') &&
      root.value?.contains(focused)
    )
      return
    close()
  }

  function outside(event: Event) {
    if (event.target instanceof Node && !root.value?.contains(event.target))
      close()
  }
  function keydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && openKey.value) {
      event.preventDefault()
      close(true)
    }
  }
  function revealLink(event: FocusEvent) {
    if (
      event.target instanceof HTMLElement &&
      event.target.closest('[data-brand-panel]')
    ) {
      event.target.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
        behavior: 'instant',
      })
    } else if (
      event.target instanceof HTMLElement &&
      event.target.dataset.brandTrigger !== openKey.value
    )
      close()
  }

  const route = useRoute()
  watch(
    () => route.fullPath,
    () => close(),
  )
  watch(groups, () => close())
  onMounted(() => {
    document.addEventListener('pointerdown', outside)
    document.addEventListener('focusin', outside)
    document.addEventListener('keydown', keydown)
    window.addEventListener('resize', updatePosition)
    window.addEventListener('scroll', updatePosition, true)
    observer = new ResizeObserver(updatePosition)
    if (root.value) observer.observe(root.value)
  })
  onBeforeUnmount(() => {
    close()
    observer?.disconnect()
    document.removeEventListener('pointerdown', outside)
    document.removeEventListener('focusin', outside)
    document.removeEventListener('keydown', keydown)
    window.removeEventListener('resize', updatePosition)
    window.removeEventListener('scroll', updatePosition, true)
  })
  return { root, openKey, positions, close, enter, toggle, leave, revealLink }
}
