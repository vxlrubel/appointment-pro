<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  ref,
  useAttrs,
  useId,
  useSlots,
  watch,
} from "vue";

defineOptions({ inheritAttrs: false });
const props = defineProps({
  modelValue: { default: undefined },
  value: { default: undefined },
  modelModifiers: { type: Object, default: () => ({}) },
  disabled: Boolean,
  required: Boolean,
  placeholder: { type: String, default: "Select an option" },
});
const emit = defineEmits(["update:modelValue", "change"]);
const attrs = useAttrs(),
  slots = useSlots(),
  uid = useId();
const trigger = ref(null),
  native = ref(null),
  listbox = ref(null);
const open = ref(false),
  active = ref(-1),
  localValue = ref(undefined),
  invalid = ref(false);
const placement = ref("below"),
  position = ref({});
const triggerId = computed(() => attrs.id || uid + "-select");
const listId = uid + "-options";
const currentValue = computed(() =>
  props.modelValue !== undefined
    ? props.modelValue
    : props.value !== undefined
      ? props.value
      : localValue.value,
);
const buttonAttrs = computed(() =>
  Object.fromEntries(
    Object.entries(attrs).filter(
      ([key]) => !["class", "style", "name", "form", "id"].includes(key),
    ),
  ),
);
function textContent(children) {
  if (Array.isArray(children)) return children.map(textContent).join("");
  if (children && typeof children === "object")
    return typeof children.type === "symbol" && !children.children
      ? ""
      : textContent(children.children);
  return children == null ? "" : String(children);
}
// Option slots retain their original number, boolean, null and string values.
function readOptions(nodes, output = []) {
  for (const node of nodes || []) {
    if (node.type === "option") {
      const label = String(
        node.props?.label ?? textContent(node.children),
      ).trim();
      output.push({
        label,
        value:
          node.props && Object.hasOwn(node.props, "value")
            ? node.props.value
            : label,
        disabled:
          node.props?.disabled !== undefined &&
          node.props.disabled !== false &&
          node.props.disabled !== null,
        symbol: node.props?.["data-symbol"],
      });
    } else if (Array.isArray(node.children)) readOptions(node.children, output);
  }
  return output;
}
const options = computed(() => readOptions(slots.default?.()));
const matches = (a, b) =>
  a === b || (a != null && b != null && String(a) === String(b));
const selected = computed(() =>
  options.value.findIndex((option) =>
    matches(option.value, currentValue.value),
  ),
);
const label = computed(
  () =>
    options.value[selected.value]?.label ||
    options.value.find((option) => option.value === "")?.label ||
    props.placeholder,
);
const enabledIndices = computed(() =>
  options.value.flatMap((option, index) => (option.disabled ? [] : [index])),
);
let frame = 0,
  searchTimer = 0,
  search = "",
  resizeObserver;

