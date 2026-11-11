import { onBeforeUnmount, reactive } from 'vue'

interface ConfirmationText {
  heading: string
  bodyText: string
}

export function useDeleteConfirmation() {
  const confirmation = reactive({ open: false, heading: '', bodyText: '' })
  let pending: ((confirmed: boolean) => void) | null = null

  function resolveDelete(confirmed: boolean) {
    const resolve = pending
    pending = null
    confirmation.open = false
    resolve?.(confirmed)
  }

  function confirmDelete(text: ConfirmationText): Promise<boolean> {
    if (pending) return Promise.resolve(false)
    Object.assign(confirmation, text, { open: true })
    return new Promise((resolve) => {
      pending = resolve
    })
  }

  onBeforeUnmount(() => resolveDelete(false))
  return { confirmation, confirmDelete, resolveDelete }
}
