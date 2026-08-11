<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Activity, ArrowDownToLine, Eye, Smartphone, RefreshCw } from 'lucide-vue-next'
import { get } from '../api'
import { useAppStore } from '../stores/app'

const store = useAppStore()
const loading = ref(true)
const error = ref('')
const data = ref<any>(null)

async function load() {
  loading.value = true; error.value = ''
  try { data.value = await get('/admin/api/dashboard.php') }
  catch (e: any) { error.value = e?.message || '仪表盘加载失败' }
  finally { loading.value = false }
}
onMounted(load)

const change = computed(() => (data.value?.today_visits || 0) - (data.value?.yesterday_visits || 0))
const maxTrend = computed(() => Math.max(1, ...(data.value?.trend_7day?.visits || [1]), ...(data.value?.trend_7day?.downloads || [1])))
function points(values: number[] = []) {
  if (!values.length) return ''
  const w = 680, h = 220, pad = 18
  return values.map((v, i) => `${pad + (i * (w - pad * 2) / Math.max(1, values.length - 1))},${h - pad - (v / maxTrend.value) * (h - pad * 2)}`).join(' ')
}
</script>

<template>
  <div>
    <div class="page-head">
      <div><h1>仪表盘</h1><p>欢迎回来，{{ store.userName }}。这里显示真实访问、下载与来源数据。</p></div>
      <div class="page-actions"><button class="button" :disabled="loading" @click="load"><RefreshCw :size="14"/>刷新</button><a class="button" :href="store.publicPath" target="_blank">打开分发页</a></div>
    </div>

    <div v-if="error" class="empty-state"><b>数据加载失败</b>{{ error }}<div style="margin-top:12px"><button class="button" @click="load">重试</button></div></div>
    <template v-else>
      <div class="stats-grid">
        <div class="metric"><div class="metric-top"><span class="metric-label">今日访问</span><span class="metric-icon"><Eye :size="14"/></span></div><div class="metric-value">{{ loading ? '—' : Number(data?.today_visits||0).toLocaleString() }}</div><div class="metric-sub"><span :class="change>=0?'positive':'negative'">{{ change>0?'↑':change<0?'↓':'•' }} {{ Math.abs(change) }}</span> 较昨日</div></div>
        <div class="metric"><div class="metric-top"><span class="metric-label">今日下载</span><span class="metric-icon"><ArrowDownToLine :size="14"/></span></div><div class="metric-value">{{ loading ? '—' : Number(data?.today_downloads_total||0).toLocaleString() }}</div><div class="metric-sub">下载按钮真实点击</div></div>
        <div class="metric"><div class="metric-top"><span class="metric-label">累计访问</span><span class="metric-icon"><Activity :size="14"/></span></div><div class="metric-value">{{ loading ? '—' : Number(data?.total_visits||0).toLocaleString() }}</div><div class="metric-sub">站点历史访问</div></div>
        <div class="metric"><div class="metric-top"><span class="metric-label">累计下载</span><span class="metric-icon"><Smartphone :size="14"/></span></div><div class="metric-value">{{ loading ? '—' : Number(data?.total_downloads||0).toLocaleString() }}</div><div class="metric-sub">全部应用累计下载</div></div>
      </div>

      <div class="two-col">
        <section class="panel">
          <div class="panel-head"><div><h3>过去 7 天</h3><span>访问与下载趋势</span></div><span>实时统计</span></div>
          <div class="chart-box">
            <svg viewBox="0 0 680 220" preserveAspectRatio="none">
              <g stroke="var(--border)" stroke-width="1"><line v-for="y in [25,70,115,160,205]" :key="y" x1="18" x2="662" :y1="y" :y2="y"/></g>
              <polyline v-if="data" :points="points(data.trend_7day?.visits)" fill="none" stroke="var(--primary)" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/>
              <polyline v-if="data" :points="points(data.trend_7day?.downloads)" fill="none" stroke="var(--success)" stroke-width="2.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head"><div><h3>今日来源</h3><span>TOP 10</span></div><span>{{ data?.top_referers?.length || 0 }} 项</span></div>
          <div class="activity-list" v-if="data?.top_referers?.length">
            <div class="activity-row" v-for="(r,i) in data.top_referers" :key="r.referer+i"><div class="activity-icon">{{ i+1 }}</div><div style="min-width:0;flex:1"><b>{{ r.source_name }}</b><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.referer }}</span></div><b>{{ r.count }}</b></div>
          </div>
          <div v-else class="empty-state" style="margin:12px">今日暂无来源数据</div>
        </section>
      </div>

      <section class="panel" style="margin-top:13px">
        <div class="panel-head"><div><h3>今日下载明细</h3><span>按应用与下载类型聚合</span></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>应用</th><th>类型</th><th>次数</th></tr></thead><tbody>
          <template v-for="(types, app) in (data?.today_downloads||{})" :key="String(app)"><tr v-for="(count,type) in types as any" :key="String(type)"><td>{{ app }}</td><td>{{ type }}</td><td>{{ count }}</td></tr></template>
          <tr v-if="!Object.keys(data?.today_downloads||{}).length"><td colspan="3" class="subtle">今日暂无下载</td></tr>
        </tbody></table></div>
      </section>
    </template>
  </div>
</template>
