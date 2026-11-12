// Run with Node 24+: node --test tests/admin-cache.test.mjs
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ReadCache } from '../src/admin/src/utils/readCache.ts'

function deferred() {
  let resolve
  const promise = new Promise((done) => { resolve = done })
  return { promise, resolve }
}

test('cache hits avoid fetching and isolate editable response objects', async () => {
  const cache = new ReadCache()
  let calls = 0
  const load = async () => { calls++; return [{ name: 'Original', nested: { value: 1 } }] }
  const first = await cache.read('services', load)
  first[0].name = 'Unsaved edit'
  first[0].nested.value = 99
  assert.deepEqual(await cache.read('services', load), [{ name: 'Original', nested: { value: 1 } }])
  assert.equal(calls, 1)
  await cache.read('other-user/services', load)
  assert.equal(calls, 2)
})

test('simultaneous reads share one request and receive separate objects', async () => {
  const cache = new ReadCache()
  const pending = deferred()
  let calls = 0
  const load = () => { calls++; return pending.promise }
  const first = cache.read('services', load)
  const second = cache.read('services', load)
  pending.resolve([{ id: 1 }])
  const [a, b] = await Promise.all([first, second])
  assert.equal(calls, 1)
  assert.deepEqual(a, b)
  assert.notEqual(a, b)
})

test('expiry and purge fetch fresh data', async () => {
  const cache = new ReadCache()
  let calls = 0
  const load = async () => ++calls
  await cache.read('services', load, -1)
  assert.equal(await cache.read('services', load), 2)
  cache.clear()
  assert.equal(await cache.read('services', load), 3)
})

test('an old request cannot repopulate a purged cache or remove a newer pending request', async () => {
  const cache = new ReadCache()
  const old = deferred(), fresh = deferred()
  const first = cache.read('services', () => old.promise)
  cache.clear()
  let calls = 0
  const load = () => { calls++; return fresh.promise }
  const second = cache.read('services', load)
  old.resolve('old')
  await first
  const third = cache.read('services', load)
  fresh.resolve('fresh')
  assert.deepEqual(await Promise.all([second, third]), ['fresh', 'fresh'])
  assert.equal(calls, 1)
  assert.equal(await cache.read('services', async () => 'wrong'), 'fresh')
})

test('overlapping mutations bypass caching and invalidate reads on completion', async () => {
  const cache = new ReadCache()
  await cache.read('services', async () => 'before')
  const finishFirst = cache.beginMutation(), finishSecond = cache.beginMutation()
  let calls = 0
  const load = async () => ++calls
  await cache.read('services', load)
  finishFirst()
  await cache.read('services', load)
  await cache.read('services', load)
  assert.equal(calls, 3)
  finishSecond()
  assert.equal(await cache.read('services', load), 4)
  assert.equal(await cache.read('services', load), 4)
})

test('failed requests are retried rather than cached', async () => {
  const cache = new ReadCache()
  await assert.rejects(cache.read('services', async () => { throw Error('offline') }), /offline/)
  assert.equal(await cache.read('services', async () => 'recovered'), 'recovered')
})

test('the cache has a bounded capacity', async () => {
  const cache = new ReadCache()
  for (let i = 0; i < 101; i++) await cache.read(String(i), async () => i)
  assert.equal(await cache.read('0', async () => 'evicted'), 'evicted')
})
