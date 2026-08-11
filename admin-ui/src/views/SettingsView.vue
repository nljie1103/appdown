<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Save, RefreshCw, ExternalLink } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'

const store=useAppStore();const loading=ref(true);const saving=ref(false);const section=ref('general');const original=ref('')
const form=reactive<Record<string,string>>({})
const allowed=['site_title','site_heading','logo_url','favicon_url','notice_text','notice_enabled','copyright','carousel_interval','stats_downloads','stats_rating','stats_daily_active','font_url','font_family','captcha_enabled','bg_type','bg_color','bg_gradient','bg_image','effects_config','inapp_redirect','filter_bots']
const tabs=[['general','常规'],['brand','品牌'],['distribution','分发'],['stats','统计'],['security','安全'],['background','背景']]
function snapshot(){return JSON.stringify(Object.fromEntries(allowed.map(k=>[k,form[k]??''])))}
const dirty=computed(()=>!loading.value&&snapshot()!==original.value)
async function load(){loading.value=true;try{const d:any=await get('/admin/api/settings.php');allowed.forEach(k=>form[k]=String(d[k]??''));original.value=snapshot()}catch(e:any){store.notify(e?.message||'设置加载失败','error')}finally{loading.value=false}}
async function save(){saving.value=true;try{await post('/admin/api/settings.php',{settings:Object.fromEntries(allowed.map(k=>[k,form[k]??'']))});original.value=snapshot();store.notify('站点设置已保存');await store.bootstrap()}catch(e:any){store.notify(e?.message||'保存失败','error')}finally{saving.value=false}}
function reset(){const d=JSON.parse(original.value||'{}');allowed.forEach(k=>form[k]=String(d[k]??''))}
onMounted(load)
</script>
<template><div>
<div class="page-head"><div><h1>站点设置</h1><p>管理站点品牌、公开分发、统计、访问保护与背景效果。</p></div><div class="page-actions"><button class="button" @click="load"><RefreshCw :size="14"/>重新读取</button><a class="button" :href="store.publicPath" target="_blank"><ExternalLink :size="14"/>前台</a></div></div>
<div v-if="loading" class="card"><div v-for="i in 5" :key="i" class="skeleton" style="height:42px;margin:9px 0"></div></div>
<div v-else class="settings-layout">
 <aside class="settings-nav"><button v-for="t in tabs" :key="t[0]" :class="{active:section===t[0]}" @click="section=t[0]">{{t[1]}}</button></aside>
 <div>
  <section class="form-card">
   <template v-if="section==='general'"><div class="form-section"><h3>常规</h3><p>影响后台标题和公开页基本信息。</p><div class="setting-row"><div class="setting-copy"><b>站点名称</b><span>后台工作区与浏览器标题。</span></div><input v-model="form.site_title" class="input"></div><div class="setting-row"><div class="setting-copy"><b>首页标题</b><span>公开分发页主标题。</span></div><input v-model="form.site_heading" class="input"></div><div class="setting-row"><div class="setting-copy"><b>公告</b><span>可在公开页显示一条站点公告。</span></div><div><select v-model="form.notice_enabled" class="select" style="margin-bottom:8px"><option value="1">启用</option><option value="0">关闭</option></select><textarea v-model="form.notice_text" class="textarea"></textarea></div></div><div class="setting-row"><div class="setting-copy"><b>版权信息</b><span>显示在公开页底部。</span></div><input v-model="form.copyright" class="input"></div></div></template>
   <template v-if="section==='brand'"><div class="form-section"><h3>品牌</h3><p>Logo、Favicon 与字体。</p><div class="setting-row"><div class="setting-copy"><b>Logo URL</b><span>支持上传目录路径或绝对 URL。</span></div><input v-model="form.logo_url" class="input"></div><div class="setting-row"><div class="setting-copy"><b>Favicon URL</b><span>浏览器标签图标。</span></div><input v-model="form.favicon_url" class="input"></div><div class="setting-row"><div class="setting-copy"><b>字体文件 URL</b><span>留空使用系统字体。</span></div><input v-model="form.font_url" class="input"></div><div class="setting-row"><div class="setting-copy"><b>字体族名称</b><span>与上传字体对应。</span></div><input v-model="form.font_family" class="input"></div></div></template>
   <template v-if="section==='distribution'"><div class="form-section"><h3>公开分发</h3><p>控制轮播和应用内浏览器行为。</p><div class="setting-row"><div class="setting-copy"><b>轮播间隔</b><span>毫秒，建议 3000–8000。</span></div><input v-model="form.carousel_interval" class="input" type="number"></div><div class="setting-row"><div class="setting-copy"><b>应用内浏览器跳转</b><span>检测微信等内置浏览器后的处理策略。</span></div><select v-model="form.inapp_redirect" class="select"><option value="1">启用</option><option value="0">关闭</option></select></div></div></template>
   <template v-if="section==='stats'"><div class="form-section"><h3>展示统计</h3><p>公开页中的下载、评分和日活展示值。</p><div class="setting-row"><div class="setting-copy"><b>下载次数</b><span>公开页展示统计。</span></div><input v-model="form.stats_downloads" class="input" type="number"></div><div class="setting-row"><div class="setting-copy"><b>评分</b><span>例如 4.9。</span></div><input v-model="form.stats_rating" class="input" type="number" step="0.1"></div><div class="setting-row"><div class="setting-copy"><b>日活</b><span>公开页展示值。</span></div><input v-model="form.stats_daily_active" class="input" type="number"></div></div></template>
   <template v-if="section==='security'"><div class="form-section"><h3>访问保护</h3><p>登录验证码与公共统计过滤。</p><div class="setting-row"><div class="setting-copy"><b>登录验证码</b><span>启用简单数学验证码。</span></div><select v-model="form.captcha_enabled" class="select"><option value="1">启用</option><option value="0">关闭</option></select></div><div class="setting-row"><div class="setting-copy"><b>过滤爬虫统计</b><span>减少机器人对访问统计的干扰。</span></div><select v-model="form.filter_bots" class="select"><option value="1">启用</option><option value="0">关闭</option></select></div></div></template>
   <template v-if="section==='background'"><div class="form-section"><h3>背景与效果</h3><p>模板仍可覆盖布局；这些值作为站点级背景设置。</p><div class="setting-row"><div class="setting-copy"><b>背景类型</b><span>default / color / gradient / image</span></div><select v-model="form.bg_type" class="select"><option value="default">默认</option><option value="color">纯色</option><option value="gradient">渐变</option><option value="image">图片</option></select></div><div class="setting-row"><div class="setting-copy"><b>纯色</b><span>CSS 颜色值。</span></div><input v-model="form.bg_color" class="input"></div><div class="setting-row"><div class="setting-copy"><b>渐变</b><span>完整 CSS gradient。</span></div><input v-model="form.bg_gradient" class="input"></div><div class="setting-row"><div class="setting-copy"><b>背景图片</b><span>图片 URL。</span></div><input v-model="form.bg_image" class="input"></div><div class="setting-row"><div class="setting-copy"><b>效果配置 JSON</b><span>保留现有粒子/特效配置。</span></div><textarea v-model="form.effects_config" class="textarea" style="min-height:150px"></textarea></div></div></template>
  </section>
  <div v-if="dirty" class="sticky-save"><span>● 有未保存的修改</span><div class="page-actions"><button class="button" @click="reset">放弃</button><button class="button primary" :disabled="saving" @click="save"><Save :size="13"/>{{saving?'保存中…':'保存更改'}}</button></div></div>
 </div>
</div>
</div></template>
