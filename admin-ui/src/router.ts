import { createRouter, createWebHashHistory } from 'vue-router'

const DashboardView = () => import('./views/DashboardView.vue')
const AppsView = () => import('./views/AppsView.vue')
const AttachmentsView = () => import('./views/AttachmentsView.vue')
const BuilderView = () => import('./views/BuilderView.vue')
const SigningView = () => import('./views/SigningView.vue')
const MobileconfigView = () => import('./views/MobileconfigView.vue')
const TemplatesView = () => import('./views/TemplatesView.vue')
const ContentView = () => import('./views/ContentView.vue')
const MediaView = () => import('./views/MediaView.vue')
const FontsView = () => import('./views/FontsView.vue')
const SettingsView = () => import('./views/SettingsView.vue')
const CustomCodeView = () => import('./views/CustomCodeView.vue')
const BackupView = () => import('./views/BackupView.vue')
const SystemView = () => import('./views/SystemView.vue')
const UpdateView = () => import('./views/UpdateView.vue')
const AccountView = () => import('./views/AccountView.vue')

export const router = createRouter({
  history: createWebHashHistory('/admin/app.php'),
  routes: [
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { title: '仪表盘' } },
    { path: '/apps', name: 'apps', component: AppsView, meta: { title: '应用管理' } },
    { path: '/attachments', name: 'attachments', component: AttachmentsView, meta: { title: '附件管理' } },
    { path: '/builder', name: 'builder', component: BuilderView, meta: { title: '生成应用' } },
    { path: '/signing', name: 'signing', component: SigningView, meta: { title: '签名密钥' } },
    { path: '/mobileconfig', name: 'mobileconfig', component: MobileconfigView, meta: { title: 'Mobileconfig' } },
    { path: '/templates', name: 'templates', component: TemplatesView, meta: { title: '页面模板' } },
    { path: '/content', name: 'content', component: ContentView, meta: { title: '内容组件' } },
    { path: '/media', name: 'media', component: MediaView, meta: { title: '媒体库' } },
    { path: '/fonts', name: 'fonts', component: FontsView, meta: { title: '字体管理' } },
    { path: '/settings', name: 'settings', component: SettingsView, meta: { title: '站点设置' } },
    { path: '/custom-code', name: 'custom-code', component: CustomCodeView, meta: { title: '自定义代码' } },
    { path: '/backup', name: 'backup', component: BackupView, meta: { title: '导入导出' } },
    { path: '/system', name: 'system', component: SystemView, meta: { title: '系统信息' } },
    { path: '/update', name: 'update', component: UpdateView, meta: { title: '在线升级', capability: 'platform_update' } },
    { path: '/account', name: 'account', component: AccountView, meta: { title: '账户管理' } },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ],
  scrollBehavior: () => ({ top: 0 })
})
