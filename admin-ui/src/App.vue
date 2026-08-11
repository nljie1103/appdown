<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard, Smartphone, Paperclip, WandSparkles, KeyRound, FileKey2,
  Palette, PanelsTopLeft, Images, Type, Settings, Code2, ArchiveRestore, ServerCog,
  RefreshCcw, UserRoundCog, ExternalLink, Bell, Menu, Sun, Moon, Monitor,
  ChevronRight, LogOut, CircleHelp, X
} from '@lucide/vue'
import { useAppStore } from './stores/app'

const store = useAppStore()
const route = useRoute()
const router = useRouter()
const themeMenu = ref(false)
const media = window.matchMedia('(prefers-color-scheme: dark)')
const systemDark = ref(media.matches)

type MenuItem = { to: string; label: string; icon: any; capability?: string }
const groups: Array<{ title: string; items: MenuItem[] }> = [
  { title: '概览', items: [{ to: '/dashboard', label: '仪表盘', icon: LayoutDashboard }] },
  { title: '分发', items: [
    { to: '/apps', label: '应用管理', icon: Smartphone },
    { to: '/attachments', label: '附件管理', icon: Paperclip },
    { to: '/mobileconfig', label: 'Mobileconfig', icon: FileKey2, capability: 'mobileconfig' },
  ]},
  { title: '构建', items: [
    { to: '/builder', label: '生成应用', icon: WandSparkles },
    { to: '/signing', label: '签名密钥', icon: KeyRound, capability: 'keystores' },
  ]},
  { title: '外观', items: [
    { to: '/templates', label: '页面模板', icon: Palette },
    { to: '/content', label: '内容组件', icon: PanelsTopLeft },
    { to: '/media', label: '媒体库', icon: Images },
    { to: '/fonts', label: '字体管理', icon: Type, capability: 'fonts' },
  ]},
  { title: '设置', items: [
    { to: '/settings', label: '站点设置', icon: Settings },
    { to: '/custom-code', label: '自定义代码', icon: Code2 },
  ]},
  { title: '系统', items: [
    { to: '/backup', label: '导入导出', icon: ArchiveRestore },
    { to: '/system', label: '系统信息', icon: ServerCog },
    { to: '/update', label: '在线升级', icon: RefreshCcw, capability: 'platform_update' },
    { to: '/account', label: '账户管理', icon: UserRoundCog },
  ]},
]

const actualTheme = computed(() => store.theme === 'system' ? (systemDark.value ? 'dark' : 'light') : store.theme)
const pageTitle = computed(() => String(route.meta.title || 'AppDown'))
const publicUrl = computed(() => store.publicPath)

function onMedia(e: MediaQueryListEvent) { systemDark.value = e.matches }
function navigate(to: string) { router.push(to); store.sidebarOpen = false }
function themeName() { return store.theme === 'light' ? '浅色' : store.theme === 'dark' ? '深色' : '跟随系统' }
function allowed(item: MenuItem) { return !item.capability || !!store.boot?.capabilities?.[item.capability] }

onMounted(async () => {
  media.addEventListener?.('change', onMedia)
  try { await store.bootstrap() } catch { /* handled in store */ }
})
onBeforeUnmount(() => media.removeEventListener?.('change', onMedia))
watch(pageTitle, (title) => { document.title = `${title} · AppDown Admin` }, { immediate: true })
</script>

<template>
  <div class="admin-app" :data-theme="actualTheme">
    <div v-if="store.booting" class="boot-screen">
      <div class="brand-mark">A</div>
      <div class="boot-copy"><b>AppDown Admin 2.0</b><span>正在初始化管理后台…</span></div>
    </div>

    <div v-else-if="store.bootError" class="boot-screen error-screen">
      <div class="brand-mark">!</div>
      <div class="boot-copy"><b>后台初始化失败</b><span>{{ store.bootError }}</span></div>
      <button class="button" @click="store.bootstrap()">重新加载</button>
    </div>

    <template v-else>
      <aside class="sidebar" :class="{ open: store.sidebarOpen }">
        <div class="sidebar-brand">
          <div class="brand-line">
            <div class="brand-mark">A</div>
            <div><b>AppDown</b><small>Admin 2.0</small></div>
          </div>
          <a class="workspace-card" :href="publicUrl" target="_blank" rel="noopener">
            <div class="workspace-avatar">{{ store.tenantLabel.slice(0, 1).toUpperCase() }}</div>
            <div class="workspace-copy">
              <b>{{ store.tenantLabel }}</b>
              <span>{{ store.boot?.tenant?.slug || store.editionLabel }}</span>
            </div>
            <ChevronRight :size="15" />
          </a>
        </div>

        <nav class="sidebar-nav">
          <div v-for="group in groups" :key="group.title" class="nav-group">
            <div class="nav-title">{{ group.title }}</div>
            <button
              v-for="item in group.items.filter(allowed)" :key="item.to"
              class="nav-item" :class="{ active: route.path === item.to }"
              @click="navigate(item.to)"
            >
              <component :is="item.icon" :size="17" />
              <span>{{ item.label }}</span>
            </button>
          </div>
        </nav>

        <div class="sidebar-footer">
          <div class="theme-wrap">
            <button class="footer-row" @click.stop="themeMenu = !themeMenu">
              <Sun v-if="store.theme === 'light'" :size="16" />
              <Moon v-else-if="store.theme === 'dark'" :size="16" />
              <Monitor v-else :size="16" />
              <span>主题</span><small>{{ themeName() }}</small>
            </button>
            <div v-if="themeMenu" class="theme-menu">
              <button :class="{ active: store.theme === 'light' }" @click="store.setTheme('light');themeMenu=false"><Sun :size="15"/>浅色</button>
              <button :class="{ active: store.theme === 'dark' }" @click="store.setTheme('dark');themeMenu=false"><Moon :size="15"/>深色</button>
              <button :class="{ active: store.theme === 'system' }" @click="store.setTheme('system');themeMenu=false"><Monitor :size="15"/>跟随系统</button>
            </div>
          </div>
          <div class="user-row">
            <div class="user-avatar">{{ store.userName.slice(0, 1).toUpperCase() }}</div>
            <div class="user-copy"><b>{{ store.userName }}</b><span>{{ store.editionLabel }} · {{ store.boot?.version }}</span></div>
            <a href="/admin/logout.php" class="icon-quiet" title="退出"><LogOut :size="15"/></a>
          </div>
        </div>
      </aside>

      <div v-if="store.sidebarOpen" class="sidebar-overlay" @click="store.sidebarOpen=false"></div>

      <main class="main-shell">
        <header class="topbar">
          <div class="top-left">
            <button class="icon-button mobile-menu" @click="store.sidebarOpen=true"><Menu :size="17"/></button>
            <div class="breadcrumb"><span>AppDown</span><i>/</i><b>{{ pageTitle }}</b></div>
          </div>
          <div class="top-actions">
            <a class="icon-button" :href="publicUrl" target="_blank" rel="noopener" title="打开分发页"><ExternalLink :size="16"/></a>
            <button class="icon-button" title="通知"><Bell :size="16"/></button>
            <a class="icon-button" href="https://github.com/nljie1103/appdown" target="_blank" rel="noopener" title="项目帮助"><CircleHelp :size="16"/></a>
          </div>
        </header>

        <div class="page-container"><RouterView /></div>
      </main>

      <Transition name="toast">
        <div v-if="store.toast" class="toast" :class="store.toast.tone">
          <span>{{ store.toast.message }}</span>
          <button @click="store.toast=null"><X :size="14"/></button>
        </div>
      </Transition>
    </template>
  </div>
</template>
