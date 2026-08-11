export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE'

let csrfToken = ''

export function setCsrfToken(token: string) {
  csrfToken = token || ''
}

export class ApiError extends Error {
  status: number
  code?: string
  constructor(message: string, status = 0, code?: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

async function parseResponse(res: Response): Promise<any> {
  const type = res.headers.get('content-type') || ''
  if (type.includes('application/json')) {
    const data = await res.json().catch(() => null)
    if (!res.ok) {
      const raw = data?.error
      const message = typeof raw === 'string' ? raw : (raw?.message || `请求失败 (${res.status})`)
      const code = typeof raw === 'object' ? raw?.code : undefined
      throw new ApiError(message, res.status, code)
    }
    return data
  }
  if (!res.ok) throw new ApiError(`请求失败 (${res.status})`, res.status)
  return res
}

export async function api<T = any>(url: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers || {})
  headers.set('X-Requested-With', 'XMLHttpRequest')
  const method = String(options.method || 'GET').toUpperCase()
  if (method !== 'GET' && csrfToken) headers.set('X-CSRF-Token', csrfToken)
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  const res = await fetch(url, { credentials: 'same-origin', ...options, headers })
  if (res.status === 401) {
    location.href = '/admin/login.php'
    throw new ApiError('登录已失效', 401)
  }
  return parseResponse(res) as Promise<T>
}

export const get = <T = any>(url: string) => api<T>(url)
export const post = <T = any>(url: string, data?: unknown) => api<T>(url, { method: 'POST', body: data instanceof FormData ? data : JSON.stringify(data ?? {}) })
export const put = <T = any>(url: string, data?: unknown) => api<T>(url, { method: 'PUT', body: JSON.stringify(data ?? {}) })
export const del = <T = any>(url: string, data?: unknown) => api<T>(url, { method: 'DELETE', body: JSON.stringify(data ?? {}) })

export async function downloadPost(url: string, data: unknown): Promise<{ blob: Blob; filename: string }> {
  const headers = new Headers({ 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' })
  if (csrfToken) headers.set('X-CSRF-Token', csrfToken)
  const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(data) })
  if (!res.ok) {
    const parsed = await res.json().catch(() => ({}))
    throw new ApiError(parsed?.error || `导出失败 (${res.status})`, res.status)
  }
  const disposition = res.headers.get('content-disposition') || ''
  const headerName = res.headers.get('x-filename') || ''
  const match = disposition.match(/filename="?([^";]+)"?/i)
  return { blob: await res.blob(), filename: headerName || match?.[1] || 'appdown-backup.bin' }
}
