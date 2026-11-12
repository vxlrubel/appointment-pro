/** Page-lifetime cache. Never persists customer or payment data to browser storage. */
export class ReadCache {
  private values = new Map<string, { data: unknown; expires: number }>()
  private pending = new Map<string, Promise<unknown>>()
  private writes = 0
  generation = 0

  clear() {
    this.generation++
    this.values.clear()
    this.pending.clear()
  }

  beginMutation() {
    this.writes++
    this.clear()
    return () => {
      this.writes--
      this.clear()
    }
  }

  async read<T>(key: string, load: () => Promise<T>, ttl = 30_000): Promise<T> {
    if (this.writes) return load()
    const cached = this.values.get(key)
    if (cached && cached.expires > Date.now()) return structuredClone(cached.data) as T
    this.values.delete(key)
    let promise = this.pending.get(key)
    if (!promise) {
      const generation = this.generation
      promise = load()
        .then((data) => {
          if (generation === this.generation && !this.writes) {
            if (this.values.size >= 100) this.values.delete(this.values.keys().next().value!)
            this.values.set(key, { data: structuredClone(data), expires: Date.now() + ttl })
          }
          return data
        })
        .finally(() => {
          if (this.pending.get(key) === promise) this.pending.delete(key)
        })
      this.pending.set(key, promise)
    }
    return structuredClone(await promise) as T
  }
}
