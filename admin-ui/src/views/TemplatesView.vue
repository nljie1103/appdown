<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Check, ExternalLink, RefreshCw } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'

const store=useAppStore();const loading=ref(true);const current=ref('classic');const templates=ref<Record<string,any>>({});const saving=ref('')
async function load(){loading.value=true;try{const d:any=await get('/admin/api/templates.php');current.value=d.current||'classic';templates.value=d.templates||{}}catch(e:any){store.notify(e?.message||'模板加载失败','error')}finally{loading.value=false}}
async function select(key:string){if(key===current.value||saving.value)return;saving.value=key;try{await post('/admin/api/templates.php',{template:key});current.value=key;store.notify('分发页模板已切换')}catch(e:any){store.notify(e?.message||'切换失败','error')}finally{saving.value=''}}
onMounted(load)
const markers:Record<string,number>={classic:3,glass:3,minimal:4,midnight:4,aurora:3,store:3,bento:4,split:3,mobile:1}
</script>
<template><div>
<div class="page-head"><div><h1>页面模板</h1><p>模板 2.0 会真实改变 Hero、应用选择、下载区、截图、统计与特色卡片布局，而不是只换颜色。</p></div><div class="page-actions"><button class="button" @click="load"><RefreshCw :size="14"/>刷新</button><a class="button" :href="store.publicPath" target="_blank"><ExternalLink :size="14"/>前台预览</a></div></div>
<div v-if="loading" class="template-grid"><div v-for="i in 6" :key="i" class="template-card"><div class="skeleton" style="height:142px"></div><div class="skeleton" style="height:13px;width:50%;margin-top:12px"></div></div></div>
<div v-else class="template-grid">
 <article v-for="(item,key) in templates" :key="String(key)" class="template-card" :class="{active:current===key}" @click="select(String(key))">
  <div class="template-preview" :class="String(key)"><i v-for="n in (markers[String(key)]||3)" :key="n"></i></div>
  <h3>{{item.name}} <span v-if="current===key" class="badge" style="margin-left:5px"><Check :size="10"/>当前</span></h3>
  <p>{{item.description}}</p><small>{{item.preview || item.layout}}</small>
  <button v-if="saving===key" class="button small" style="margin-top:9px" disabled>正在切换…</button>
 </article>
</div>
</div></template>
