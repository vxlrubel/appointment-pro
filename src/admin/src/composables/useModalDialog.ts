import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue'
type Entry = {
  overlay: HTMLElement
  dialog: HTMLElement
  close: () => void
  focus: HTMLElement | null
}
const stack: Entry[] = []
let overflow = ''
const inert = new Map<HTMLElement, boolean>()
function sync() {
  const top = stack.at(-1)
  for (const child of Array.from(document.body.children)) {
    if (!(child instanceof HTMLElement)) continue
    if (!inert.has(child)) inert.set(child, child.inert)
    child.inert = top
      ? child !== top.overlay && !child.contains(top.overlay)
      : inert.get(child) || false
  }
}
function keydown(event: KeyboardEvent) {
  const top = stack.at(-1)
  if (!top) return
  // An expanded select handles Escape before its containing modal closes.
  if (
    event.key === 'Escape' &&
    event.target instanceof Element &&
    event.target.closest('[role="combobox"][aria-expanded="true"]')
  )
    return
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopImmediatePropagation()
    top.close()
    return
  }
  if (event.key !== 'Tab') return
  const fields = Array.from(
    top.dialog.querySelectorAll<HTMLElement>(
      'button:not(:disabled), input:not(:disabled), select:not(:disabled):not([tabindex="-1"]), textarea:not(:disabled), a[href], [tabindex="0"]',
    ),
  ).filter((el) => el.getClientRects().length)
  const first = fields[0],
    last = fields.at(-1)
  if (
    event.shiftKey &&
    (document.activeElement === first || !top.dialog.contains(document.activeElement))
  ) {
    event.preventDefault()
    last?.focus()
  } else if (
    !event.shiftKey &&
    (document.activeElement === last || !top.dialog.contains(document.activeElement))
  ) {
    event.preventDefault()
    first?.focus()
  }
}
export function useModalDialog(
  open: () => boolean,
  overlay: Ref<HTMLElement | null>,
  dialog: Ref<HTMLElement | null>,
  close: () => void,
  initial?: Ref<HTMLElement | null>,
) {
  let entry: Entry | null = null
  function release() {
    if (!entry) return
    const old = entry
    const index = stack.indexOf(old)
    const wasTop = index === stack.length - 1
    if (index !== -1) stack.splice(index, 1)
    entry = null
    sync()
    if (!stack.length) {
      for (const [el, value] of inert) el.inert = value
      inert.clear()
      document.body.style.overflow = overflow
      document.removeEventListener('keydown', keydown, true)
    }
    if (wasTop && old.focus?.isConnected && !old.focus.closest('[inert]')) old.focus.focus()
  }
  watch(
    open,
    async (value) => {
      if (!value) {
        release()
        return
      }
      const focus = document.activeElement instanceof HTMLElement ? document.activeElement : null
      await nextTick()
      if (!open() || !overlay.value || !dialog.value || entry) return
      if (!stack.length) {
        overflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        document.addEventListener('keydown', keydown, true)
      }
      entry = { overlay: overlay.value, dialog: dialog.value, close, focus }
      stack.push(entry)
      sync()
      ;(
        initial?.value ||
        dialog.value.querySelector<HTMLElement>('input,select,textarea,button') ||
        dialog.value
      ).focus()
    },
    { immediate: true, flush: 'post' },
  )
  onBeforeUnmount(release)
}
