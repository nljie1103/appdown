import { defineStore } from 'pinia'
import { get, setCsrfToken } from '../api'

export interface BootstrapData {
  ok: boolean
  csrf: string
  edition: 'main' | 'saas' | string
  version: string
  user: { id: number; name: string }
  site: { title: string }
  tenant: null | { slug: string; display_name: string; public_path: string }
  capabilities: Record<string, boolean>
}

export type ThemeMode = 'light' | 'dark' | 'system'

export const useAppStore = defineStore('appdown', {
  state: () => ({
    boot: null as BootstrapData | null,
    booting: false,
    bootError: '',
    theme: (localStorage.getItem('appdown-admin-theme') || 'system') as ThemeMode,
    sidebarOpen: false,
    toast: null as null | { message: string; tone: 'success' | 'error' | 'info' },
  }),
  getters: {
    siteTitle: (s) => s.boot?.site.title || 'AppDown',
    userName: (s) => s.boot?.user.name || 'Admin',
    tenantLabel: (s) => s.boot?.tenant?.display_name || s.boot?.site.title || 'AppDown',
    publicPath: (s) => s.boot?.tenant?.public_path || '/',
    editionLabel: (s) => s.boot?.edition === 'saas' ? 'SaaS Tenant' : 'Single Site',
  },
  actions: {
    async bootstrap() {
      this.booting = true
      this.bootError = ''
      try {
        const data = await get<BootstrapData>('/admin/api/bootstrap.php')
        this.boot = data
        setCsrfToken(data.csrf)
      } catch (e: any) {
        this.bootError = e?.message || '后台初始化失败'
        throw e
      } finally {
        this.booting = false
      }
    },
    setTheme(mode: ThemeMode) {
      this.theme = mode
      localStorage.setItem('appdown-admin-theme', mode)
    },
    notify(message: string, tone: 'success' | 'error' | 'info' = 'success') {
      this.toast = { message, tone }
      window.setTimeout(() => {
        if (this.toast?.message === message) this.toast = null
      }, 2400)
    }
  }
})
