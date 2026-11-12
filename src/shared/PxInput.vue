<script lang="ts">
import { defineComponent, getCurrentInstance, h, vModelText, withDirectives } from 'vue'
import type { PropType } from 'vue'

export default defineComponent({
  name: 'PxInput',
  inheritAttrs: false,
  props: {
    modelValue: { default: undefined },
    modelModifiers: {
      type: Object as PropType<Record<string, boolean>>,
      default: () => ({}),
    },
    type: { type: String, default: 'text' },
  },
  emits: ['update:modelValue'],
  setup(props, { attrs, emit }) {
    const instance = getCurrentInstance()!
    return () => {
      const input = h('input', {
        ...attrs,
        class: ['px-input', attrs.class],
        type: props.type,
        'onUpdate:modelValue': (value: unknown) => emit('update:modelValue', value),
      })

      // Use Vue's native input behavior for composition, .lazy, .trim and .number.
      // Unbound inputs (especially file uploads) keep their native value handling.
      return props.type !== 'file' && instance.vnode.props?.['onUpdate:modelValue']
        ? withDirectives(input, [[vModelText, props.modelValue, undefined, props.modelModifiers]])
        : input
    }
  },
})
</script>

<style scoped>
input.px-input {
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  height: 40px;
  min-height: 40px;
  margin: 0;
  padding: 6px 10px;
  border: 1px solid var(--px-line, #dee2e6);
  border-radius: 6px;
  background: var(--px-card, #fff);
  color: var(--px-color, #1e1e1e);
  box-shadow: 0 1px 2px #0f172a05;
  font: inherit;
  line-height: 1.5;
  vertical-align: middle;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
input.px-input::placeholder {
  color: #7b8492;
  opacity: 1;
}
input.px-input:hover:not(:disabled):not([readonly]) {
  border-color: #a5b4c8;
}
input.px-input:focus {
  outline: 2px solid #0d6efd40;
  outline-offset: 1px;
  border-color: var(--px-primary, #0d6efd);
  box-shadow: 0 0 0 3px #0d6efd14;
}
input.px-input:disabled {
  background: #f6f7f9;
  color: #7b8492;
  cursor: not-allowed;
  opacity: 0.65;
}
input.px-input[readonly] {
  background: #f6f7f9;
}
input.px-input[aria-invalid='true'],
input.px-input:user-invalid {
  border-color: var(--px-cancel-color, #f70063);
}
input.px-input[type='file'] {
  padding: 0 10px 0 0;
  overflow: hidden;
}
input.px-input::file-selector-button {
  height: 33px;
  margin-inline-end: 10px;
  padding: 0 10px;
  border: 0;
  border-inline-end: 1px solid var(--px-line, #dee2e6);
  background: #f6f7f9;
  color: inherit;
  font: inherit;
  cursor: pointer;
}
input.px-input:disabled::file-selector-button {
  cursor: not-allowed;
}
input.px-input[type='color'] {
  padding: 4px;
  cursor: pointer;
}
input.px-input[type='range'] {
  padding-inline: 0;
  accent-color: var(--px-primary, #0d6efd);
}
input.px-input[type='hidden'] {
  display: none;
}
@media (prefers-reduced-motion: reduce) {
  input.px-input {
    transition: none;
  }
}
</style>
