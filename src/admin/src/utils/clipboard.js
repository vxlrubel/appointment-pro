/** Copy on HTTPS and older/local HTTP admin pages without losing focus. */
export async function copyText(text) {
  if (navigator.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text)
      return
    } catch {
      /* Try the selection fallback below. */
    }
  }
  const focus = document.activeElement
  const selection = window.getSelection()
  const ranges = selection
    ? Array.from({ length: selection.rangeCount }, (_, index) =>
        selection.getRangeAt(index).cloneRange(),
      )
    : []
  const inputSelection =
    focus instanceof HTMLInputElement || focus instanceof HTMLTextAreaElement
      ? {
          start: focus.selectionStart,
          end: focus.selectionEnd,
          direction: focus.selectionDirection,
        }
      : null
  const field = document.createElement('textarea')
  field.value = text
  field.setAttribute('readonly', '')
  field.setAttribute('aria-label', 'Affiliate link to copy')
  Object.assign(field.style, {
    position: 'fixed',
    top: '0',
    left: '0',
    width: '1px',
    height: '1px',
    opacity: '0',
    fontSize: '16px',
    pointerEvents: 'none',
  })
  document.body.appendChild(field)
  try {
    field.focus({ preventScroll: true })
    field.select()
    field.setSelectionRange(0, field.value.length)
    if (!document.execCommand('copy')) throw new Error('Clipboard access was denied.')
  } finally {
    field.remove()
    if (focus instanceof HTMLElement && focus.isConnected) focus.focus({ preventScroll: true })
    if (inputSelection && inputSelection.start !== null && inputSelection.end !== null)
      focus.setSelectionRange(
        inputSelection.start,
        inputSelection.end,
        inputSelection.direction || undefined,
      )
    if (selection) {
      selection.removeAllRanges()
      ranges.forEach((range) => selection.addRange(range))
    }
  }
}
