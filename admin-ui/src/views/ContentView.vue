<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ArrowDown, ArrowUp, Pencil, Plus, RefreshCw, Save, Trash2, Upload } from '@lucide/vue'
import { del, get, post, put } from '../api'
import { useAppStore } from '../stores/app'

const store = useAppStore()
const tab = ref<'features'|'links'>('features')
const loading = ref(false)
const features = ref<any[]>([])
const categories = ref<any[]>([])
const links = ref<any[]>([])
const featureDraft = reactive<any>({title:'',description:'',icon:'fas fa-star',icon_url:'',category_id:0})
const categoryName = ref('')
const linkDraft = reactive<any>({name:'',url:'https://',icon:'fas fa-link',icon_url:'',show_icon:1})
const uploading = ref('')

async function load(){
  loading.value=true
  try{[features.value,categories.value,links.value]=await Promise.all([get('/admin/api/features.php'),get('/admin/api/features.php?action=categories'),get('/admin/api/links.php')])}
  catch(e:any){store.notify(e?.message||'内容组件加载失败','error')}
  finally{loading.value=false}
}

async function addCategory(){
  if(!categoryName.value.trim())return
  try{await post('/admin/api/features.php?action=categories',{name:categoryName.value});categoryName.value='';await load();store.notify('分类已添加')}
  catch(e:any){store.notify(e?.message||'添加失败','error')}
}
async function renameCategory(c:any){
  const name=prompt('分类名称',c.name||'')?.trim();if(!name||name===c.name)return
  try{await put('/admin/api/features.php?action=categories',{id:c.id,name});await load();store.notify('分类已重命名')}
  catch(e:any){store.notify(e?.message||'重命名失败','error')}
}
async function removeCategory(c:any){
  if(!confirm(`删除分类「${c.name}」？卡片会变为未分类。`))return
  try{await del('/admin/api/features.php?action=categories',{id:c.id});await load();store.notify('分类已删除')}
  catch(e:any){store.notify(e?.message||'删除失败','error')}
}

async function addFeature(){
  if(!featureDraft.title.trim())return store.notify('标题不能为空','error')
  try{await post('/admin/api/features.php',featureDraft);Object.assign(featureDraft,{title:'',description:'',icon:'fas fa-star',icon_url:'',category_id:0});await load();store.notify('特色卡片已添加')}
  catch(e:any){store.notify(e?.message||'添加失败','error')}
}
async function saveFeature(f:any){
  try{await put('/admin/api/features.php',{id:f.id,title:f.title,description:f.description,icon:f.icon||'',icon_url:f.icon_url||'',category_id:Number(f.category_id||0),is_active:Number(f.is_active)?1:0});store.notify('卡片已保存')}
  catch(e:any){store.notify(e?.message||'保存失败','error')}
}
async function removeFeature(f:any){
  if(!confirm(`删除「${f.title}」？`))return
  try{await del('/admin/api/features.php',{id:f.id});features.value=features.value.filter(x=>x.id!==f.id);await load();store.notify('卡片已删除')}
  catch(e:any){store.notify(e?.message||'删除失败','error')}
}

async function addLink(){
  if(!linkDraft.name.trim())return store.notify('链接名称不能为空','error')
  try{await post('/admin/api/links.php',linkDraft);Object.assign(linkDraft,{name:'',url:'https://',icon:'fas fa-link',icon_url:'',show_icon:1});await load();store.notify('友情链接已添加')}
  catch(e:any){store.notify(e?.message||'添加失败','error')}
}
async function saveLink(l:any){
  try{await put('/admin/api/links.php',{id:l.id,name:l.name,url:l.url||'#',icon:l.icon||'',icon_url:l.icon_url||'',show_icon:Number(l.show_icon)?1:0,is_active:Number(l.is_active)?1:0});store.notify('链接已保存')}
  catch(e:any){store.notify(e?.message||'保存失败','error')}
}
async function removeLink(l:any){
  if(!confirm(`删除「${l.name}」？`))return
  try{await del('/admin/api/links.php',{id:l.id});links.value=links.value.filter(x=>x.id!==l.id);store.notify('链接已删除')}
  catch(e:any){store.notify(e?.message||'删除失败','error')}
}

