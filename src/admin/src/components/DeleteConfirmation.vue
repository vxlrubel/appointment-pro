<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import { useModalDialog } from '../composables/useModalDialog'
const props = withDefaults(defineProps<{ open: boolean; heading?: string; bodyText?: string }>(), {
  heading: 'Confirm deletion',
  bodyText: 'Permanently delete the selected records?',
})
const emit = defineEmits<{ confirm: []; cancel: [] }>()
const dialog = ref<HTMLElement | null>(null),
  overlay = ref<HTMLElement | null>(null),
  noButton = ref<HTMLButtonElement | null>(null)
const id = useId()
let answered = false
watch(
  () => props.open,
  (value) => {
    if (value) answered = false
  },
)
function respond(confirmed: boolean) {
  if (!props.open || answered) return
  answered = true
  if (confirmed) emit('confirm')
  else emit('cancel')
}
useModalDialog(
  () => props.open,
  overlay,
  dialog,
  () => respond(false),
  noButton,
)
</script>

<template>
  <Teleport to="body">
    <div v-if="open" ref="overlay" class="px-delete-overlay" @mousedown.self="respond(false)">
      <section
        ref="dialog"
        class="px-delete-dialog"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="`${id}-heading`"
        :aria-describedby="`${id}-body`"
      >
        <div class="px-delete-icon" aria-hidden="true">
          <svg
            viewBox="0 0 24 24"
            width="28"
            height="28"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path
              d="M3 6h18M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 6l1 14a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1l1-14M10 10v7M14 10v7"
            />
          </svg>
        </div>
        <h2 :id="`${id}-heading`">{{ heading }}</h2>
        <p :id="`${id}-body`">{{ bodyText }}</p>
        <div class="px-delete-actions">
          <button
            ref="noButton"
            type="button"
            class="px-button px-button-ghost"
            @click="respond(false)"
          >
            No
          </button>
          <button type="button" class="px-button px-button-danger" @click="respond(true)">
            Yes
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.px-delete-overlay {
  position: fixed;
  inset: 0;
  z-index: 2147483000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  overflow-y: auto;
  background: rgba(15, 23, 42, 0.48);
  backdrop-filter: blur(3px);
}
.px-delete-dialog {
  width: 100%;
  max-width: 440px;
  max-height: calc(100dvh - 48px);
  overflow-y: auto;
  box-sizing: border-box;
  padding: 28px;
  border: 1px solid var(--px-line, #dee2e6);
  border-radius: 14px;
  background: var(--px-card, #fff);
  color: var(--px-color, #1e1e1e);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
}
.px-delete-icon {
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  margin-bottom: 18px;
  border-radius: 14px;
  color: var(--px-cancel-color, #f70063);
  background: #fff0f5;
}
.px-delete-dialog h2 {
  margin: 0 0 12px;
  padding: 0;
  color: inherit;
  font-size: 21px;
  line-height: 1.35;
}
.px-delete-dialog p {
  margin: 0;
  color: #646970;
  font-size: 14px;
  line-height: 1.7;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.px-delete-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 26px;
  padding-top: 20px;
  border-top: 1px solid var(--px-line, #dee2e6);
}
.px-delete-actions button {
  min-width: 88px;
  padding: 9px 18px;
}
.px-delete-actions button:focus-visible {
  outline: 2px solid var(--px-primary, #0d6efd);
  outline-offset: 3px;
}
.px-delete-overlay {
  animation: px-delete-overlay-in 0.18s ease both;
}
.px-delete-dialog {
  animation: px-delete-dialog-in 0.18s ease both;
}
@keyframes px-delete-overlay-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes px-delete-dialog-in {
  from {
    transform: translateY(10px) scale(0.98);
  }
  to {
    transform: translateY(0) scale(1);
  }
}
@media (prefers-reduced-motion: reduce) {
  .px-delete-overlay,
  .px-delete-dialog {
    animation: none;
  }
}
@media (max-width: 480px) {
  .px-delete-overlay {
    padding: 16px;
  }
  .px-delete-dialog {
    padding: 22px;
  }
}
</style>
