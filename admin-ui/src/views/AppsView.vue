<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Search, RefreshCw, Trash2, Upload, X, ExternalLink } from 'lucide-vue-next'
import { del, get, post, put } from '../api'
import { useAppStore } from '../stores/app'

type AppRow = Record<string, any>
const store = useAppStore()
const apps = ref<AppRow[]>([])
const loading = ref(false)
const search = ref('')
const drawer = ref(false)
const drawerTab = ref<'basic'|'ios'|'android'|'downloads'|'images'>('basic')
const saving = ref(false)
const editing = reactive<AppRow>({})
const downloads = ref<AppRow[]>([])
const images = ref<AppRow[]>([])
const newDownload = reactive({ btn_type:'android', btn_icon:'', btn_text:'Android', btn_subtext:'下载安装', href:'#' })
const newImage = reactive({ image_url:'', alt_text:'' })

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return apps.value.filter(a => !q || `${a.name} ${a.slug} ${a.ios_bundle_id||''}`.toLowerCase().includes(q))
})

async function load() {
  loading.value = true
  try { apps.value = await get('/admin/api/apps.php') }
  catch (e:any) { store.notify(e?.message || '应用列表加载失败','error') }
  finally { loading.value = false }
}
async function openApp(app?: AppRow) {
  drawerTab.value='basic'
  Object.keys(editing).forEach(k => delete editing[k])
  if (app?.id) {
    try {
      const detail = await get<AppRow>(`/admin/api/apps.php?id=${encodeURIComponent(app.id)}`)
      Object.assign(editing, detail)
      downloads.value = detail.downloads || []
      images.value = detail.images || []
    } catch(e:any){ store.notify(e?.message||'应用详情加载失败','error'); return }
  } else {
    Object.assign(editing,{id:0,slug:'',name:'',icon:'fas fa-mobile-alt',icon_url:'',theme_color:'#4f46e5',is_active:1,ios_version:'',ios_ipa_url:'',ios_plist_url:'',ios_bundle_id:'',ios_description:'',android_version:'',android_apk_url:'',android_description:'',android_template:'default',ios_template:'default'})
    downloads.value=[]; images.value=[]
  }
  drawer.value=true
}
async function saveApp() {
  if (!editing.name?.trim()) return store.notify('应用名称不能为空','error')
  saving.value=true
  try {
    if (!editing.id) {
      if (!editing.slug?.trim()) return store.notify('新应用必须填写标识 slug','error')
      const res:any = await post('/admin/api/apps.php',{slug:editing.slug,name:editing.name,icon:editing.icon,icon_url:editing.icon_url,theme_color:editing.theme_color})
      editing.id=res.id
    } else {
      const payload:any={id:editing.id}
      ;['name','icon','icon_url','theme_color','ios_plist_url','ios_ipa_url','ios_bundle_id','ios_description','ios_version','ios_size','ios_template','android_template','android_apk_url','android_version','android_size','android_description'].forEach(k=>payload[k]=editing[k]??'')
      payload.is_active=!!Number(editing.is_active)
      await put('/admin/api/apps.php',payload)
    }
    store.notify('应用已保存')
    await load()
    drawer.value=false
  } catch(e:any){store.notify(e?.message||'保存失败','error')}
  finally{saving.value=false}
}
async function removeApp() {
  if (!editing.id || !confirm(`确定永久删除「${editing.name}」及其关联文件吗？`)) return
  try { await del('/admin/api/apps.php',{id:editing.id}); store.notify('应用已删除'); drawer.value=false; await load() }
  catch(e:any){store.notify(e?.message||'删除失败','error')}
}
async function addDownload(){
  if(!editing.id)return store.notify('请先保存应用','error')
  try{await post('/admin/api/downloads.php',{app_id:editing.id,...newDownload});downloads.value=await get(`/admin/api/downloads.php?app_id=${editing.id}`);store.notify('下载方式已添加')}
  catch(e:any){store.notify(e?.message||'添加失败','error')}
}
async function saveDownload(d:any){try{await put('/admin/api/downloads.php',d);store.notify('下载方式已保存')}catch(e:any){store.notify(e?.message||'保存失败','error')}}
async function removeDownload(d:any){if(!confirm('删除这个下载方式？'))return;try{await del('/admin/api/downloads.php',{id:d.id});downloads.value=downloads.value.filter(x=>x.id!==d.id);store.notify('已删除')}catch(e:any){store.notify(e?.message||'删除失败','error')}}
async function uploadImage(file:File){
  const fd=new FormData();fd.append('category','image');fd.append('file',file)
  try{const res:any=await post('/admin/api/upload.php',fd);if(!res.ok)throw new Error(res.error||'上传失败');newImage.image_url=res.url||'';store.notify('图片已上传，请点击添加截图')}
  catch(e:any){store.notify(e?.message||'上传失败','error')}
}
async function addImage(){if(!editing.id||!newImage.image_url)return store.notify('请先选择图片','error');try{await post('/admin/api/images.php',{app_id:editing.id,...newImage});images.value=await get(`/admin/api/images.php?app_id=${editing.id}`);newImage.image_url='';newImage.alt_text='';store.notify('截图已添加')}catch(e:any){store.notify(e?.message||'添加失败','error')}}
async function removeImage(img:any){if(!confirm('删除这张截图？'))return;try{await del('/admin/api/images.php',{id:img.id});images.value=images.value.filter(x=>x.id!==img.id);store.notify('截图已删除')}catch(e:any){store.notify(e?.message||'删除失败','error')}}
onMounted(load)
</script>

