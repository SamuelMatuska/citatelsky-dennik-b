// Tenký obal nad fetch: JSON dnu aj von, chyby ako výnimka so správou a chybami polí.
export async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch(`/api${path}`, {
    method,
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  })
  if (res.status === 204) return null
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const error = new Error(data.message || 'Niečo sa pokazilo. Skúste to znova.')
    error.status = res.status
    error.errors = data.errors || {}
    throw error
  }
  return data
}
