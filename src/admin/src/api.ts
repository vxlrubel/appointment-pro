import { toast } from '../../shared/toast'
import {
  adminReadCache,
  adminCacheState,
  configureAdminCache,
  adminCacheTTL,
  broadcastCacheInvalidation,
} from './cache'
import type { CacheSettings } from './cache'

export interface ApiConfig {
  root: string
  nonce: string
  cache?: CacheSettings
}

export interface ApiOptions {
  method?: string
  body?: unknown
  headers?: Record<string, string>
  toast?: false | { working?: string; success?: string }
  cache?: boolean
}

function config(): ApiConfig {
  const runtime = window as unknown as { pxAppointmentAdmin?: Partial<ApiConfig> }

  if (runtime.pxAppointmentAdmin?.root) {
    return {
      root: runtime.pxAppointmentAdmin.root,
      nonce: runtime.pxAppointmentAdmin.nonce || '',
    }
  }

  return { root: '/wp-json/px-appointment/v1/', nonce: '' }
}

async function request<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
  const cfg = config()
  const generation = adminReadCache.generation
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  }

  if (cfg.nonce) {
    headers['X-WP-Nonce'] = cfg.nonce
  }

  const response = await fetch(cfg.root.replace(/\/?$/, '/') + path.replace(/^\//, ''), {
    ...options,
    cache: 'no-store',
    headers,
    body:
      options.body && typeof options.body !== 'string'
        ? JSON.stringify(options.body)
        : (options.body as string | undefined),
  })
  const version = response.headers.get('X-PX-Cache-Version')
  if (version && generation === adminReadCache.generation && version !== adminCacheState.version) {
    configureAdminCache({ enabled: adminCacheState.enabled, version })
  }

  const text = await response.text()
  let data: any = null

  try {
    data = text ? JSON.parse(text) : null
  } catch {
    data = { message: text }
  }

  if (!response.ok) {
    if ([401, 403].includes(response.status)) adminReadCache.clear()
    const message = data?.message || data?.data?.message || data?.code || response.statusText
    const error = new Error(typeof message === 'string' ? message : response.statusText)
    ;(error as any).status = response.status
    ;(error as any).data = data
    throw error
  }

  return data as T
}

/** Mutations report their full lifecycle; background reads only report failures. */
export async function api<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
  const { toast: feedback, cache: useCache, ...requestOptions } = options
  const method = (options.method || 'GET').toUpperCase()
  const cfg = config()
  const perform = async () => {
    if (['GET', 'HEAD'].includes(method)) {
      const ttl =
        method === 'GET' && useCache !== false && adminCacheState.enabled ? adminCacheTTL(path) : 0
      if (!ttl) return request<T>(path, requestOptions)
      const headers = Object.entries(options.headers || {}).sort(([a], [b]) => a.localeCompare(b))
      const key = JSON.stringify([cfg.root, cfg.nonce, path.replace(/^\//, ''), headers])
      return adminReadCache.read(key, () => request<T>(path, requestOptions), ttl)
    }
    const finish = adminReadCache.beginMutation()
    broadcastCacheInvalidation(cfg.root)
    try {
      return await request<T>(path, requestOptions)
    } finally {
      finish()
      broadcastCacheInvalidation(cfg.root)
    }
  }
  if (feedback === false) return perform()
  if (!['GET', 'HEAD'].includes(method)) {
    return toast.promise(perform, {
      working: feedback?.working || (method === 'DELETE' ? 'Deleting…' : 'Saving changes…'),
      success:
        feedback?.success ||
        (method === 'DELETE' ? 'Deleted successfully.' : 'Changes saved successfully.'),
    })
  }
  try {
    return await perform()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Unable to load data. Please try again.')
    throw error
  }
}

export async function refreshAdminCacheSettings() {
  const generation = adminReadCache.generation
  const settings = await api<CacheSettings>('admin/cache', { cache: false, toast: false })
  if (typeof settings?.enabled !== 'boolean' || typeof settings?.version !== 'string') {
    throw new Error('Unable to read admin cache settings.')
  }
  if (generation === adminReadCache.generation || settings.version === adminCacheState.version) {
    configureAdminCache(settings)
  }
  return settings
}

export function startAdminCacheSync() {
  const runtime = window as unknown as { pxAppointmentAdmin?: ApiConfig }
  if (runtime.pxAppointmentAdmin?.cache) configureAdminCache(runtime.pxAppointmentAdmin.cache)
  let syncing = false
  let resync = false
  let stopped = false
  const sync = async () => {
    if (stopped || document.hidden) return
    if (syncing) {
      resync = true
      return
    }
    syncing = true
    try {
      await refreshAdminCacheSettings()
    } catch {
      adminReadCache.clear()
    } finally {
      syncing = false
      if (resync) {
        resync = false
        void sync()
      }
    }
  }
  const focus = () => {
    adminReadCache.clear()
    void sync()
  }
  const storage = (event: StorageEvent) => {
    if (event.key === 'px-appointment-cache:' + config().root) focus()
  }
  window.addEventListener('focus', focus)
  window.addEventListener('storage', storage)
  document.addEventListener('visibilitychange', focus)
  const timer = window.setInterval(() => {
    void sync()
  }, 15_000)
  void sync()
  return () => {
    stopped = true
    window.clearInterval(timer)
    window.removeEventListener('focus', focus)
    window.removeEventListener('storage', storage)
    document.removeEventListener('visibilitychange', focus)
  }
}
