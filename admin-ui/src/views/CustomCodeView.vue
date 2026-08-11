<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Code2, RefreshCw, Save } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'
const store=useAppStore();const loading=ref(true);const saving=ref('');const codes=reactive<Record<string,string>>({head_css:'',head_js:'',footer_css:'',footer_js:''});const labels:any={head_css:['Head CSS','在 </head> 前注入，内置模板 CSS 之后，拥有最终覆盖权。'],head_js:['Head JavaScript','在页面头部执行，请避免阻塞加载。'],footer_css:['Footer CSS','页面底部附加样式。'],footer_js:['Footer JavaScript','页面主体渲染完成后的自定义脚本。']}
async function load(){loading.value=true;try{Object.assign(codes,await get('/admin/api/custom-code.php'))}catch(e:any){store.notify(e?.message||'代码加载失败','error')}finally{loading.value=false}}
async function save(position:string){saving.value=position;try{await post('/admin/api/custom-code.php',{position,code:codes[position]||''});store.notify(`${labels[position][0]} 已保存`)}catch(e:any){store.notify(e?.message||'保存失败','error')}finally{saving.value=''}}
onMounted(load)
</script>
<template><div>
<div class="page-head"><div><h1>自定义代码</h1><p>继续保留高级自定义能力；自定义 CSS 仍然位于内置模板样式之后。</p></div><button class="button" @click="load"><RefreshCw :size="14"/>重新读取</button></div>
<div v-if="loading" class="card"><div class="skeleton" style="height:420px"></div></div>
<div v-else class="two-col">
 <section v-for="(info,key) in labels" :key="String(key)" class="form-card"><div class="panel-head"><div><h3><Code2 :size="13" style="display:inline;vertical-align:-2px;margin-right:5px"/>{{info[0]}}</h3><span>{{info[1]}}</span></div><button class="button small primary" :disabled="saving===key" @click="save(String(key))"><Save :size="12"/>保存</button></div><div style="padding:12px"><textarea v-model="codes[String(key)]" class="textarea" spellcheck="false" style="min-height:330px;font:10.5px/1.6 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace"></textarea></div></section>
</div>
</div></template>
