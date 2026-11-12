/** Keeps the category row below the shared header and reveals keyboard focus below both. */
export function useStickyCatalogNavigation() {
  const navigation = ref<HTMLElement | null>(null)
  const headerHeight = useSiteHeaderHeight()
  const pinned = ref(false)
  let observer: ResizeObserver | undefined
  let frame: number | undefined

  function updatePinned() {
    const top = navigation.value?.getBoundingClientRect().top
    pinned.value = top !== undefined && Math.abs(top - headerHeight.value) < 1
  }

  function revealFocusedControl(event: FocusEvent) {
    const target = event.target
    const row = navigation.value
    if (
      !row ||
      row.getClientRects().length === 0 ||
      !(target instanceof HTMLElement) ||
      target.closest('dialog') ||
      row.contains(target) ||
      !(
        row.compareDocumentPosition(target) & Node.DOCUMENT_POSITION_FOLLOWING
      ) ||
      !target.matches(':focus-visible')
    )
      return

    if (frame !== undefined) cancelAnimationFrame(frame)
    frame = requestAnimationFrame(() => {
      frame = undefined
      if (!target.isConnected || document.activeElement !== target) return
      const offset = headerHeight.value + row.getBoundingClientRect().height + 8
      const top = target.getBoundingClientRect().top
      if (top < offset || top >= window.innerHeight)
        window.scrollBy({ top: top - offset, behavior: 'instant' })
    })
  }

  onMounted(() => {
    window.addEventListener('scroll', updatePinned, { passive: true })
    window.addEventListener('resize', updatePinned)
    observer = new ResizeObserver(updatePinned)
    watch(
      navigation,
      (element) => {
        observer?.disconnect()
        if (element) observer?.observe(element)
        updatePinned()
      },
      { immediate: true, flush: 'post' },
    )
    watch(headerHeight, updatePinned, { flush: 'post' })
  })

  onUnmounted(() => {
    window.removeEventListener('scroll', updatePinned)
    window.removeEventListener('resize', updatePinned)
    observer?.disconnect()
    if (frame !== undefined) cancelAnimationFrame(frame)
  })

  return { navigation, headerHeight, pinned, revealFocusedControl }
}
