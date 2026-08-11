<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AlertTriangle, Code2, RefreshCw, Save, Sparkles } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'

const store=useAppStore()
const loading=ref(true)
const saving=ref('')
const savingEffects=ref(false)
const codes=reactive<Record<string,string>>({head_css:'',head_js:'',footer_css:'',footer_js:''})
const labels:any={
  head_css:['Head CSS','在 </head> 前注入，内置模板 CSS 之后，拥有最终覆盖权。'],
  head_js:['Head JavaScript','在页面头部执行，请避免阻塞加载。'],
  footer_css:['Footer CSS','页面底部附加样式。'],
  footer_js:['Footer JavaScript','页面主体渲染完成后的自定义脚本。'],
}

type ParamDef={key:string,label:string,min:number,max:number,default:number}
type EffectDef={name:string,icon:string,color:string,desc:string,params:ParamDef[],extra?:'music_url'|'festival'}
type FestivalDef={id:string,name:string,date:string,greeting:string,lunar?:boolean,dynamic?:boolean}
type FestivalConfig={enabled:boolean,greeting:string}
type EffectConfig={enabled:boolean,params:Record<string,number>,music_url?:string,festivals?:Record<string,FestivalConfig>}

const EFFECTS:Record<string,EffectDef>={
  sakura:{name:'全屏樱花',icon:'🌸',color:'#FFB7C5',desc:'飘落的樱花瓣特效',params:[{key:'count',label:'数量',min:5,max:100,default:35},{key:'size',label:'大小',min:2,max:20,default:8},{key:'speed',label:'速度',min:10,max:100,default:50}]},
  snow:{name:'全屏雪花',icon:'❄️',color:'#87CEEB',desc:'飘落的雪花特效',params:[{key:'count',label:'数量',min:10,max:200,default:60},{key:'size',label:'大小',min:1,max:10,default:4},{key:'speed',label:'速度',min:10,max:100,default:50}]},
  lantern:{name:'节日灯笼',icon:'🏮',color:'#FF4500',desc:'页面顶部悬挂灯笼',params:[{key:'size',label:'大小',min:20,max:100,default:50}]},
  particles:{name:'粒子背景',icon:'✨',color:'#3498DB',desc:'动态粒子连线效果',params:[{key:'count',label:'数量',min:10,max:150,default:50},{key:'speed',label:'速度',min:10,max:100,default:40},{key:'opacity',label:'透明度',min:10,max:100,default:40}]},
  cursor:{name:'鼠标跟随',icon:'🌟',color:'#F39C12',desc:'鼠标移动时星星拖尾',params:[{key:'size',label:'大小',min:2,max:15,default:6}]},
  ribbon:{name:'彩带背景',icon:'🎀',color:'#E91E63',desc:'点击刷新彩带背景',params:[{key:'opacity',label:'透明度',min:10,max:100,default:60}]},
  grayscale:{name:'全站灰色',icon:'🕯️',color:'#888888',desc:'纪念 / 悼念模式',params:[]},
  contextmenu:{name:'右键美化',icon:'🖱️',color:'#2ECC71',desc:'自定义右键菜单',params:[]},
  nosource:{name:'禁止查看源码',icon:'🔒',color:'#E74C3C',desc:'禁用 F12 / 右键查看源码',params:[]},
  bgmusic:{name:'背景音乐',icon:'🎵',color:'#9B59B6',desc:'网站背景音乐播放',params:[{key:'volume',label:'音量',min:5,max:100,default:30}],extra:'music_url'},
  welcome:{name:'节日欢迎弹窗',icon:'🎉',color:'#FF6B6B',desc:'节日自动弹窗祝福',params:[],extra:'festival'},
}

