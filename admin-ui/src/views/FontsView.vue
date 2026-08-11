<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RefreshCw, Save, Upload } from 'lucide-vue-next'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'
const store=useAppStore();const family=ref('CustomFont');const url=ref('');const uploading=ref(false)
const builtins=[['system-ui','系统默认'],['Arial, sans-serif','Arial'],['"Segoe UI", sans-serif','Segoe UI'],['"PingFang SC", sans-serif','苹方'],['"Microsoft YaHei", sans-serif','微软雅黑'],['"Noto Sans SC", sans-serif','Noto Sans SC'],['serif','衬线体'],['monospace','等宽体']]
async function load(){try{const s:any=await get('/admin/api/settings.php');family.value=s.font_family||'CustomFont';url.value=s.font_url||''}catch(e:any){store.notify(e?.message||'字体设置加载失败','error')}}
async function save(){try{await post('/admin/api/settings.php',{settings:{font_family:family.value.trim(),font_url:url.value.trim()}});store.notify('字体设置已保存')}catch(e:any){store.notify(e?.message||'保存失败','error')}}
async function upload(file?:File){if(!file)return;uploading.value=true;const fd=new FormData();fd.append('category','font');fd.append('file',file);try{const r:any=await post('/admin/api/upload.php',fd);if(!r.ok)throw new Error(r.error||'上传失败');url.value=r.url||'';if(!family.value||family.value==='CustomFont')family.value='用户上传字体';store.notify('字体文件已上传，请保存设置')}catch(e:any){store.notify(e?.message||'上传失败','error')}finally{uploading.value=false}}
onMounted(load)
</script>
<template><div>
<div class="page-head"><div><h1>字体管理</h1><p>选择系统字体或上传 TTF / OTF / WOFF / WOFF2，公开分发页继续使用现有字体加载逻辑。</p></div><button class="button" @click="load"><RefreshCw :size="14"/>重新读取</button></div>
<div class="two-col">
 <section class="form-card"><div class="form-section"><h3>当前字体</h3><p>设置会写入站点配置，并自动进入公开 config API。</p><div class="field"><label>字体族</label><input v-model="family" class="input"></div><div class="field" style="margin-top:10px"><label>字体文件 URL</label><div class="toolbar" style="margin:0"><input v-model="url" class="input" style="flex:1"><label class="button"><Upload :size="13"/>{{uploading?'上传中…':'上传'}}<input type="file" accept=".ttf,.otf,.woff,.woff2" hidden @change="upload(($event.target as HTMLInputElement).files?.[0])"></label></div></div><div :style="{fontFamily:family}" style="margin-top:14px;padding:20px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);font-size:22px;line-height:1.5">字体预览 · AppDown<br>ABCDEFG abcdefg 1234567890<br>应用分发，简洁而现代。</div><button class="button primary" style="margin-top:12px" @click="save"><Save :size="13"/>保存字体设置</button></div></section>
 <section class="card"><h3>系统字体</h3><p class="subtle">不需要下载外部字体文件，性能最好。</p><div class="task-list" style="margin-top:12px"><button v-for="f in builtins" :key="f[0]" class="task-card" style="text-align:left;color:var(--text)" :style="{fontFamily:f[0]}" @click="family=f[0];url=''" type="button"><div class="task-head"><b>{{f[1]}}</b><span class="badge muted" v-if="family===f[0]">当前</span></div><p style="font-size:16px;color:var(--text);margin-top:8px">AppDown 应用分发 ABC 123</p></button></div></section>
</div>
</div></template>