async function move(list:any[],index:number,delta:number,table:string){
  const next=index+delta;if(next<0||next>=list.length)return
  const copy=[...list];const [item]=copy.splice(index,1);copy.splice(next,0,item)
  try{await post('/admin/api/reorder.php',{table,order:copy.map(x=>Number(x.id))});if(table==='feature_cards')features.value=copy;else if(table==='friend_links')links.value=copy;else categories.value=copy;store.notify('排序已保存')}
  catch(e:any){store.notify(e?.message||'排序失败','error')}
}

async function uploadIcon(target:any,key:string,file?:File){
  if(!file)return
  const token=`${key}-${Date.now()}`;uploading.value=token
  const fd=new FormData();fd.append('category','image');fd.append('file',file)
  try{const r:any=await post('/admin/api/upload.php',fd);target[key]=r.url||'';store.notify('图标图片已上传')}
  catch(e:any){store.notify(e?.message||'上传失败','error')}
  finally{uploading.value=''}
}

onMounted(load)
</script>

<template><div>
<div class="page-head"><div><h1>内容组件</h1><p>完整管理分发页特色卡片、分类与友情链接，包括图片图标、启停状态和显示顺序。</p></div><button class="button" @click="load"><RefreshCw :size="14"/>刷新</button></div>
<div class="drawer-tabs" style="width:max-content;margin-bottom:13px"><button :class="{active:tab==='features'}" @click="tab='features'">特色卡片</button><button :class="{active:tab==='links'}" @click="tab='links'">友情链接</button></div>

<template v-if="tab==='features'">
 <div class="two-col" style="grid-template-columns:minmax(0,1fr) 300px">
  <div>
   <section class="form-card"><div class="form-section"><h3>新增特色卡片</h3><p>FA 图标与图片图标可二选一；有图片 URL 时前台优先显示图片。</p><div class="form-grid"><div class="field"><label>标题</label><input v-model="featureDraft.title" class="input"></div><div class="field"><label>分类</label><select v-model.number="featureDraft.category_id" class="select"><option :value="0">未分类 / 全局</option><option v-for="c in categories" :value="Number(c.id)" :key="c.id">{{c.name}}</option></select></div><div class="field"><label>FA 图标类</label><input v-model="featureDraft.icon" class="input" placeholder="fas fa-star"></div><div class="field"><label>图片图标 URL</label><div class="toolbar"><input v-model="featureDraft.icon_url" class="input"><label class="button small"><Upload :size="12"/><input type="file" accept="image/*" hidden @change="uploadIcon(featureDraft,'icon_url',($event.target as HTMLInputElement).files?.[0])"></label></div></div><div class="field full"><label>说明</label><textarea v-model="featureDraft.description" class="textarea"></textarea></div></div><button class="button primary" style="margin-top:10px" @click="addFeature"><Plus :size="13"/>添加卡片</button></div></section>

   <div class="task-list" style="margin-top:12px">
    <article v-for="(f,i) in features" :key="f.id" class="task-card">
      <div class="task-head"><div><b>{{f.title||'未命名卡片'}}</b><span class="subtle" style="display:block;margin-top:2px">ID #{{f.id}}</span></div><div class="page-actions"><button class="icon-button" title="上移" @click="move(features,i,-1,'feature_cards')"><ArrowUp :size="13"/></button><button class="icon-button" title="下移" @click="move(features,i,1,'feature_cards')"><ArrowDown :size="13"/></button></div></div>
      <div class="form-grid" style="margin-top:9px"><div class="field"><label>标题</label><input v-model="f.title" class="input"></div><div class="field"><label>分类</label><select v-model.number="f.category_id" class="select"><option :value="0">未分类</option><option v-for="c in categories" :value="Number(c.id)" :key="c.id">{{c.name}}</option></select></div><div class="field"><label>FA 图标类</label><input v-model="f.icon" class="input"></div><div class="field"><label>图片图标 URL</label><div class="toolbar"><input v-model="f.icon_url" class="input"><label class="button small"><Upload :size="12"/><input type="file" accept="image/*" hidden @change="uploadIcon(f,'icon_url',($event.target as HTMLInputElement).files?.[0])"></label></div></div><div class="field full"><label>说明</label><textarea v-model="f.description" class="textarea"></textarea></div><div class="field"><label>状态</label><select v-model.number="f.is_active" class="select"><option :value="1">启用</option><option :value="0">停用</option></select></div></div>
      <div class="page-actions" style="margin-top:10px"><button class="button small" @click="saveFeature(f)"><Save :size="12"/>保存</button><button class="button small danger" @click="removeFeature(f)"><Trash2 :size="12"/>删除</button></div>
    </article>
    <div v-if="!features.length&&!loading" class="empty-state">暂无特色卡片。</div>
   </div>
  </div>

  <aside class="card"><h3>分类</h3><p class="subtle">应用可以绑定某个分类，前台只展示对应卡片组。</p><div class="toolbar" style="margin-top:12px"><input v-model="categoryName" class="input" placeholder="新分类名称"><button class="button primary" @click="addCategory"><Plus :size="13"/></button></div><div class="task-list"><div v-for="(c,i) in categories" :key="c.id" class="task-card"><div class="task-head"><b>{{c.name}}</b><span class="badge muted">{{c.card_count||0}} 卡片</span></div><div class="page-actions" style="margin-top:7px"><button class="icon-button" title="上移" @click="move(categories,i,-1,'feature_categories')"><ArrowUp :size="13"/></button><button class="icon-button" title="下移" @click="move(categories,i,1,'feature_categories')"><ArrowDown :size="13"/></button><button class="button small" @click="renameCategory(c)"><Pencil :size="12"/>重命名</button><button class="button small danger" @click="removeCategory(c)"><Trash2 :size="12"/></button></div></div></div></aside>
 </div>
