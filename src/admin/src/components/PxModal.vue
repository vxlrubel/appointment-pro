<script setup lang="ts">
import { ref, useId } from 'vue'
import { useModalDialog } from '../composables/useModalDialog'
const props = withDefaults(
  defineProps<{
    open: boolean
    heading: string
    primaryText?: string
    busy?: boolean
    formId?: string
    hideFooter?: boolean
  }>(),
  { primaryText: 'Save changes', busy: false, hideFooter: false },
)
const emit = defineEmits<{ close: []; submit: [] }>()
const overlay = ref<HTMLElement | null>(null),
  dialog = ref<HTMLElement | null>(null)
const id = useId()
function close() {
  if (!props.busy) emit('close')
}
useModalDialog(() => props.open, overlay, dialog, close)
</script>
<template>
  <Teleport to="body"
    ><div v-if="open" ref="overlay" class="px-modal-overlay" @mousedown.self="close">
      <section
        ref="dialog"
        class="px-modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="id + '-heading'"
        :aria-busy="busy"
        tabindex="-1"
      >
        <header class="px-modal-header">
          <h2 :id="id + '-heading'">{{ heading }}</h2>
          <button
            type="button"
            class="px-modal-close"
            aria-label="Close modal"
            :disabled="busy"
            @click="close"
          >
            &times;
          </button>
        </header>
        <div class="px-modal-body"><slot /></div>
        <footer v-if="!hideFooter" class="px-modal-footer">
          <button type="button" class="px-button px-button-ghost" :disabled="busy" @click="close">
            Cancel</button
          ><button
            class="px-button"
            :type="formId ? 'submit' : 'button'"
            :form="formId"
            :disabled="busy"
            @click="!formId && emit('submit')"
          >
            {{ busy ? 'Saving...' : primaryText }}
          </button>
        </footer>
      </section>
    </div></Teleport
  >
</template>
<style>
.px-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2147482000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
  backdrop-filter: blur(3px);
  box-sizing: border-box;
  font-family: Arial, sans-serif;
  color: var(--px-color, #1e1e1e);
}
.px-modal {
  width: min(960px, 100%);
  max-height: calc(100dvh - 48px);
  display: flex;
  flex-direction: column;
  background: var(--px-card, #fff);
  border: 1px solid var(--px-line, #dee2e6);
  border-radius: 14px;
  box-shadow: 0 24px 80px #0f172a38;
  animation: px-modal-in 0.18s ease both;
}
.px-modal-header,
.px-modal-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 24px;
  flex-shrink: 0;
}
.px-modal-header {
  justify-content: space-between;
  border-bottom: 1px solid var(--px-line, #dee2e6);
}
.px-modal-header h2 {
  margin: 0;
  font-size: 21px;
  line-height: 1.4;
}
.px-modal-close {
  border: 0;
  background: transparent;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
  color: #646970;
  padding: 4px 8px;
}
.px-modal-body {
  padding: 24px;
  overflow: auto;
  overscroll-behavior: contain;
}
.px-modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--px-line, #dee2e6);
}
.px-modal-body .px-card {
  border: 0;
  padding: 0;
  box-shadow: none;
}
.px-modal input,
.px-modal select,
.px-modal textarea {
  box-sizing: border-box;
}
.px-modal button:focus-visible {
  outline: 2px solid var(--px-primary, #0d6efd);
  outline-offset: 3px;
}
@keyframes px-modal-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@media (prefers-reduced-motion: reduce) {
  .px-modal {
    animation: none;
  }
}
@media (max-width: 600px) {
  .px-modal-overlay {
    padding: 10px;
  }
  .px-modal {
    max-height: calc(100dvh - 20px);
  }
  .px-modal-body {
    padding: 16px;
  }
}
</style>
