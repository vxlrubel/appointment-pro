import { onScopeDispose, watch } from "vue";
import type { WatchSource } from "vue";
import { toast } from "./toast";

/** Keep field/page errors in place while also reporting them in the toast host. */
export function useToastFeedback(error: WatchSource<string>) {
  watch(
    error,
    (message) => {
      if (message) toast.error(message);
    },
    { flush: "sync" },
  );
}

export function useWorkingToast(source: WatchSource<string>) {
  let id: number | undefined;
  watch(
    source,
    (message) => {
      if (message)
        id = id ? toast.update(id, message, "working") : toast.working(message);
      else if (id) {
        toast.dismiss(id);
        id = undefined;
      }
    },
    { immediate: true },
  );
  onScopeDispose(() => {
    if (id) toast.dismiss(id);
  });
}
