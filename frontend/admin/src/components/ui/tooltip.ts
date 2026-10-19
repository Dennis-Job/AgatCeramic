import type { ObjectDirective } from 'vue'

type TooltipValue = string | false | undefined
type TooltipState = {
  update: (value: TooltipValue) => void
  dispose: () => void
}
const states = new WeakMap<HTMLElement, TooltipState>()
let sequence = 0

// Tooltips are opt-in: accessible names alone must not create visual overlays.
export const vTooltip: ObjectDirective<HTMLElement, TooltipValue> = {
  mounted(el, binding) {
    const doc = el.ownerDocument
    const win = doc.defaultView!
    const id = `admin-tooltip-${++sequence}`
    let value = binding.value
    let tooltip: HTMLDivElement | undefined
    let timer: ReturnType<typeof setTimeout> | undefined
    let pointerOver = false
    let tooltipHovered = false
    let dismissed = false
    const label = () => (value === false ? '' : value || '')
    const enabled = () =>
      !el.matches(':disabled, [aria-disabled="true"]') && !!label()

    function position() {
      if (!tooltip) return
      const anchor = el.getBoundingClientRect()
      const box = tooltip.getBoundingClientRect()
      const margin = 8
      const left = Math.max(
        margin,
        Math.min(
          anchor.left + (anchor.width - box.width) / 2,
          win.innerWidth - box.width - margin,
        ),
      )
      const above = anchor.top - box.height - margin
      const top =
        above >= margin
          ? above
          : Math.min(
              anchor.bottom + margin,
              win.innerHeight - box.height - margin,
            )
      tooltip.style.left = `${left}px`
      tooltip.style.top = `${Math.max(margin, top)}px`
    }

    function hide() {
      clearTimeout(timer)
      tooltipHovered = false
      tooltip?.remove()
      tooltip = undefined
      const descriptions = (el.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter((part) => part && part !== id)
      if (descriptions.length)
        el.setAttribute('aria-describedby', descriptions.join(' '))
      else el.removeAttribute('aria-describedby')
      win.removeEventListener('keydown', onKeydown, true)
      win.removeEventListener('resize', hide)
      doc.removeEventListener('scroll', hide, true)
    }

    function show() {
      clearTimeout(timer)
      if (tooltip || dismissed || !enabled()) return
      tooltip = doc.createElement('div')
      tooltip.id = id
      tooltip.className = 'admin-tooltip'
      tooltip.role = 'tooltip'
      tooltip.textContent = label()
      tooltip.addEventListener('pointerenter', onTooltipEnter)
      tooltip.addEventListener('pointerleave', onTooltipLeave)
      // Keep the description in its semantic context; fixed positioning escapes
      // table/input scroll wrappers without placing dialog text outside the modal.
      const container =
        el.closest('[role="dialog"], main, header') ??
        doc.querySelector('main') ??
        doc.body
      container.append(tooltip)
      const descriptions = el.getAttribute('aria-describedby')
      el.setAttribute(
        'aria-describedby',
        [descriptions, id].filter(Boolean).join(' '),
      )
      position()
      win.addEventListener('keydown', onKeydown, true)
      win.addEventListener('resize', hide)
      doc.addEventListener('scroll', hide, true)
    }
    function onKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        dismissed = pointerOver
        hide()
      }
    }
    function onEnter(event: PointerEvent) {
      if (event.pointerType === 'touch') return
      pointerOver = true
      clearTimeout(timer)
    }
    function onMove(event: PointerEvent) {
      if (event.pointerType === 'touch') return
      pointerOver = true
      if (dismissed) return
      clearTimeout(timer)
      doc.addEventListener('scroll', hide, true)
      timer = setTimeout(show, 300)
    }
    function onLeave() {
      pointerOver = false
      dismissed = false
      scheduleHide()
    }
    function onTooltipEnter() {
      tooltipHovered = true
      clearTimeout(timer)
    }
    function onTooltipLeave() {
      tooltipHovered = false
      scheduleHide()
    }
    function scheduleHide() {
      clearTimeout(timer)
      timer = setTimeout(() => {
        if (!pointerOver && !tooltipHovered) {
          dismissed = false
          hide()
        }
      }, 150)
    }
    function onClick() {
      dismissed = pointerOver
      tooltipHovered = false
      hide()
    }

    el.addEventListener('pointerenter', onEnter)
    el.addEventListener('pointermove', onMove)
    el.addEventListener('pointerleave', onLeave)
    el.addEventListener('click', onClick)
    states.set(el, {
      update(next) {
        value = next
        if (!enabled()) hide()
        else if (tooltip) {
          tooltip.textContent = label()
          // Vue may have updated the caller's aria-describedby independently.
          const descriptions = (el.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter((part) => part && part !== id)
          el.setAttribute('aria-describedby', [...descriptions, id].join(' '))
          position()
        }
      },
      dispose() {
        hide()
        el.removeEventListener('pointerenter', onEnter)
        el.removeEventListener('pointermove', onMove)
        el.removeEventListener('pointerleave', onLeave)
        el.removeEventListener('click', onClick)
      },
    })
  },
  updated(el, binding) {
    states.get(el)?.update(binding.value)
  },
  beforeUnmount(el) {
    states.get(el)?.dispose()
    states.delete(el)
  },
}
