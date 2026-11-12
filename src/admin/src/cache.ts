import { reactive, readonly } from 'vue'
import { ReadCache } from './utils/readCache'

export interface CacheSettings {
  enabled: boolean
  version: string
}

export const adminReadCache = new ReadCache()
const state = reactive<CacheSettings>({ enabled: false, version: '0' })
export const adminCacheState = readonly(state)

export function configureAdminCache(settings: CacheSettings) {
  if (state.enabled !== settings.enabled || state.version !== settings.version)
    adminReadCache.clear()
  Object.assign(state, settings)
}

/** Availability, payment credentials and token-based booking details always stay live. */
export function adminCacheTTL(path: string): number {
  const route = path.replace(/^\//, '').split('?')[0] || ''
  if (
    /^admin\/(locations|services|categories|staff|extras|coupons|customers|custom-fields|notifications|holidays|business-hours|affiliates)(\/\d+(\/bookings)?)?$/.test(
      route,
    ) ||
    /^staff\/\d+\/(schedule|days-off|services)$/.test(route)
  )
    return 30_000
  if (['appointments', 'payments', 'dashboard'].includes(route)) return 5000
  return 0
}

/** Store only an invalidation signal, never cached records, across tabs. */
export function broadcastCacheInvalidation(root: string) {
  try {
    localStorage.setItem(
      'px-appointment-cache:' + root,
      JSON.stringify({ id: Math.random(), time: Date.now() }),
    )
  } catch {
    /* Memory caching works when browser storage is unavailable. */
  }
}