</template>

<template v-else>
 <section class="form-card"><div class="form-section"><h3>新增友情链接</h3><p>可以使用 FA 图标、自定义图片或彻底关闭图标显示。</p><div class="form-grid"><div class="field"><label>名称</label><input v-model="linkDraft.name" class="input"></div><div class="field"><label>URL</label><input v-model="linkDraft.url" class="input"></div><div class="field"><label>FA 图标类</label><input v-model="linkDraft.icon" class="input"></div><div class="field"><label>图片图标 URL</label><div class="toolbar"><input v-model="linkDraft.icon_url" class="input"><label class="button small"><Upload :size="12"/><input type="file" accept="image/*" hidden @change="uploadIcon(linkDraft,'icon_url',($event.target as HTMLInputElement).files?.[0])"></label></div></div><div class="field"><label>显示图标</label><select v-model.number="linkDraft.show_icon" class="select"><option :value="1">显示</option><option :value="0">隐藏</option></select></div></div><button class="button primary" style="margin-top:10px" @click="addLink"><Plus :size="13"/>添加链接</button></div></section>

 <div class="task-list" style="margin-top:12px">
  <article v-for="(l,i) in links" :key="l.id" class="task-card">
    <div class="task-head"><div><b>{{l.name||'未命名链接'}}</b><span class="subtle" style="display:block;margin-top:2px">{{l.url}}</span></div><div class="page-actions"><button class="icon-button" title="上移" @click="move(links,i,-1,'friend_links')"><ArrowUp :size="13"/></button><button class="icon-button" title="下移" @click="move(links,i,1,'friend_links')"><ArrowDown :size="13"/></button></div></div>
    <div class="form-grid" style="margin-top:9px"><div class="field"><label>名称</label><input v-model="l.name" class="input"></div><div class="field"><label>URL</label><input v-model="l.url" class="input"></div><div class="field"><label>FA 图标</label><input v-model="l.icon" class="input"></div><div class="field"><label>图片图标 URL</label><div class="toolbar"><input v-model="l.icon_url" class="input"><label class="button small"><Upload :size="12"/><input type="file" accept="image/*" hidden @change="uploadIcon(l,'icon_url',($event.target as HTMLInputElement).files?.[0])"></label></div></div><div class="field"><label>显示图标</label><select v-model.number="l.show_icon" class="select"><option :value="1">显示</option><option :value="0">隐藏</option></select></div><div class="field"><label>状态</label><select v-model.number="l.is_active" class="select"><option :value="1">启用</option><option :value="0">停用</option></select></div></div>
    <div class="page-actions" style="margin-top:10px"><button class="button small" @click="saveLink(l)"><Save :size="12"/>保存</button><button class="button small danger" @click="removeLink(l)"><Trash2 :size="12"/>删除</button></div>
  </article>
  <div v-if="!links.length&&!loading" class="empty-state">暂无友情链接。</div>
 </div>
</template>
</div></template>
