<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RefreshCw, Rocket, ShieldCheck } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'
const store=useAppStore();const data=ref<any>(null);const loading=ref(false);const updating=ref(false)
async function load(force=false){loading.value=true;try{data.value=await get(`/admin/api/update.php${force?'?refresh=1':''}`)}catch(e:any){store.notify(e?.message||'版本检查失败','error')}finally{loading.value=false}}
async function update(){if(!data.value?.latest?.update_available)return;if(!confirm(`将先备份程序代码，然后升级到 ${data.value.latest.tag}。data/ 和 uploads/ 会保留。确定继续？`))return;updating.value=true;try{const r:any=await post('/admin/api/update.php',{action:'update',tag:data.value.latest.tag});store.notify(`已升级到 ${r.result?.tag||data.value.latest.tag}`);setTimeout(()=>location.reload(),1200)}catch(e:any){store.notify(e?.message||'升级失败','error')}finally{updating.value=false}}
onMounted(()=>load(false))
</script>
<template><div>
<div class="page-head"><div><h1>在线升级</h1><p>固定同步 nljie1103/appdown 的正式 Release；更新前自动备份程序代码。</p></div><button class="button" :disabled="loading" @click="load(true)"><RefreshCw :size="14"/>重新检查 GitHub</button></div>
<div v-if="loading&&!data" class="card"><div class="skeleton" style="height:180px"></div></div>
<template v-else-if="data">
 <div class="two-col">
  <section class="card"><h3>版本状态</h3><div class="stats-grid" style="grid-template-columns:1fr 1fr;margin-top:13px"><div class="metric"><div class="metric-label">当前版本</div><div class="metric-value" style="font-size:20px">{{data.current.tag}}</div><div class="metric-sub">{{data.current.edition}}</div></div><div class="metric"><div class="metric-label">GitHub 最新正式版</div><div class="metric-value" style="font-size:20px">{{data.latest.tag}}</div><div class="metric-sub">{{data.latest.published_at||'—'}}</div></div></div><div v-if="data.latest.update_available" style="margin-top:14px"><button class="button primary" :disabled="updating" @click="update"><Rocket :size="14"/>{{updating?'正在升级…':`升级到 ${data.latest.tag}`}}</button></div><div v-else class="task-card" style="margin-top:14px"><div class="task-head"><b>当前已经是最新版</b><span class="badge"><ShieldCheck :size="10"/>正常</span></div></div></section>
  <section class="card"><h3>升级保护</h3><div class="check-list" style="margin-top:12px"><div class="check-row">✓ 只接受当前 edition 的正式 Release</div><div class="check-row">✓ 升级前备份程序代码</div><div class="check-row">✓ data/、uploads/、安装锁不覆盖</div><div class="check-row">✓ ZIP 路径、符号链接、大小与版本校验</div><div class="check-row">✓ 失败时尝试自动回滚</div></div></section>
 </div>
 <section class="card" style="margin-top:13px"><h3>Release 说明</h3><pre style="white-space:pre-wrap;font:11px/1.75 inherit;color:var(--text-2);margin:12px 0 0">{{data.latest.notes||'无更新说明'}}</pre></section>
</template>
</div></template>