function syncNative() {
  if (native.value) native.value.selectedIndex = selected.value;
}
function close(focus = false) {
  open.value = false;
  if (focus) trigger.value?.focus({ preventScroll: true });
}
function updatePosition() {
  if (!open.value || !trigger.value || !listbox.value) return;
  const rect = trigger.value.getBoundingClientRect();
  const viewport = window.visualViewport;
  const leftEdge = (viewport?.offsetLeft || 0) + 8;
  const topEdge = (viewport?.offsetTop || 0) + 8;
  const rightEdge = leftEdge + (viewport?.width || window.innerWidth) - 16;
  const bottomEdge = topEdge + (viewport?.height || window.innerHeight) - 16;
  if (rect.bottom < topEdge || rect.top > bottomEdge) {
    close();
    return;
  }
  const below = Math.max(0, bottomEdge - rect.bottom - 4);
  const above = Math.max(0, rect.top - topEdge - 4);
  const desired = Math.min(300, listbox.value.scrollHeight + 2);
  const upwards = below < desired && above > below;
  const height = Math.min(desired, upwards ? above : below);
  const width = Math.min(rect.width, rightEdge - leftEdge);
  placement.value = upwards ? "above" : "below";
  position.value = {
    left: Math.max(leftEdge, Math.min(rect.left, rightEdge - width)) + "px",
    top: (upwards ? rect.top - height - 4 : rect.bottom + 4) + "px",
    width: width + "px",
    maxHeight: height + "px",
    fontFamily: getComputedStyle(trigger.value).fontFamily,
    fontSize: getComputedStyle(trigger.value).fontSize,
  };
}
function schedulePosition(event) {
  if (event?.target instanceof Node && listbox.value?.contains(event.target))
    return;
  cancelAnimationFrame(frame);
  frame = requestAnimationFrame(updatePosition);
}
function outside(event) {
  if (
    !trigger.value?.contains(event.target) &&
    !listbox.value?.contains(event.target)
  )
    close();
}
function cleanup() {
  cancelAnimationFrame(frame);
  resizeObserver?.disconnect();
  resizeObserver = undefined;
  document.removeEventListener("pointerdown", outside, true);
  document.removeEventListener("focusin", outside, true);
  window.removeEventListener("scroll", schedulePosition, true);
  window.removeEventListener("resize", schedulePosition);
  window.visualViewport?.removeEventListener("resize", schedulePosition);
  window.visualViewport?.removeEventListener("scroll", schedulePosition);
}
async function reveal(index = selected.value) {
  if (
    props.disabled ||
    trigger.value?.matches(":disabled") ||
    !enabledIndices.value.length
  )
    return;
  active.value = enabledIndices.value.includes(index)
    ? index
    : enabledIndices.value[0];
  open.value = true;
  await nextTick();
  scrollActive();
}
function scrollActive() {
  nextTick(() => {
    const row = document.getElementById(listId + "-" + active.value);
    if (!row || !listbox.value) return;
    // Scroll only the popup; opening a select must not scroll the page or its modal.
    const start = row.offsetTop,
      end = start + row.offsetHeight;
    if (start < listbox.value.scrollTop) listbox.value.scrollTop = start;
    else if (end > listbox.value.scrollTop + listbox.value.clientHeight)
      listbox.value.scrollTop = end - listbox.value.clientHeight;
  });
}
function nativeChange(event) {
  const option = options.value[native.value.selectedIndex];
  if (!option || option.disabled) return;
  let value = option.value;
  if (props.modelModifiers.number) {
    const parsed = Number.parseFloat(value);
    if (!Number.isNaN(parsed)) value = parsed;
  }
  localValue.value = value;
  invalid.value = props.required && !native.value.validity.valid;
  emit("update:modelValue", value);
  emit("change", event);
}
function choose(index, focus = true) {
  if (props.disabled || !options.value[index] || options.value[index].disabled)
    return;
  native.value.selectedIndex = index;
  native.value.dispatchEvent(new Event("change", { bubbles: true }));
  close(focus);
}
function keydown(event) {
  if (props.disabled) return;
  const keys = [
    "ArrowDown",
    "ArrowUp",
    "Home",
    "End",
    "PageDown",
    "PageUp",
    "Enter",
    " ",
    "Escape",
  ];
  if (event.key === "Tab") {
    if (open.value) choose(active.value, false);
    return;
  }
  if (keys.includes(event.key)) {
    if (event.key === "Escape") {
      if (open.value) {
        event.preventDefault();
        event.stopPropagation();
        close(true);
      }
      return;
    }
    event.preventDefault();
    if (event.key === "Enter" || event.key === " ") {
      if (open.value) choose(active.value);
      else reveal();
      return;
    }
    const indices = enabledIndices.value;
    const index = indices.indexOf(active.value);
    const target =
      event.key === "Home"
        ? indices[0]
        : event.key === "End"
          ? indices.at(-1)
          : !open.value
            ? selected.value
            : indices[
                Math.max(
                  0,
                  Math.min(
                    indices.length - 1,
                    index +
                      ({ ArrowDown: 1, ArrowUp: -1, PageDown: 10, PageUp: -10 }[
                        event.key
                      ] || 0),
                  ),
                )
              ];
    if (!open.value) reveal(target);
    else {
      active.value = target;
      scrollActive();
    }
  } else if (
    event.key.length === 1 &&
    !event.ctrlKey &&
    !event.metaKey &&
    !event.altKey
  ) {
    event.preventDefault();
    clearTimeout(searchTimer);
    search += event.key.toLocaleLowerCase();
    const query = [...search].every((char) => char === search[0])
      ? search[0]
      : search;
    const start = Math.max(0, open.value ? active.value : selected.value);
    const indices = [
      ...enabledIndices.value.filter((index) => index > start),
      ...enabledIndices.value.filter((index) => index <= start),
    ];
    const target = indices.find((index) =>
      options.value[index].label.toLocaleLowerCase().startsWith(query),
    );
    if (target !== undefined) {
      if (open.value) {
        active.value = target;
        scrollActive();
      } else reveal(target);
    }
    searchTimer = setTimeout(() => {
      search = "";
    }, 700);
  }
}
function invalidField(event) {
  event.preventDefault();
  invalid.value = true;
  trigger.value?.focus();
  reveal();
}
watch(
  [currentValue, options],
  async () => {
    await nextTick();
    syncNative();
    if (native.value?.validity.valid) invalid.value = false;
    if (open.value) {
      if (!enabledIndices.value.length) close();
      else {
        if (!enabledIndices.value.includes(active.value))
          active.value =
            selected.value >= 0 ? selected.value : enabledIndices.value[0];
        updatePosition();
      }
    }
  },
  { immediate: true, flush: "post" },
);
watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) close();
  },
);
watch(open, async (value) => {
  cleanup();
  if (!value) return;
  await nextTick();
  if (!open.value) return;
  updatePosition();
  if (!open.value) return;
  if (window.ResizeObserver) {
    resizeObserver = new ResizeObserver(schedulePosition);
    resizeObserver.observe(trigger.value);
    resizeObserver.observe(listbox.value);
  }
  document.addEventListener("pointerdown", outside, true);
  document.addEventListener("focusin", outside, true);
  window.addEventListener("scroll", schedulePosition, true);
  window.addEventListener("resize", schedulePosition);
  window.visualViewport?.addEventListener("resize", schedulePosition);
  window.visualViewport?.addEventListener("scroll", schedulePosition);
});
onBeforeUnmount(() => {
  cleanup();
  clearTimeout(searchTimer);
});
</script>

