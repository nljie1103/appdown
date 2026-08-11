<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ArrowDown, ArrowUp, Copy, ImagePlus, Pencil, Plus, RefreshCw, Save, Trash2, Upload } from '@lucide/vue'
import { del, get, post, put } from '../api'
import { useAppStore } from '../stores/app'

const store = useAppStore()
const loading = ref(false)
const categories = ref<any[]>([])
const images = ref<any[]>([])
const categoryId = ref(0)
const newCategory = ref('')
const uploadFile = ref<File | null>(null)
const uploading = ref(false)
const upload = reactive({ rename: '', remark: '', format: 'webp', quality: 82 })

const activeCategory = computed(() => categories.value.find(c => Number(c.id) === Number(categoryId.value)))
const canUpload = computed(() => Number(categoryId.value) > 0 && !!uploadFile.value)

function src(url: string) {
  if (!url) return ''
  return /^(https?:)?\/\//i.test(url) || url.startsWith('/') ? url : `/${url}`
}
function dropImage(e:DragEvent){const f=e.dataTransfer?.files?.[0];if(f&&f.type.startsWith('image/'))uploadFile.value=f}

async function loadCategories() {
  categories.value = await get('/admin/api/image-library.php?action=categories')
  if (categoryId.value && !categories.value.some(c => Number(c.id) === Number(categoryId.value))) categoryId.value = 0
}

async function loadImages() {
  const qs = categoryId.value ? `&category_id=${categoryId.value}` : ''
  images.value = await get(`/admin/api/image-library.php?action=images${qs}`)
}

async function load() {
  loading.value = true
  try { await loadCategories(); await loadImages() }
  catch (e:any) { store.notify(e?.message || '媒体库加载失败', 'error') }
  finally { loading.value = false }
}

async function selectCategory(id: number) {
  categoryId.value = id
  try { await loadImages() } catch (e:any) { store.notify(e?.message || '图片加载失败', 'error') }
}

async function addCategory() {
  const name = newCategory.value.trim()
  if (!name) return
  try {
    const res:any = await post('/admin/api/image-library.php?action=categories', { name })
    newCategory.value = ''
    await loadCategories()
    categoryId.value = Number(res.id || 0)
    await loadImages()
    store.notify('图片分类已创建')
  } catch (e:any) { store.notify(e?.message || '创建分类失败', 'error') }
}

async function renameCategory(c:any) {
  const name = prompt('分类名称', c.name || '')?.trim()
  if (!name || name === c.name) return
  try { await put('/admin/api/image-library.php?action=categories', { id: c.id, name }); await loadCategories(); store.notify('分类已重命名') }
  catch (e:any) { store.notify(e?.message || '重命名失败', 'error') }
}

async function removeCategory(c:any) {
  if (!confirm(`删除分类「${c.name}」及其中全部图片文件？此操作不可恢复。`)) return
  try {
    await del('/admin/api/image-library.php?action=categories', { id: c.id })
    if (Number(categoryId.value) === Number(c.id)) categoryId.value = 0
    await load()
    store.notify('分类及图片已删除')
  } catch (e:any) { store.notify(e?.message || '删除分类失败', 'error') }
}

async function uploadImage() {
  if (!canUpload.value || !uploadFile.value) return store.notify('请选择分类和图片文件', 'error')
  const fd = new FormData()
  fd.append('category_id', String(categoryId.value))
  fd.append('rename', upload.rename.trim())
  fd.append('remark', upload.remark.trim())
  fd.append('format', upload.format)
  fd.append('quality', String(upload.quality))
  fd.append('file', uploadFile.value)
  uploading.value = true
  try {
    await post('/admin/api/image-library.php?action=images', fd)
    uploadFile.value = null
    upload.rename = ''
    upload.remark = ''
    await loadImages(); await loadCategories()
    store.notify('图片已加入媒体库')
  } catch (e:any) { store.notify(e?.message || '图片上传失败', 'error') }
  finally { uploading.value = false }
}

async function saveImage(img:any) {
  try {
    const r:any = await put('/admin/api/image-library.php?action=images', { id: img.id, filename: img.filename, remark: img.remark || '' })
    if (r?.file_url) img.file_url = r.file_url
    if (r?.filename) img.filename = r.filename
    store.notify('图片信息已保存')
  } catch (e:any) { store.notify(e?.message || '保存失败', 'error') }
}

async function removeImage(img:any) {
  if (!confirm(`永久删除图片「${img.filename}」？如果其他组件仍引用此路径，前台将无法显示。`)) return
  try { await del('/admin/api/image-library.php?action=images', { id: img.id }); await loadImages(); await loadCategories(); store.notify('图片已删除') }
  catch (e:any) { store.notify(e?.message || '删除失败', 'error') }
}

async function reorder(list:any[], index:number, delta:number, table:string) {
  const next = index + delta
  if (next < 0 || next >= list.length) return
  const copy = [...list]
  const [item] = copy.splice(index, 1)
  copy.splice(next, 0, item)
  try {
    await post('/admin/api/reorder.php', { table, order: copy.map(x => Number(x.id)) })
    if (table === 'image_categories') categories.value = copy
    else images.value = copy
  } catch (e:any) { store.notify(e?.message || '排序保存失败', 'error') }
}

async function copyPath(url:string) {
  try { await navigator.clipboard.writeText(url); store.notify('图片路径已复制') }
  catch { store.notify('复制失败，请手动复制', 'error') }
}

onMounted(load)
</script>