const FESTIVALS:FestivalDef[]=[
  {id:'newyear',name:'元旦',date:'01-01',greeting:'新年快乐！愿新的一年万事如意，阖家幸福！🎊'},
  {id:'valentine',name:'情人节',date:'02-14',greeting:'情人节快乐！愿有情人终成眷属！💕'},
  {id:'women',name:'妇女节',date:'03-08',greeting:'妇女节快乐！致敬每一位伟大的女性！🌷'},
  {id:'arbor',name:'植树节',date:'03-12',greeting:'植树节快乐！让我们一起守护绿色家园！🌳'},
  {id:'fool',name:'愚人节',date:'04-01',greeting:'愚人节快乐！今天的玩笑要适可而止哦！😄'},
  {id:'qingming',name:'清明节',date:'04-05',greeting:'清明时节，缅怀先人，珍惜当下。🕊️'},
  {id:'labor',name:'劳动节',date:'05-01',greeting:'劳动节快乐！向每一位劳动者致敬！💪'},
  {id:'youth',name:'青年节',date:'05-04',greeting:'五四青年节快乐！青春正当时，奋斗不止步！🔥'},
  {id:'mother',name:'母亲节',date:'05-second-sun',greeting:'母亲节快乐！感恩母亲的无私奉献！❤️',dynamic:true},
  {id:'children',name:'儿童节',date:'06-01',greeting:'六一儿童节快乐！愿每个人心中都住着一个快乐的孩子！🎈'},
  {id:'dragon',name:'端午节',date:'lunar-05-05',greeting:'端午节安康！粽叶飘香，龙舟竞渡！🐉',lunar:true},
  {id:'cpc',name:'建党节',date:'07-01',greeting:'七一建党节，不忘初心，牢记使命！🇨🇳'},
  {id:'army',name:'建军节',date:'08-01',greeting:'八一建军节，致敬最可爱的人！🎖️'},
  {id:'qixi',name:'七夕节',date:'lunar-07-07',greeting:'七夕节快乐！愿天下有情人终成眷属！🌹',lunar:true},
  {id:'teacher',name:'教师节',date:'09-10',greeting:'教师节快乐！感恩师恩，桃李满天下！📚'},
  {id:'mid_autumn',name:'中秋节',date:'lunar-08-15',greeting:'中秋节快乐！月圆人团圆，幸福美满！🥮🌕',lunar:true},
  {id:'national',name:'国庆节',date:'10-01',greeting:'国庆节快乐！祝伟大祖国繁荣昌盛！🇨🇳🎆'},
  {id:'chongyang',name:'重阳节',date:'lunar-09-09',greeting:'重阳节快乐！敬老爱老，登高望远！🏔️',lunar:true},
  {id:'spring',name:'春节',date:'lunar-01-01',greeting:'新春快乐！恭喜发财，大吉大利！🧧🎆',lunar:true},
  {id:'lantern_fest',name:'元宵节',date:'lunar-01-15',greeting:'元宵节快乐！花灯璀璨，团团圆圆！🏮🎊',lunar:true},
  {id:'christmas',name:'圣诞节',date:'12-25',greeting:'圣诞快乐！Merry Christmas！🎄🎅'},
  {id:'nye',name:'除夕',date:'lunar-12-30',greeting:'除夕夜快乐！辞旧迎新，阖家团圆！🎇',lunar:true},
]

const effects=reactive<Record<string,EffectConfig>>({})
const activeEffects=computed(()=>Object.keys(EFFECTS).filter(id=>effects[id]?.enabled))

function normalizeEffect(id:string,raw:any={}):EffectConfig{
  const def=EFFECTS[id]
  const cfg:EffectConfig={enabled:!!raw?.enabled,params:{}}
  for(const p of def.params) cfg.params[p.key]=Number(raw?.params?.[p.key]??p.default)
  if(def.extra==='music_url') cfg.music_url=String(raw?.music_url??'')
  if(def.extra==='festival'){
    cfg.festivals={}
    for(const f of FESTIVALS){
      const old=raw?.festivals?.[f.id]
      cfg.festivals[f.id]={enabled:old?.enabled!==false,greeting:String(old?.greeting??f.greeting)}
    }
  }
  return cfg
}

function resetEffects(raw:any={}){
  for(const key of Object.keys(effects)) delete effects[key]
  for(const id of Object.keys(EFFECTS)) effects[id]=normalizeEffect(id,raw?.[id])
}

async function load(){
  loading.value=true
  try{
    const [codeData,settings]:any=await Promise.all([get('/admin/api/custom-code.php'),get('/admin/api/settings.php')])
    Object.keys(codes).forEach(k=>codes[k]=String(codeData?.[k]??''))
    let parsed:any={}
    try{parsed=settings?.effects_config?JSON.parse(String(settings.effects_config)): {}}catch{store.notify('原特效配置 JSON 无法解析，已载入默认配置；保存前请确认。','error')}
    resetEffects(parsed)
  }catch(e:any){store.notify(e?.message||'自定义配置加载失败','error')}
  finally{loading.value=false}
}

async function save(position:string){
  saving.value=position
  try{await post('/admin/api/custom-code.php',{position,code:codes[position]||''});store.notify(`${labels[position][0]} 已保存`)}
  catch(e:any){store.notify(e?.message||'保存失败','error')}
  finally{saving.value=''}
}

async function saveEffects(){
  savingEffects.value=true
  try{await post('/admin/api/settings.php',{settings:{effects_config:JSON.stringify(effects)}});store.notify('内置特效设置已保存')}
  catch(e:any){store.notify(e?.message||'特效保存失败','error')}
  finally{savingEffects.value=false}
}

async function toggleEffect(id:string){effects[id].enabled=!effects[id].enabled;await saveEffects()}
function toggleAllFestivals(){
  const rows=effects.welcome?.festivals||{}
  const all=FESTIVALS.every(f=>rows[f.id]?.enabled)
  FESTIVALS.forEach(f=>{if(rows[f.id])rows[f.id].enabled=!all})
}

onMounted(load)
</script>

