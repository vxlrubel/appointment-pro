<script setup lang="ts">
import { toast } from "./toast";

const labels = {
  success: "Success",
  error: "Error",
  working: "Working",
  info: "Information",
};
</script>

<template>
  <Teleport to="body">
    <section class="px-toasts" aria-label="Notifications">
      <TransitionGroup name="px-toast" tag="div" class="px-toast-stack">
        <div
          v-for="item in toast.messages"
          :key="item.id"
          class="px-toast"
          :class="`px-toast--${item.type}`"
          :role="item.type === 'error' ? 'alert' : 'status'"
          aria-atomic="true"
          @mouseenter="toast.pause(item.id)"
          @mouseleave="toast.resume(item.id)"
          @focusin="toast.pause(item.id)"
          @focusout="toast.resume(item.id)"
        >
          <span
            class="px-toast-icon"
            :class="{ 'is-spinning': item.type === 'working' }"
            aria-hidden="true"
          >
            <svg
              v-if="item.type !== 'working'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path v-if="item.type === 'success'" d="m5 12 4 4L19 6" />
              <path
                v-else-if="item.type === 'error'"
                d="m7 7 10 10M17 7 7 17"
              />
              <path v-else d="M12 11v6m0-10v1" />
            </svg>
          </span>
          <div class="px-toast-content">
            <strong>{{ labels[item.type] }}</strong>
            <p>{{ item.message }}</p>
          </div>
          <button
            type="button"
            class="px-toast-close"
            aria-label="Dismiss notification"
            @click="toast.dismiss(item.id)"
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <path d="m6 6 12 12M18 6 6 18" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </section>
  </Teleport>
</template>

<style scoped>
.px-toasts {
  position: fixed;
  z-index: 2147483600;
  inset-block-start: max(48px, env(safe-area-inset-top));
  inset-inline-end: max(20px, env(safe-area-inset-right));
  width: min(390px, calc(100vw - 32px));
  pointer-events: none;
  font:
    14px/1.5 Inter,
    system-ui,
    sans-serif;
  color: #1e293b;
}
.px-toast-stack {
  display: grid;
  gap: 10px;
}
.px-toast {
  --toast-accent: #0d6efd;
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  box-sizing: border-box;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-inline-start: 3px solid var(--toast-accent);
  border-radius: 10px;
  background: #fff;
  box-shadow:
    0 8px 30px #0f172a1a,
    0 2px 6px #0f172a0a;
  pointer-events: auto;
}
.px-toast--success {
  --toast-accent: #15803d;
}
.px-toast--error {
  --toast-accent: #dc2626;
}
.px-toast-icon {
  display: inline-flex;
  flex: 0 0 22px;
  width: 22px;
  height: 22px;
  box-sizing: border-box;
  color: var(--toast-accent);
}
.px-toast-icon svg,
.px-toast-close svg {
  width: 100%;
  height: 100%;
}
.px-toast-icon.is-spinning {
  border: 2px solid #dbeafe;
  border-top-color: var(--toast-accent);
  border-radius: 50%;
  animation: px-toast-spin 0.8s linear infinite;
}
.px-toast-content {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}
.px-toast-content strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.px-toast-content p {
  margin: 2px 0 0;
  font: inherit;
  color: #475569;
}
.px-toast-close {
  display: inline-flex;
  flex: 0 0 26px;
  width: 26px;
  height: 26px;
  min-height: 0;
  margin: -3px -3px 0 0;
  padding: 5px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: #64748b;
  box-shadow: none;
  cursor: pointer;
}
.px-toast-close:hover {
  background: #f1f5f9;
  color: #0f172a;
}
.px-toast-close:focus-visible {
  outline: 2px solid #0d6efd;
  outline-offset: 2px;
}
.px-toast-enter-active,
.px-toast-leave-active,
.px-toast-move {
  transition:
    opacity 180ms ease,
    transform 180ms ease;
}
.px-toast-enter-from,
.px-toast-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
@keyframes px-toast-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .px-toast-enter-active,
  .px-toast-leave-active,
  .px-toast-move,
  .px-toast-icon.is-spinning {
    animation: none;
    transition: none;
  }
}
</style>
