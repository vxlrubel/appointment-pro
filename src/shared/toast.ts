import { readonly, reactive } from "vue";

export type ToastType = "success" | "error" | "working" | "info";
export interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
  duration: number;
}

const messages = reactive<ToastMessage[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let sequence = 0;

function stopTimer(id: number) {
  clearTimeout(timers.get(id));
  timers.delete(id);
}
function dismiss(id: number) {
  stopTimer(id);
  const index = messages.findIndex((item) => item.id === id);
  if (index >= 0) messages.splice(index, 1);
}
function resume(id: number) {
  stopTimer(id);
  const item = messages.find((item) => item.id === id);
  if (item && item.duration > 0) {
    timers.set(
      id,
      setTimeout(() => dismiss(id), item.duration),
    );
  }
}
function durationFor(type: ToastType) {
  return type === "working" ? 0 : type === "error" ? 8000 : 5000;
}
function show(
  message: string,
  type: ToastType = "info",
  duration = durationFor(type),
  deduplicate = true,
) {
  const existing =
    deduplicate &&
    messages.find((item) => item.type === type && item.message === message);
  if (existing) {
    resume(existing.id);
    return existing.id;
  }
  if (messages.length >= 5)
    dismiss(
      messages.find((item) => item.type !== "working")?.id ?? messages[0]!.id,
    );
  const id = ++sequence;
  messages.push({ id, type, message, duration });
  resume(id);
  return id;
}
function update(id: number, message: string, type: ToastType = "success") {
  const item = messages.find((item) => item.id === id);
  if (!item) return show(message, type);
  Object.assign(item, { message, type, duration: durationFor(type) });
  resume(id);
  return id;
}

/** Import toast anywhere; mount one PxToast host per page. */
export const toast = {
  messages: readonly(messages),
  show,
  success: (message: string) => show(message, "success"),
  error: (message: string) => show(message, "error"),
  working: (message = "Working…") => show(message, "working", 0, false),
  info: (message: string) => show(message, "info"),
  update,
  dismiss,
  pause: stopTimer,
  resume,
  clear: () => [...messages].forEach((item) => dismiss(item.id)),
  async promise<T>(
    operation: () => Promise<T>,
    options: { working: string; success: string },
  ) {
    // Each operation owns its progress toast, even when requests run concurrently.
    const id = show(options.working, "working", 0, false);
    try {
      const result = await operation();
      update(id, options.success, "success");
      return result;
    } catch (error) {
      update(
        id,
        error instanceof Error
          ? error.message
          : "Something went wrong. Please try again.",
        "error",
      );
      throw error;
    }
  },
};

export function useToast() {
  return toast;
}