<template>
  <span class="px-select" :class="attrs.class" :style="attrs.style">
    <button
      v-bind="buttonAttrs"
      :id="triggerId"
      ref="trigger"
      class="px-select-trigger"
      type="button"
      role="combobox"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-activedescendant="
        open && active >= 0 ? listId + '-' + active : undefined
      "
      :aria-required="required || undefined"
      :aria-invalid="invalid || undefined"
      :aria-describedby="invalid ? uid + '-error' : attrs['aria-describedby']"
      :disabled="disabled"
      @click="open ? close() : reveal()"
      @keydown="keydown"
    >
      <span class="px-select-label">{{ label }}</span>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        height="18"
        width="18"
        fill="#444"
        aria-hidden="true"
        focusable="false"
      >
        <path
          d="M12,22L6,16l1.43-1.43L12,19.15l4.57-4.57L18,16l-6,6ZM7.45,9.4L6,8l6-6l6,6L16.55,9.4L12,4.85L7.45,9.4Z"
        />
      </svg>
    </button>
    <!-- Native form participation preserves required validation and typed option values. -->
    <select
      ref="native"
      class="px-select-native"
      :id="uid + '-native'"
      :name="attrs.name"
      :form="attrs.form"
      :disabled="disabled"
      :required="required"
      tabindex="-1"
      aria-hidden="true"
      @change="nativeChange"
      @invalid="invalidField"
    >
      <option
        v-for="(option, index) in options"
        :key="index"
        :value="option.value"
        :disabled="option.disabled"
        :data-symbol="option.symbol"
      >
        {{ option.label }}
      </option>
    </select>
    <span
      v-if="invalid"
      :id="uid + '-error'"
      class="px-select-error"
      role="alert"
      >Please select an option.</span
    >
    <Teleport to="body">
      <div
        v-if="open"
        :id="listId"
        ref="listbox"
        class="px-select-options"
        :style="position"
        :data-placement="placement"
        role="listbox"
        :aria-labelledby="triggerId"
        @pointerdown.prevent
      >
        <div
          v-for="(option, index) in options"
          :id="listId + '-' + index"
          :key="index"
          role="option"
          :aria-selected="selected === index"
          :aria-disabled="option.disabled || undefined"
          class="px-select-option"
          :class="{
            'is-active': active === index,
            'is-selected': selected === index,
            'is-disabled': option.disabled,
          }"
          @pointermove="!option.disabled && (active = index)"
          @click.stop="choose(index)"
        >
          {{ option.label }}
        </div>
      </div>
    </Teleport>
  </span>