<template>
<div>
  <div class="page-head">
    <div><h1>媒体库</h1><p>统一管理 Logo、背景、应用图标、特色卡片图标和截图素材；支持分类、转换压缩与物理重命名。</p></div>
    <button class="button" @click="load"><RefreshCw :size="14"/>刷新</button>
  </div>

  <div class="two-col" style="grid-template-columns:260px minmax(0,1fr);align-items:start">
    <aside class="panel">
      <div class="panel-head"><div><h3>图片分类</h3><span>{{ categories.length }} 个分类</span></div></div>
      <div style="padding:10px">
        <button class="nav-item" :class="{active:categoryId===0}" style="width:100%" @click="selectCategory(0)"><ImagePlus :size="15"/><span>全部图片</span></button>
        <div v-for="(c,i) in categories" :key="c.id" class="task-card" style="margin-top:7px;padding:9px">
          <button style="border:0;background:none;padding:0;text-align:left;cursor:pointer;min-width:0;flex:1" @click="selectCategory(Number(c.id))">
            <b style="display:block;overflow:hidden;text-overflow:ellipsis">{{c.name}}</b><span class="subtle">{{c.image_count||0}} 张</span>
          </button>
          <div class="page-actions" style="margin-top:7px">
            <button class="icon-button" title="上移" @click="reorder(categories,i,-1,'image_categories')"><ArrowUp :size="13"/></button>
            <button class="icon-button" title="下移" @click="reorder(categories,i,1,'image_categories')"><ArrowDown :size="13"/></button>
            <button class="icon-button" title="重命名" @click="renameCategory(c)"><Pencil :size="13"/></button>
            <button class="icon-button" title="删除" @click="removeCategory(c)"><Trash2 :size="13"/></button>
          </div>
        </div>
        <div class="toolbar" style="margin-top:10px"><input v-model="newCategory" class="input" placeholder="新分类"><button class="button primary" @click="addCategory"><Plus :size="13"/></button></div>
      </div>
    </aside>

    <div>
      <section class="form-card">
        <div class="form-section">
          <h3>上传图片{{ activeCategory ? ` · ${activeCategory.name}` : '' }}</h3>
          <p v-if="!activeCategory">先在左侧选择一个分类，图片库不会把新文件放进“未分类”。</p>
          <div class="form-grid">
            <div class="field full"><label>文件</label><label class="drop-zone" style="display:block" @dragover.prevent @drop.prevent="dropImage"><Upload :size="22" style="margin:auto"/><b>{{uploadFile?.name||'点击选择或拖拽图片到这里'}}</b><span>支持项目上传策略允许的图片格式。</span><input type="file" accept="image/*" hidden @change="uploadFile=($event.target as HTMLInputElement).files?.[0]||null"></label></div>
            <div class="field"><label>自定义文件名</label><input v-model="upload.rename" class="input" placeholder="可留空"></div>
            <div class="field"><label>输出格式</label><select v-model="upload.format" class="select"><option value="webp">WebP</option><option value="jpg">JPG</option><option value="png">PNG</option><option value="gif">GIF</option><option value="original">保持原格式</option></select></div>
            <div class="field"><label>质量 {{upload.quality}}</label><input v-model.number="upload.quality" type="range" min="1" max="100" class="input"><small v-if="upload.format==='gif'">GIF 输出由 GD 处理，不使用有损质量参数。</small></div>
            <div class="field"><label>备注</label><input v-model="upload.remark" class="input"></div>
          </div>
          <button class="button primary" style="margin-top:10px" :disabled="!canUpload||uploading" @click="uploadImage"><Upload :size="13"/>{{uploading?'上传处理中…':'上传到媒体库'}}</button>
        </div>
      </section>

      <div v-if="loading" class="template-grid" style="margin-top:12px"><div v-for="i in 8" :key="i" class="template-card"><div class="skeleton" style="height:160px"></div></div></div>
      <div v-else-if="images.length" class="template-grid" style="margin-top:12px">
        <article v-for="(img,i) in images" :key="img.id" class="template-card" style="cursor:default">
          <img :src="src(img.file_url)" :alt="img.filename||''" style="width:100%;height:170px;object-fit:contain;border-radius:9px;background:var(--surface-2)">
          <div class="field" style="margin-top:10px"><label>文件名</label><input v-model="img.filename" class="input"></div>
          <div class="field" style="margin-top:7px"><label>备注</label><input v-model="img.remark" class="input"></div>
          <div class="subtle" style="margin-top:7px">{{img.width||0}}×{{img.height||0}} · {{img.file_size||'—'}}</div>
          <code style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:6px">{{img.file_url}}</code>
          <div class="page-actions" style="margin-top:9px">
            <button class="button small" @click="saveImage(img)"><Save :size="12"/>保存</button>
            <button class="button small" @click="copyPath(img.file_url)"><Copy :size="12"/>路径</button>
            <button class="icon-button" title="上移" @click="reorder(images,i,-1,'image_library')"><ArrowUp :size="13"/></button>
            <button class="icon-button" title="下移" @click="reorder(images,i,1,'image_library')"><ArrowDown :size="13"/></button>
            <button class="button small danger" @click="removeImage(img)"><Trash2 :size="12"/></button>
          </div>
        </article>
      </div>
      <div v-else class="empty-state" style="margin-top:12px"><b>这个范围还没有图片</b>选择分类后可以直接上传第一张素材。</div>
    </div>
  </div>
</div>
</template>