<template><div>
<div class="page-head"><div><h1>自定义代码</h1><p>恢复完整的内置美化特效管理，同时保留 Head / Footer CSS 与 JavaScript 高级自定义能力。</p></div><button class="button" @click="load"><RefreshCw :size="14"/>重新读取</button></div>
<div class="card" style="margin-bottom:13px;border-color:color-mix(in srgb,var(--warning) 42%,var(--border))"><div style="display:flex;gap:9px;align-items:flex-start"><AlertTriangle :size="16" style="margin-top:2px;flex:none"/><div><b>自定义代码会直接作用于公开分发页</b><p class="subtle" style="margin:4px 0 0">错误的 CSS / JavaScript 可能影响页面显示；内置特效配置与下方代码分开保存。</p></div></div></div>
<div v-if="loading" class="card"><div class="skeleton" style="height:420px"></div></div>
<template v-else>
<section class="panel" style="margin-bottom:13px"><div class="panel-head"><div><h3><Sparkles :size="14" style="display:inline;vertical-align:-2px;margin-right:5px"/>内置美化特效</h3><span>点击卡片启用 / 禁用；启用后可继续调整参数</span></div><button class="button small primary" :disabled="savingEffects" @click="saveEffects"><Save :size="12"/>{{savingEffects?'保存中…':'保存特效设置'}}</button></div>
 <div style="padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:9px">
  <button v-for="(ef,id) in EFFECTS" :key="id" type="button" class="card" @click="toggleEffect(String(id))" :style="{padding:'14px',cursor:'pointer',textAlign:'left',borderColor:effects[String(id)]?.enabled?ef.color:'var(--border)',background:effects[String(id)]?.enabled?ef.color+'12':'var(--surface)'}">
   <div style="display:flex;align-items:center;justify-content:space-between;gap:8px"><span style="font-size:24px">{{ef.icon}}</span><span class="badge" :style="effects[String(id)]?.enabled?{borderColor:ef.color,color:ef.color}:{}">{{effects[String(id)]?.enabled?'已启用':'未启用'}}</span></div>
   <b style="display:block;margin-top:8px">{{ef.name}}</b><span class="subtle" style="display:block;margin-top:3px;font-size:12px">{{ef.desc}}</span>
  </button>
 </div>
</section>

<section v-if="activeEffects.length" class="form-card" style="margin-bottom:13px"><div class="form-section"><h3>特效参数</h3><p>参数修改后点击“保存特效设置”。启用 / 禁用操作会立即保存。</p>
 <div v-for="id in activeEffects" :key="id" class="card" style="margin-top:10px;padding:14px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px"><span style="font-size:21px">{{EFFECTS[id].icon}}</span><b>{{EFFECTS[id].name}}</b></div>
  <div v-for="p in EFFECTS[id].params" :key="p.key" style="display:grid;grid-template-columns:90px 1fr 44px;gap:10px;align-items:center;margin:9px 0"><label>{{p.label}}</label><input v-model.number="effects[id].params[p.key]" type="range" :min="p.min" :max="p.max" style="width:100%;accent-color:var(--primary)"><code style="text-align:right">{{effects[id].params[p.key]}}</code></div>
  <div v-if="EFFECTS[id].extra==='music_url'" class="field" style="margin-top:10px"><label>音乐链接</label><input v-model="effects[id].music_url" class="input" placeholder="https://example.com/music.mp3"></div>
  <template v-if="EFFECTS[id].extra==='festival'">
   <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px"><b style="font-size:13px">触发节日</b><button class="button small" type="button" @click="toggleAllFestivals">全选 / 取消</button></div>
   <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:7px;margin-top:8px"><label v-for="f in FESTIVALS" :key="f.id" class="card" style="padding:8px 10px;display:flex;gap:7px;align-items:center;cursor:pointer"><input v-model="effects[id].festivals![f.id].enabled" type="checkbox"><span>{{f.name}}<small v-if="f.lunar" class="subtle">（农历）</small></span></label></div>
   <details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600">编辑各节日祝福语</summary><div style="display:grid;gap:7px;margin-top:9px"><div v-for="f in FESTIVALS" :key="f.id" class="field"><label>{{f.name}}</label><input v-model="effects[id].festivals![f.id].greeting" class="input"></div></div></details>
  </template>
 </div>
 <button class="button primary" style="margin-top:12px" :disabled="savingEffects" @click="saveEffects"><Save :size="13"/>{{savingEffects?'保存中…':'保存特效设置'}}</button>
</div></section>

<div class="two-col">
 <section v-for="(info,key) in labels" :key="String(key)" class="form-card"><div class="panel-head"><div><h3><Code2 :size="13" style="display:inline;vertical-align:-2px;margin-right:5px"/>{{info[0]}}</h3><span>{{info[1]}}</span></div><button class="button small primary" :disabled="saving===key" @click="save(String(key))"><Save :size="12"/>保存</button></div><div style="padding:12px"><textarea v-model="codes[String(key)]" class="textarea" spellcheck="false" style="min-height:330px;font:10.5px/1.6 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace"></textarea></div></section>
</div>
</template>
</div></template>
