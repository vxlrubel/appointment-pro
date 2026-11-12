function config() {
  if (typeof window !== "undefined" && window.pxAppointmentAdmin) {
    return window.pxAppointmentAdmin;
  }
  if (typeof window !== "undefined" && window.pxAppointmentPublic) {
    return window.pxAppointmentPublic;
  }
  return { root: "/wp-json/px-appointment/v1/", nonce: "" };
}

export async function api(path, options = {}) {
  const cfg = config();
  const headers = Object.assign(
    { "Content-Type": "application/json" },
    options.headers || {},
  );
  if (cfg.nonce) {
    headers["X-WP-Nonce"] = cfg.nonce;
  }
  const res = await fetch(
    cfg.root.replace(/\/?$/, "/") + path.replace(/^\//, ""),
    {
      ...options,
      cache: "no-store",
      headers,
      body:
        options.body && typeof options.body !== "string"
          ? JSON.stringify(options.body)
          : options.body,
    },
  );
  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = { message: text };
  }
  if (!res.ok) {
    const msg =
      data?.message || data?.data?.message || data?.code || res.statusText;
    const err = new Error(typeof msg === "string" ? msg : res.statusText);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}