</template>

<style scoped>
.px-select {
  position: relative;
  display: inline-flex;
  flex-direction: column;
  min-width: 0;
  width: 100%;
  vertical-align: middle;
  font: inherit;
}
.px-select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  box-sizing: border-box;
  width: 100%;
  min-height: 40px;
  margin: 0;
  padding: 7px 10px;
  border: 1px solid var(--px-line, #dee2e6);
  border-radius: 6px;
  background: var(--px-card, #fff);
  color: var(--px-color, #1e1e1e);
  font: inherit;
  font-weight: 400;
  text-align: start;
  line-height: 1.5;
  cursor: pointer;
}
.px-select-trigger svg {
  flex-shrink: 0;
}
.px-select-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.px-select-trigger:hover:not(:disabled) {
  border-color: var(--px-primary, #0d6efd);
}
.px-select-trigger:focus-visible,
.px-select-trigger[aria-expanded="true"] {
  outline: 2px solid #0d6efd40;
  outline-offset: 1px;
  border-color: var(--px-primary, #0d6efd);
}
.px-select-trigger:disabled {
  cursor: not-allowed;
  opacity: 0.55;
  background: #f6f7f7;
}
.px-select-trigger[aria-invalid="true"] {
  border-color: #f70063;
}
.px-select-native {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
  clip-path: inset(50%);
  opacity: 0;
  pointer-events: none;
  overflow: hidden;
}
.px-select-options {
  position: fixed;
  z-index: 2147483500;
  box-sizing: border-box;
  max-height: 300px;
  overflow-y: auto;
  overflow-x: hidden;
  overscroll-behavior: contain;
  padding: 4px;
  border: 1px solid var(--px-line, #dee2e6);
  border-radius: 8px;
  background: var(--px-card, #fff);
  color: var(--px-color, #1e1e1e);
  box-shadow: 0 8px 28px #0f172a26;
  font-family: Inter, system-ui, sans-serif;
  line-height: 1.5;
}
.px-select-option {
  padding: 8px 10px;
  min-height: 36px;
  box-sizing: border-box;
  border-radius: 4px;
  cursor: pointer;
  overflow-wrap: anywhere;
  text-align: start;
  font-weight: 400;
}
.px-select-option.is-active {
  background: #f0f6ff;
  color: #0d6efd;
}
.px-select-option.is-selected {
  font-weight: 600;
}
.px-select-option.is-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.px-select-error {
  font-size: 12px;
  color: #f70063;
  margin-top: 4px;
}
</style>
