import type { ObjectDirective } from 'vue'

type TooltipValue = string | false | undefined
type TooltipState = {
  update: (value: TooltipValue) => void
  dispose: () => void
}
const states = new WeakMap<HTMLElement, TooltipState>()
let sequence = 0

// Shared by UiButton and the native controls inside UI primitives and the shell.
export const vTooltip: ObjectDirective<HTMLElement, TooltipValue> = {
  mounted(el, binding) {
    const doc = el.ownerDocument
    const win = doc.defaultView!
    const id = `admin-tooltip-${++sequence}`
    let value = binding.value
    let tooltip: HTMLDivElement | undefined
    let timer: ReturnType<typeof setTimeout> | undefined
    let hovered = false
    let focused = false
    let dismissed = false
    const label = () =>
      value === false ? '' : value || el.getAttribute('aria-label') || ''
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
      doc.removeEventListener('scroll', position, true)
      doc.removeEventListener('transitionend', position, true)
    }

    function show() {
      clearTimeout(timer)
      if (tooltip || dismissed || !enabled()) return
      tooltip = doc.createElement('div')
      tooltip.id = id
      tooltip.className = 'admin-tooltip'
      tooltip.role = 'tooltip'
      tooltip.textContent = label()
      tooltip.addEventListener('pointerenter', () => clearTimeout(timer))
      tooltip.addEventListener('pointerleave', scheduleHide)
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
      doc.addEventListener('scroll', position, true)
      doc.addEventListener('transitionend', position, true)
    }

    function scheduleHide() {
      clearTimeout(timer)
      if (!focused) timer = setTimeout(hide, 150)
    }
    function onKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        dismissed = true
        hide()
      }
    }
    function onEnter(event: PointerEvent) {
      if (event.pointerType === 'touch') return
      hovered = true
      dismissed = false
      clearTimeout(timer)
      timer = setTimeout(show, 300)
    }
    function onLeave() {
      hovered = false
      scheduleHide()
    }
    function onFocus() {
      focused = true
      dismissed = false
      show()
    }
    function onBlur() {
      focused = false
      if (!hovered) hide()
    }
    function onClick() {
      dismissed = true
      hide()
    }

    el.addEventListener('pointerenter', onEnter)
    el.addEventListener('pointerleave', onLeave)
    el.addEventListener('focus', onFocus)
    el.addEventListener('blur', onBlur)
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
        el.removeEventListener('pointerleave', onLeave)
        el.removeEventListener('focus', onFocus)
        el.removeEventListener('blur', onBlur)
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