<template>
<div>
  <div class="page-head"><div><h1>应用管理</h1><p>管理应用、安装包、版本、下载方式与公开分发状态。</p></div><div class="page-actions"><button class="button" @click="load"><RefreshCw :size="14"/>刷新</button><button class="button primary" @click="openApp()"><Plus :size="14"/>添加应用</button></div></div>
  <div class="toolbar"><div class="search-box"><Search :size="15"/><input v-model="search" class="input" placeholder="搜索应用名称、slug 或 Bundle ID…"></div><span class="subtle">{{ filtered.length }} 个应用</span></div>
  <div v-if="loading" class="app-list"><div v-for="i in 3" :key="i" class="app-row"><div class="skeleton" style="height:46px;width:220px"></div><div class="skeleton" style="height:36px"></div><div class="skeleton" style="height:30px;width:80px"></div></div></div>
  <div v-else-if="filtered.length" class="app-list">
    <div v-for="a in filtered" :key="a.id" class="app-row">
      <div class="app-main"><div class="app-icon"><img v-if="a.icon_url" :src="a.icon_url" alt=""><span v-else>{{ String(a.name||'A').slice(0,1) }}</span></div><div class="app-copy"><b>{{ a.name }}</b><code>{{ a.slug }}</code></div></div>
      <div class="app-info"><div class="info"><span>iOS</span><b>{{ a.ios_version || '—' }}</b></div><div class="info"><span>Android</span><b>{{ a.android_version || '—' }}</b></div><div class="info"><span>内容</span><b>{{ a.dl_count || 0 }} 下载 · {{ a.img_count || 0 }} 图</b></div></div>
      <div class="row-actions"><span class="badge" :class="{muted:!Number(a.is_active)}">{{ Number(a.is_active)?'已发布':'已隐藏' }}</span><button class="button small" @click="openApp(a)">编辑</button></div>
    </div>
  </div>
  <div v-else class="empty-state"><b>没有找到应用</b>你可以修改搜索条件，或新建第一个应用。</div>

  <div v-if="drawer" class="drawer-mask" @click.self="drawer=false">
    <aside class="drawer">
      <div class="drawer-head"><div><b>{{ editing.id?'编辑应用':'新建应用' }}</b><span class="subtle" style="display:block;margin-top:3px">{{ editing.slug || '尚未创建' }}</span></div><button class="icon-button" @click="drawer=false"><X :size="15"/></button></div>
      <div class="drawer-body">
        <div class="drawer-tabs"><button v-for="t in [{k:'basic',n:'基础'},{k:'ios',n:'iOS'},{k:'android',n:'Android'},{k:'downloads',n:'下载方式'},{k:'images',n:'截图'}]" :key="t.k" :class="{active:drawerTab===t.k}" @click="drawerTab=t.k as any">{{t.n}}</button></div>

        <div v-if="drawerTab==='basic'" class="form-card"><div class="form-section"><h3>基础信息</h3><p>决定应用在后台和公开分发页中的基础身份。</p><div class="form-grid"><div class="field"><label>应用名称</label><input v-model="editing.name" class="input"></div><div class="field"><label>标识 slug</label><input v-model="editing.slug" class="input" :disabled="!!editing.id"><small>创建后不在普通编辑中修改。</small></div><div class="field"><label>图标 URL</label><input v-model="editing.icon_url" class="input" placeholder="uploads/images/…"></div><div class="field"><label>主题色</label><input v-model="editing.theme_color" class="input" type="color" style="padding:4px"></div><div class="field"><label>Font Awesome 图标</label><input v-model="editing.icon" class="input"></div><div class="field"><label>公开状态</label><select v-model="editing.is_active" class="select"><option :value="1">已发布</option><option :value="0">隐藏</option></select></div></div></div></div>

        <div v-if="drawerTab==='ios'" class="form-card"><div class="form-section"><h3>iOS 分发</h3><p>维护 IPA / OTA 与展示版本字段。</p><div class="form-grid"><div class="field"><label>版本</label><input v-model="editing.ios_version" class="input"></div><div class="field"><label>Bundle ID</label><input v-model="editing.ios_bundle_id" class="input"></div><div class="field full"><label>IPA URL</label><input v-model="editing.ios_ipa_url" class="input"></div><div class="field full"><label>Plist URL</label><input v-model="editing.ios_plist_url" class="input"></div><div class="field"><label>大小</label><input v-model="editing.ios_size" class="input"></div><div class="field"><label>模板</label><input v-model="editing.ios_template" class="input"></div><div class="field full"><label>说明</label><textarea v-model="editing.ios_description" class="textarea"></textarea></div></div></div></div>

        <div v-if="drawerTab==='android'" class="form-card"><div class="form-section"><h3>Android 分发</h3><p>维护 APK 与 Android 页面展示字段。</p><div class="form-grid"><div class="field"><label>版本</label><input v-model="editing.android_version" class="input"></div><div class="field"><label>大小</label><input v-model="editing.android_size" class="input"></div><div class="field full"><label>APK URL</label><input v-model="editing.android_apk_url" class="input"></div><div class="field"><label>模板</label><input v-model="editing.android_template" class="input"></div><div class="field full"><label>说明</label><textarea v-model="editing.android_description" class="textarea"></textarea></div></div></div></div>

        <div v-if="drawerTab==='downloads'">
          <div v-if="!editing.id" class="empty-state">请先保存应用，再添加下载方式。</div>
          <template v-else><div class="form-card"><div class="form-section"><h3>添加下载方式</h3><p>下载按钮仍使用现有下载追踪与 URL 校验逻辑。</p><div class="form-grid"><div class="field"><label>类型</label><select v-model="newDownload.btn_type" class="select"><option>android</option><option>ios</option><option>web</option><option>windows</option><option>tv</option></select></div><div class="field"><label>按钮文字</label><input v-model="newDownload.btn_text" class="input"></div><div class="field"><label>副标题</label><input v-model="newDownload.btn_subtext" class="input"></div><div class="field"><label>图标类</label><input v-model="newDownload.btn_icon" class="input"></div><div class="field full"><label>链接</label><input v-model="newDownload.href" class="input"></div></div><button class="button primary" style="margin-top:12px" @click="addDownload"><Plus :size="13"/>添加</button></div></div>
          <div class="task-list" style="margin-top:10px"><div v-for="d in downloads" :key="d.id" class="task-card"><div class="form-grid"><div class="field"><label>类型</label><input v-model="d.btn_type" class="input"></div><div class="field"><label>文字</label><input v-model="d.btn_text" class="input"></div><div class="field"><label>副标题</label><input v-model="d.btn_subtext" class="input"></div><div class="field"><label>图标</label><input v-model="d.btn_icon" class="input"></div><div class="field full"><label>链接</label><input v-model="d.href" class="input"></div></div><div class="page-actions" style="margin-top:9px"><button class="button small" @click="saveDownload(d)">保存</button><button class="button small danger" @click="removeDownload(d)"><Trash2 :size="12"/>删除</button></div></div></div></template>
        </div>

        <div v-if="drawerTab==='images'">
          <div v-if="!editing.id" class="empty-state">请先保存应用，再上传截图。</div>
          <template v-else><div class="form-card"><div class="form-section"><h3>添加截图</h3><p>可上传图片，也可以填写已有图片 URL。</p><div class="field"><label>图片 URL</label><input v-model="newImage.image_url" class="input"></div><div class="field" style="margin-top:10px"><label>替代文字</label><input v-model="newImage.alt_text" class="input"></div><div class="page-actions" style="margin-top:10px"><label class="button"><Upload :size="13"/>上传图片<input type="file" accept="image/*" hidden @change="uploadImage(($event.target as HTMLInputElement).files?.[0]!)"></label><button class="button primary" @click="addImage"><Plus :size="13"/>添加截图</button></div></div></div><div class="template-grid" style="margin-top:10px"><div v-for="img in images" :key="img.id" class="template-card"><img :src="img.image_url" :alt="img.alt_text||''" style="width:100%;height:160px;object-fit:cover;border-radius:9px;background:var(--surface-2)"><h3>{{ img.alt_text || '应用截图' }}</h3><div class="page-actions"><a class="button small" :href="img.image_url" target="_blank"><ExternalLink :size="12"/>查看</a><button class="button small danger" @click="removeImage(img)"><Trash2 :size="12"/>删除</button></div></div></div></template>
        </div>
      </div>
      <div class="drawer-foot"><button v-if="editing.id" class="button danger" style="margin-right:auto" @click="removeApp"><Trash2 :size="13"/>删除应用</button><button class="button" @click="drawer=false">取消</button><button class="button primary" :disabled="saving" @click="saveApp">{{saving?'保存中…':'保存更改'}}</button></div>
    </aside>
  </div>
</div>
</template>
