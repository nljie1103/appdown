#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def write(path, content):
    p = ROOT / path
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content, encoding='utf-8')

def replace_once(path, old, new):
    src = read(path)
    if old not in src:
        raise SystemExit(f'pattern not found in {path}: {old[:120]!r}')
    write(path, src.replace(old, new, 1))

version_src = read('includes/version.php')
edition_match = re.search(r"APPDOWN_EDITION',\s*'([^']+)'", version_src)
edition = edition_match.group(1) if edition_match else 'main'
release_tag = 'saas-v1.3.1' if edition == 'saas' else 'v1.3.1'
write('includes/version.php', "<?php\n// AppDown release identity. Online updater uses these constants to select the correct release channel.\ndefine('APPDOWN_EDITION', '%s');\ndefine('APPDOWN_VERSION', '1.3.1');\ndefine('APPDOWN_RELEASE_TAG', '%s');\ndefine('APPDOWN_GITHUB_REPO', 'nljie1103/appdown');\n" % (edition, release_tag))

custom_code = r'''<script setup lang="ts">
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
'''
write('admin-ui/src/views/CustomCodeView.vue', custom_code)

replace_once('admin-ui/src/views/SettingsView.vue', '<div class="setting-row"><div class="setting-copy"><b>效果配置 JSON</b><span>保留现有粒子/动态特效配置，保存前会校验 JSON。</span></div><textarea v-model="form.effects_config" class="textarea" style="min-height:150px"></textarea></div>', '<div class="setting-row"><div class="setting-copy"><b>内置美化特效</b><span>樱花、雪花、粒子、背景音乐、节日欢迎等已回到“自定义代码”可视化管理。</span></div><button class="button" type="button" @click="router.push(\'/custom-code\')">管理内置特效</button></div>')

replace_once('admin-ui/src/views/BuilderView.vue', '<div class="field" style="margin-top:9px"><label>图标 URL</label><input v-model="apk.icon_url" class="input"></div>', '<div class="field" style="margin-top:9px"><label>图标 URL（可选）</label><input v-model="apk.icon_url" class="input"><small>留空时使用 AppDown 内置默认图标，构建不会因缺少 launcher resource 失败。</small></div>')
replace_once('admin-ui/src/views/BuilderView.vue', '<div class="field" style="margin-top:9px"><label>图标 URL</label><input v-model="ipa.icon_url" class="input"></div>', '<div class="field" style="margin-top:9px"><label>图标 URL（可选）</label><input v-model="ipa.icon_url" class="input"></div>')
replace_once('admin-ui/src/views/BuilderView.vue', '<div class="form-section" v-else>\n   <div class="field"><label>目标 URL</label>', '<div class="form-section" v-else>\n   <div class="card" style="padding:10px 12px;margin-bottom:10px"><b>当前生成未签名 IPA</b><div class="subtle" style="margin-top:3px">Builder 会调用真实 xcodebuild 归档并打包 Payload，但不会伪装成已签名可直接安装的 IPA；签名需在后续签名流程完成。</div></div>\n   <div class="field"><label>目标 URL</label>')

# Android template always has a valid launcher resource; custom PNG densities generated by the worker override it.
write('android-template/app/src/main/res/mipmap/ic_launcher.xml', '''<?xml version="1.0" encoding="utf-8"?>\n<vector xmlns:android="http://schemas.android.com/apk/res/android" android:width="108dp" android:height="108dp" android:viewportWidth="108" android:viewportHeight="108">\n    <path android:fillColor="#2563EB" android:pathData="M0,0 L108,0 L108,108 L0,108 Z"/>\n    <path android:fillColor="#FFFFFF" android:pathData="M48,23 L60,23 L60,51 L74,51 L54,75 L34,51 L48,51 Z"/>\n    <path android:fillColor="#FFFFFF" android:pathData="M30,81 L78,81 L78,89 L30,89 Z"/>\n</vector>\n''')

# System API: central SSH command, architecture check, Xcode preflight, configurable Xcode version.
system_path='admin/api/system.php'
s=read(system_path)
anchor="$action = $_GET['action'] ?? '';\n"
helper=r'''

function ios_ssh_command(PDO $pdo, string $remoteCommand, int $timeout = 5): string {
    $port = (int)(get_setting($pdo, 'custom_ios_ssh_port') ?: '50922');
    if ($port < 1 || $port > 65535) $port = 50922;
    $root = dirname(__DIR__, 2);
    $knownHosts = $root . '/data/ios_known_hosts';
    if (!file_exists($knownHosts)) { @touch($knownHosts); @chmod($knownHosts, 0600); }
    $identity = $root . '/data/ios_builder_ed25519';
    $opts = '-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=' . escapeshellarg($knownHosts)
        . ' -o ConnectTimeout=' . max(1, $timeout) . ' -o BatchMode=yes -p ' . $port;
    if (is_file($identity) && is_readable($identity)) $opts .= ' -i ' . escapeshellarg($identity) . ' -o IdentitiesOnly=yes';
    return 'ssh ' . $opts . ' ' . escapeshellarg('user@localhost') . ' ' . escapeshellarg($remoteCommand);
}
'''
if 'function ios_ssh_command' not in s:
    s=s.replace(anchor,anchor+helper,1)
s=s.replace("        // Docker 已安装\n        $dockerOut = [];", "        // Docker-OSX 仅支持 x86_64 KVM 宿主机\n        $arch = strtolower((string)php_uname('m'));\n        $archOk = in_array($arch, ['x86_64', 'amd64'], true);\n\n        // Docker 已安装\n        $dockerOut = [];",1)
s=s.replace("@exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost \"echo ok\" 2>/dev/null', $sshOut);", "@exec(ios_ssh_command($pdo, 'echo ok', 5) . ' 2>/dev/null', $sshOut, $sshCode);",1)
s=s.replace("@exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost \"xcodebuild -version 2>/dev/null | head -1\" 2>/dev/null', $xcOut);", "@exec(ios_ssh_command($pdo, 'xcodebuild -version 2>/dev/null | head -1', 5) . ' 2>/dev/null', $xcOut, $xcCode);",1)
s=s.replace("        json_response([\n            'docker'", "        json_response([\n            'arch'              => ['ok' => $archOk, 'version' => $arch],\n            'docker'",1)
s=s.replace("'all_ok'            => $hasDocker && $dockerRunning && $hasKvm && $containerExists && $containerRunning && $sshOk && $hasXcode,", "'all_ok'            => $archOk && $hasDocker && $dockerRunning && $hasKvm && $containerExists && $containerRunning && $sshOk && $hasXcode,",1)
s=s.replace("@exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost \"xcodebuild -version 2>/dev/null\" 2>/dev/null', $xcOut, $xcCode);", "@exec(ios_ssh_command($pdo, 'xcodebuild -version 2>/dev/null', 10) . ' 2>/dev/null', $xcOut, $xcCode);",1)
needle="""        $input = json_decode(file_get_contents('php://input'), true);\n        $appleId = trim($input['apple_id'] ?? '');"""
replacement="""        $sshProbe = [];\n        @exec(ios_ssh_command($pdo, 'echo ok', 10) . ' 2>/dev/null', $sshProbe, $sshProbeCode);\n        if ($sshProbeCode !== 0 || trim($sshProbe[0] ?? '') !== 'ok') {\n            json_response(['error' => 'macOS 容器 SSH 尚未就绪，不能启动 Xcode 安装。请先让 iOS 环境检测中的 SSH 变为正常。'], 400);\n        }\n\n        $input = json_decode(file_get_contents('php://input'), true);\n        $appleId = trim($input['apple_id'] ?? '');"""
if needle not in s: raise SystemExit('system.php install xcode anchor missing')
s=s.replace(needle,replacement,1)
s=s.replace("'custom_docker_data_root', 'custom_docker_mirror', 'custom_docker_osx_image'];", "'custom_docker_data_root', 'custom_docker_mirror', 'custom_docker_osx_image', 'custom_xcode_version'];",1)
validation="""                // Xcode 版本只允许常见版本字符；留空表示自动选择\n                if ($k === 'custom_xcode_version' && $val !== '') {\n                    if (strlen($val) > 40 || !preg_match('/^[0-9A-Za-z ._\\-]+$/', $val)) {\n                        json_response(['error' => 'Xcode 版本格式无效，例如 15.4 或 12.4'], 400);\n                    }\n                }\n"""
insert_before="                set_setting($pdo, $k, $val);\n"
if validation not in s:
    s=s.replace(insert_before,validation+insert_before,1)
write(system_path,s)

# Install worker creates a PHP-owned dedicated SSH identity, then passes it to the root setup script.
p='tools/install-ios-worker.php'; s=read(p)
anchor="$pdo = get_db();\n"
sshprep=r'''

$dataDir = realpath(__DIR__ . '/../data');
$sshKey = $dataDir ? ($dataDir . '/ios_builder_ed25519') : '';
if ($sshKey && !file_exists($sshKey)) {
    $keyCmd = 'ssh-keygen -q -t ed25519 -N ' . escapeshellarg('') . ' -f ' . escapeshellarg($sshKey) . ' 2>&1';
    exec($keyCmd, $keyOut, $keyCode);
    if ($keyCode !== 0) {
        file_put_contents($logFile, "[错误] 无法创建 iOS Builder SSH 密钥: " . implode("\n", $keyOut) . "\n");
        exit(1);
    }
    @chmod($sshKey, 0600);
    @chmod($sshKey . '.pub', 0644);
}
'''
if '$sshKey = $dataDir' not in s: s=s.replace(anchor,anchor+sshprep,1)
s=s.replace("    $envPrefix = $envParts ? implode(' ', $envParts) . ' ' : '';", "    if ($sshKey) $envParts[] = 'IOS_SSH_KEY=' . escapeshellarg($sshKey);\n    $envPrefix = $envParts ? implode(' ', $envParts) . ' ' : '';",1)
write(p,s)

# Honest Docker-OSX Phase 1: x86_64/KVM required, loopback SSH, auto-image key bootstrap, SSH failure is a real failure.
setup=r'''#!/bin/bash
set -euo pipefail

DOCKER_OSX_IMAGE="${DOCKER_OSX_IMAGE:-sickcodes/docker-osx:auto}"
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
SSH_PORT="${SSH_PORT:-50922}"
DOCKER_DATA_ROOT="${DOCKER_DATA_ROOT:-}"
DOCKER_MIRROR="${DOCKER_MIRROR:-}"
IOS_SSH_KEY="${IOS_SSH_KEY:-}"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log(){ echo -e "${GREEN}[✓]${NC} $1"; }
warn(){ echo -e "${YELLOW}[!]${NC} $1"; }
error(){ echo -e "${RED}[✗]${NC} $1"; }

[ "$(id -u)" -eq 0 ] || { error "请使用 sudo 运行此脚本"; exit 1; }
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
[ -n "$IOS_SSH_KEY" ] || IOS_SSH_KEY="$PROJECT_DIR/data/ios_builder_ed25519"

ARCH="$(uname -m | tr '[:upper:]' '[:lower:]')"
if [ "$ARCH" != "x86_64" ] && [ "$ARCH" != "amd64" ]; then
  error "Docker-OSX 需要 x86_64 KVM 宿主机，当前架构: $ARCH"
  echo "ARM/aarch64 服务器不能运行这条 Docker-OSX 构建路线。"
  exit 1
fi
[ -e /dev/kvm ] || { error "KVM 不可用（缺少 /dev/kvm）"; exit 1; }

if ! command -v docker >/dev/null 2>&1; then
  log "安装 Docker CE ..."
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg >/dev/null
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  . /etc/os-release
  ARCH_DPKG="$(dpkg --print-architecture)"
  echo "deb [arch=$ARCH_DPKG signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" >/etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1 || true
docker info >/dev/null 2>&1 || { error "Docker daemon 未运行"; exit 1; }
log "Docker: $(docker --version | head -1)"

if [ -n "$DOCKER_DATA_ROOT" ] || [ -n "$DOCKER_MIRROR" ]; then
  python3 - "$DOCKER_DATA_ROOT" "$DOCKER_MIRROR" <<'PY'
import json,sys,os
p='/etc/docker/daemon.json'; cfg={}
if os.path.exists(p):
    try: cfg=json.load(open(p))
    except Exception: cfg={}
if sys.argv[1]: os.makedirs(sys.argv[1],exist_ok=True); cfg['data-root']=sys.argv[1]
if sys.argv[2]: cfg['registry-mirrors']=[x.strip() for x in sys.argv[2].split(',') if x.strip()]
os.makedirs('/etc/docker',exist_ok=True)
json.dump(cfg,open(p,'w'),indent=2)
PY
  systemctl restart docker
fi

if [[ "$DOCKER_OSX_IMAGE" == *":auto" ]]; then
  apt-get update -qq
  apt-get install -y -qq openssh-client sshpass >/dev/null
  warn "使用 Docker-OSX :auto 预制 Catalina CLI 镜像；如需现代 Xcode，请改用已经完成 macOS 安装并启用 SSH 的较新自定义镜像。"
else
  apt-get update -qq
  apt-get install -y -qq openssh-client >/dev/null
  warn "非 :auto Docker-OSX 镜像通常需要先完成 macOS 初始安装并启用 SSH；AppDown 只有在 SSH 真正可达后才会标记 Phase 1 成功。"
fi

if ! docker image inspect "$DOCKER_OSX_IMAGE" >/dev/null 2>&1; then
  log "拉取 $DOCKER_OSX_IMAGE ..."
  docker pull "$DOCKER_OSX_IMAGE"
fi

if docker ps -a --format '{{.Names}}' | grep -qx "$CONTAINER_NAME"; then
  docker start "$CONTAINER_NAME" >/dev/null 2>&1 || true
  log "复用已有容器 $CONTAINER_NAME"
else
  log "创建 macOS 容器 $CONTAINER_NAME"
  docker run -d --name "$CONTAINER_NAME" --device /dev/kvm \
    -p "127.0.0.1:${SSH_PORT}:10022" \
    -e RAM=8 -e NOPICKER=true -e GENERATE_UNIQUE=true \
    "$DOCKER_OSX_IMAGE" >/dev/null
fi

docker ps --format '{{.Names}}' | grep -qx "$CONTAINER_NAME" || { error "macOS 容器未运行"; exit 1; }

ssh_key_probe(){
  [ -f "$IOS_SSH_KEY" ] || return 1
  ssh -i "$IOS_SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o BatchMode=yes -p "$SSH_PORT" user@localhost 'echo ok' 2>/dev/null | grep -qx ok
}
ssh_auto_probe(){
  command -v sshpass >/dev/null 2>&1 || return 1
  sshpass -p alpine ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o PreferredAuthentications=password -o PubkeyAuthentication=no -p "$SSH_PORT" user@localhost 'echo ok' 2>/dev/null | grep -qx ok
}

log "等待 macOS SSH 真正就绪 ..."
SSH_OK=false
for _ in $(seq 1 60); do
  if ssh_key_probe; then SSH_OK=true; break; fi
  if [[ "$DOCKER_OSX_IMAGE" == *":auto" ]] && ssh_auto_probe; then
    if [ ! -f "$IOS_SSH_KEY" ]; then
      mkdir -p "$(dirname "$IOS_SSH_KEY")"
      ssh-keygen -q -t ed25519 -N '' -f "$IOS_SSH_KEY"
      chmod 600 "$IOS_SSH_KEY"
    fi
    PUBKEY="$(cat "${IOS_SSH_KEY}.pub")"
    sshpass -p alpine ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -p "$SSH_PORT" user@localhost \
      "umask 077; mkdir -p ~/.ssh; touch ~/.ssh/authorized_keys; grep -qxF '$PUBKEY' ~/.ssh/authorized_keys || printf '%s\\n' '$PUBKEY' >> ~/.ssh/authorized_keys; chmod 700 ~/.ssh; chmod 600 ~/.ssh/authorized_keys" >/dev/null 2>&1 || true
    if ssh_key_probe; then SSH_OK=true; break; fi
  fi
  sleep 10
done

if [ "$SSH_OK" != true ]; then
  error "macOS SSH 在 10 分钟内未就绪，Phase 1 判定失败。"
  echo "容器已创建/启动，但 AppDown 不会再把‘容器存在’误报为‘iOS 环境完成’。"
  echo "请完成 macOS 初始安装、开启 Remote Login，并确保 user 可使用 AppDown 专用 SSH key 后重试。"
  exit 1
fi

MACOS_VER="$(ssh -i "$IOS_SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o BatchMode=yes -p "$SSH_PORT" user@localhost 'sw_vers -productVersion' 2>/dev/null | head -1 || true)"
log "SSH ................. OK"
log "macOS ............... ${MACOS_VER:-已连接}"
log "Phase 1 安装完成：Docker、KVM、容器、SSH 全部真实通过"
'''
write('tools/setup-ios-env.sh',setup)

uninstall=r'''#!/bin/bash
set -euo pipefail
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
DOCKER_OSX_IMAGE="${DOCKER_OSX_IMAGE:-sickcodes/docker-osx:auto}"
REMOVE_DOCKER_OSX_IMAGE="${REMOVE_DOCKER_OSX_IMAGE:-0}"
[ "$(id -u)" -eq 0 ] || { echo "请使用 sudo 运行"; exit 1; }
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"; PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qx "$CONTAINER_NAME"; then docker rm -f "$CONTAINER_NAME" >/dev/null; fi
rm -rf "$PROJECT_DIR/data/ios-build"
rm -f "$PROJECT_DIR/data/ios_known_hosts"
if [ "$REMOVE_DOCKER_OSX_IMAGE" = "1" ] && docker image inspect "$DOCKER_OSX_IMAGE" >/dev/null 2>&1; then docker rmi "$DOCKER_OSX_IMAGE" || true; fi
echo "iOS Builder 容器与临时构建数据已清理。Docker 本身和镜像默认保留，避免误删其他服务共享资源。"
'''
write('tools/uninstall-ios-env.sh',uninstall)

# Xcode worker: dynamic SSH settings/key, configurable version, Catalina fallback, no false success.
p='tools/xcode-install-worker.php'; s=read(p)
old="""// SSH 配置\n$SSH_PORT = 50922;\n$SSH_HOST = 'localhost';\n$SSH_USER = 'user';\n$SSH_OPTS = \"-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p $SSH_PORT\";\n\n// 2FA IPC 文件路径\n$dataDir = realpath(__DIR__ . '/../data');"""
new="""// SSH 配置：与后台自定义端口一致，并复用 AppDown 专用 identity/known_hosts。\n$SSH_PORT = (int)(get_setting($pdo, 'custom_ios_ssh_port') ?: '50922');\nif ($SSH_PORT < 1 || $SSH_PORT > 65535) $SSH_PORT = 50922;\n$SSH_HOST = 'localhost';\n$SSH_USER = 'user';\n$dataDir = realpath(__DIR__ . '/../data');\n$knownHosts = $dataDir . '/ios_known_hosts';\nif (!file_exists($knownHosts)) { @touch($knownHosts); @chmod($knownHosts, 0600); }\n$identity = $dataDir . '/ios_builder_ed25519';\n$SSH_OPTS = '-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=' . escapeshellarg($knownHosts) . ' -o ConnectTimeout=10 -o BatchMode=yes -p ' . $SSH_PORT;\nif (is_file($identity) && is_readable($identity)) $SSH_OPTS .= ' -i ' . escapeshellarg($identity) . ' -o IdentitiesOnly=yes';\n\n// 2FA IPC 文件路径"""
if old not in s: raise SystemExit('xcode ssh block missing')
s=s.replace(old,new,1)
old_remote="""    // 构建远程命令：通过环境变量传入凭据\n    $remoteCmd = sprintf(\n        'export XCODES_USERNAME=%s XCODES_PASSWORD=%s; xcodes install --latest --no-superuser 2>&1',\n        escapeshellarg($appleId),\n        escapeshellarg($password)\n    );"""
new_remote="""    // 留空时自动选择：预制 Catalina 使用 Apple 官方仍支持的 Xcode 12.4；较新 macOS 使用 latest。\n    $requestedVersion = trim((string)get_setting($pdo, 'custom_xcode_version'));\n    if ($requestedVersion === '') {\n        $macResult = ssh_exec_simple('sw_vers -productVersion 2>/dev/null');\n        $macVersion = trim($macResult['output'][0] ?? '');\n        if ($macVersion !== '' && version_compare($macVersion, '11.0', '<')) {\n            $requestedVersion = '12.4';\n            log_msg('检测到 macOS ' . $macVersion . '，自动选择 Xcode 12.4。');\n        }\n    }\n    $installTarget = $requestedVersion !== '' ? escapeshellarg($requestedVersion) : '--latest';\n    log_msg('目标 Xcode: ' . ($requestedVersion !== '' ? $requestedVersion : 'latest'));\n\n    // 构建远程命令：通过环境变量传入凭据\n    $remoteCmd = sprintf(\n        'export XCODES_USERNAME=%s XCODES_PASSWORD=%s; xcodes install %s --no-superuser 2>&1',\n        escapeshellarg($appleId),\n        escapeshellarg($password),\n        $installTarget\n    );"""
if old_remote not in s: raise SystemExit('xcode install block missing')
s=s.replace(old_remote,new_remote,1)
s=s.replace("        log_msg(\"警告: 许可协议接受可能失败，但不影响后续使用\");", "        log_msg(\"警告: 自动接受许可失败；Xcode 只有在后续 xcodebuild -version 与真实构建通过后才会被视为就绪。\");",1)
write(p,s)

# iOS build worker: use dedicated identity and SCP instead of the non-existent macOS /mnt/build shared-folder assumption.
p='tools/ios-build-worker.php'; s=read(p)
s=s.replace("$SSH_OPTS = '-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=' . escapeshellarg($knownHosts) .\n    ' -o ConnectTimeout=10 -o BatchMode=yes -p ' . (int)$SSH_PORT;", "$identity = $projectRoot . '/data/ios_builder_ed25519';\n$SSH_OPTS = '-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=' . escapeshellarg($knownHosts) .\n    ' -o ConnectTimeout=10 -o BatchMode=yes -p ' . (int)$SSH_PORT;\nif (is_file($identity) && is_readable($identity)) $SSH_OPTS .= ' -i ' . escapeshellarg($identity) . ' -o IdentitiesOnly=yes';\n$SCP_OPTS = '-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=' . escapeshellarg($knownHosts) .\n    ' -o ConnectTimeout=10 -o BatchMode=yes -P ' . (int)$SSH_PORT;\nif (is_file($identity) && is_readable($identity)) $SCP_OPTS .= ' -i ' . escapeshellarg($identity) . ' -o IdentitiesOnly=yes';",1)
func_anchor="""function ssh_exec(string $command): array {\n    global $SSH_OPTS, $SSH_USER, $SSH_HOST;\n    $fullCmd = 'ssh ' . $SSH_OPTS . ' ' . escapeshellarg($SSH_USER . '@' . $SSH_HOST) . ' ' . escapeshellarg($command) . ' 2>&1';\n    $output = [];\n    exec($fullCmd, $output, $retCode);\n    return ['output' => $output, 'code' => $retCode];\n}\n"""
scp_funcs=func_anchor+"""\nfunction scp_to_remote(string $localDir, string $remoteDir): array {\n    global $SCP_OPTS, $SSH_USER, $SSH_HOST;\n    $prep = ssh_exec('rm -rf ' . escapeshellarg($remoteDir) . ' && mkdir -p ' . escapeshellarg($remoteDir));\n    if ($prep['code'] !== 0) return $prep;\n    $cmd = 'scp ' . $SCP_OPTS . ' -r ' . escapeshellarg(rtrim($localDir, '/') . '/.') . ' ' . escapeshellarg($SSH_USER . '@' . $SSH_HOST . ':' . $remoteDir . '/') . ' 2>&1';\n    $out=[]; exec($cmd,$out,$code); return ['output'=>$out,'code'=>$code];\n}\n\nfunction scp_from_remote(string $remoteFile, string $localFile): array {\n    global $SCP_OPTS, $SSH_USER, $SSH_HOST;\n    $cmd = 'scp ' . $SCP_OPTS . ' ' . escapeshellarg($SSH_USER . '@' . $SSH_HOST . ':' . $remoteFile) . ' ' . escapeshellarg($localFile) . ' 2>&1';\n    $out=[]; exec($cmd,$out,$code); return ['output'=>$out,'code'=>$code];\n}\n"""
if func_anchor not in s: raise SystemExit('ios build ssh func anchor missing')
s=s.replace(func_anchor,scp_funcs,1)
s=s.replace("    $remoteBuildDir = '/mnt/build/task_' . $taskId;", "    $remoteBuildDir = '/tmp/appdown-build-' . $taskId;",1)
compile_anchor="""    update_task($pdo, $taskId, ['progress' => 30, 'progress_msg' => '正在编译IPA（可能需要几分钟）...']);\n    $xcodeCmd = \"cd $remoteBuildDir && \" ."""
compile_repl="""    update_task($pdo, $taskId, ['progress' => 28, 'progress_msg' => '通过 SSH 复制工程到 macOS...']);\n    $transfer = scp_to_remote($localBuildDir, $remoteBuildDir);\n    if ($transfer['code'] !== 0) {\n        fail_task($pdo, $taskId, \"工程传输到 macOS 失败\\n\" . implode(\"\\n\", $transfer['output']));\n        exit(1);\n    }\n\n    update_task($pdo, $taskId, ['progress' => 30, 'progress_msg' => '正在编译未签名 IPA（可能需要几分钟）...']);\n    $xcodeCmd = \"cd $remoteBuildDir && \" ."""
if compile_anchor not in s: raise SystemExit('ios build compile anchor missing')
s=s.replace(compile_anchor,compile_repl,1)
old_copy="""    $localIpaPath = $localBuildDir . '/build/app.ipa';\n    $destPath = $ipaDir . '/' . $ipaFilename;\n    if (!file_exists($localIpaPath)) {\n        fail_task($pdo, $taskId, 'IPA 文件未找到（共享卷同步可能延迟）');\n        exit(1);\n    }"""
new_copy="""    $localIpaPath = $localBuildDir . '/build/app.ipa';\n    if (!is_dir(dirname($localIpaPath))) mkdir(dirname($localIpaPath), 0755, true);\n    $transferBack = scp_from_remote($remoteBuildDir . '/build/app.ipa', $localIpaPath);\n    if ($transferBack['code'] !== 0 || !is_file($localIpaPath)) {\n        fail_task($pdo, $taskId, \"从 macOS 拉取 IPA 失败\\n\" . implode(\"\\n\", $transferBack['output']));\n        exit(1);\n    }\n    $destPath = $ipaDir . '/' . $ipaFilename;"""
if old_copy not in s: raise SystemExit('ios copy block missing')
s=s.replace(old_copy,new_copy,1)
s=s.replace("if ($taskId) ssh_exec('rm -rf /mnt/build/task_' . (int)$taskId);", "if ($taskId) ssh_exec('rm -rf ' . escapeshellarg('/tmp/appdown-build-' . (int)$taskId));",1)
write(p,s)

# System view: show host architecture, auto image, Xcode version setting, and honest phase labels.
p='admin-ui/src/views/SystemView.vue'; s=read(p)
s=s.replace("custom_docker_osx_image:''})", "custom_docker_osx_image:'',custom_xcode_version:''})",1)
s=s.replace("const labels:Record<string,string>={java:'Java 17'", "const labels:Record<string,string>={arch:'CPU 架构',java:'Java 17'",1)
s=s.replace("<span>Docker · KVM · macOS Container · Xcode</span>", "<span>x86_64 · KVM · Docker · macOS SSH · Xcode</span>",1)
s=s.replace('placeholder="sickcodes/docker-osx:sonoma"', 'placeholder="sickcodes/docker-osx:auto"',1)
old="""<div class=\"field\"><label>SSH 端口</label><input v-model=\"env.custom_ios_ssh_port\" class=\"input\" placeholder=\"50922\"></div></div>"""
new="""<div class=\"field\"><label>SSH 端口</label><input v-model=\"env.custom_ios_ssh_port\" class=\"input\" placeholder=\"50922\"></div><div class=\"field\"><label>Xcode 版本</label><input v-model=\"env.custom_xcode_version\" class=\"input\" placeholder=\"留空自动；例如 15.4\"><small>Docker-OSX :auto 的 Catalina 留空时会自动选择 Xcode 12.4；较新 macOS 留空使用 latest。</small></div></div>"""
if old not in s: raise SystemExit('system view env anchor missing')
s=s.replace(old,new,1)
s=s.replace("<p>Apple ID 和密码只写入权限为 0600 的临时凭据文件，Worker 读取后删除；Vue 不写入站点设置。需要 2FA 时会自动显示验证码输入。</p>", "<p>只有 macOS SSH 已真实就绪时才能启动。Apple ID 和密码只写入 0600 临时文件，Worker 读取后删除；需要 2FA 时会显示验证码输入。Xcode 最终以 xcodebuild 实测结果为准。</p>",1)
write(p,s)

# Smoke tests now guard the exact regressions found in v1.3.0.
p='tests/smoke_admin2.php'; s=read(p)
insert="""\n$customCode = admin2_source($root, 'admin-ui/src/views/CustomCodeView.vue');\nadmin2_markers($customCode, [\n    '全屏樱花', '全屏雪花', '节日灯笼', '粒子背景', '鼠标跟随', '彩带背景',\n    '全站灰色', '右键美化', '禁止查看源码', '背景音乐', '节日欢迎弹窗',\n    'FESTIVALS', 'effects_config', '/admin/api/settings.php',\n], 'CustomCodeView');\n\n$androidDefaultIcon = admin2_source($root, 'android-template/app/src/main/res/mipmap/ic_launcher.xml');\nadmin2_markers($androidDefaultIcon, ['<vector', '#2563EB'], 'Android default launcher icon');\n\n$iosWorker = admin2_source($root, 'tools/ios-build-worker.php');\nadmin2_markers($iosWorker, ['scp_to_remote', 'scp_from_remote', '/tmp/appdown-build-', 'ios_builder_ed25519'], 'iOS build worker');\n\n$xcodeWorker = admin2_source($root, 'tools/xcode-install-worker.php');\nadmin2_markers($xcodeWorker, ['custom_ios_ssh_port', 'custom_xcode_version', 'ios_builder_ed25519', 'StrictHostKeyChecking=accept-new'], 'Xcode install worker');\n\n$iosSetup = admin2_source($root, 'tools/setup-ios-env.sh');\nadmin2_markers($iosSetup, ['x86_64', '127.0.0.1:${SSH_PORT}:10022', 'Phase 1 判定失败', 'sickcodes/docker-osx:auto'], 'iOS environment setup');\n"""
marker="$account = admin2_source($root, 'admin-ui/src/views/AccountView.vue');"
if insert.strip() not in s:
    s=s.replace(marker,insert+'\n'+marker,1)
write(p,s)

# Permanent real Android and real Xcode-template build gates.
p='.github/workflows/ci.yml'; s=read(p)
if 'real-android-apk:' not in s:
    s += r'''

  real-android-apk:
    runs-on: ubuntu-24.04
    steps:
      - name: Fetch source
        shell: bash
        run: |
          set -euo pipefail
          curl -fsSL --retry 3 "https://github.com/${GITHUB_REPOSITORY}/archive/${GITHUB_SHA}.tar.gz" -o /tmp/appdown.tar.gz
          mkdir -p "$GITHUB_WORKSPACE/src"
          tar -xzf /tmp/appdown.tar.gz --strip-components=1 -C "$GITHUB_WORKSPACE/src"
      - name: Install AppDown Android environment
        shell: bash
        working-directory: src
        run: sudo bash tools/setup-android-env.sh
      - name: Build and verify signed APK with no custom icon
        shell: bash
        working-directory: src/android-template
        run: |
          set -euo pipefail
          export JAVA_HOME="$(dirname "$(dirname "$(readlink -f "$(command -v java)")")")"
          export ANDROID_HOME="${ANDROID_HOME:-${ANDROID_SDK_ROOT:-/usr/local/lib/android/sdk}}"
          keytool -genkeypair -keystore /tmp/appdown-ci.jks -storepass appdown-ci-123 -keypass appdown-ci-123 -alias appdown -keyalg RSA -keysize 2048 -validity 3650 -dname "CN=AppDown CI,O=AppDown,C=US"
          export APPDOWN_KS_FILE=/tmp/appdown-ci.jks APPDOWN_KS_STORE_PASSWORD=appdown-ci-123 APPDOWN_KS_ALIAS=appdown APPDOWN_KS_KEY_PASSWORD=appdown-ci-123
          chmod +x gradlew
          ./gradlew assembleRelease -PappId=com.appdown.ci -PvName=1.3.1 -PvCode=131 --no-daemon
          APK=app/build/outputs/apk/release/app-release.apk
          test -s "$APK"
          "$ANDROID_HOME/build-tools/34.0.0/apksigner" verify --verbose "$APK"
          "$ANDROID_HOME/build-tools/34.0.0/aapt" dump badging "$APK" | grep -F "package: name='com.appdown.ci'"

  real-ios-template:
    runs-on: macos-latest
    steps:
      - name: Fetch source
        shell: bash
        run: |
          set -euo pipefail
          curl -fsSL --retry 3 "https://github.com/${GITHUB_REPOSITORY}/archive/${GITHUB_SHA}.tar.gz" -o /tmp/appdown.tar.gz
          mkdir -p "$GITHUB_WORKSPACE/src"
          tar -xzf /tmp/appdown.tar.gz --strip-components=1 -C "$GITHUB_WORKSPACE/src"
      - name: Archive real unsigned iOS app with Xcode
        shell: bash
        working-directory: src/ios-template
        run: |
          set -euo pipefail
          xcodebuild -version
          xcodebuild -project WebViewApp.xcodeproj -scheme WebViewApp -configuration Release -destination 'generic/platform=iOS' -archivePath /tmp/appdown.xcarchive archive CODE_SIGNING_ALLOWED=NO
          test -d /tmp/appdown.xcarchive/Products/Applications/WebViewApp.app
          rm -rf /tmp/appdown-ipa && mkdir -p /tmp/appdown-ipa/Payload
          cp -R /tmp/appdown.xcarchive/Products/Applications/WebViewApp.app /tmp/appdown-ipa/Payload/
          cd /tmp/appdown-ipa
          /usr/bin/zip -qry /tmp/appdown-ci.ipa Payload
          unzip -t /tmp/appdown-ci.ipa >/dev/null
'''
write(p,s)

# README: clarify real build status, host limits, unsigned IPA, and synchronized numeric version policy.
p='README.md'; s=read(p)
s=s.replace("- JDK / Android SDK 自动检测", "- JDK / Android SDK 自动检测\n- 模板自带默认 launcher icon；即使不上传自定义图标也可完成 Release APK 构建\n- 永久 CI 会真实运行 Android 环境脚本、Gradle、JKS 签名与 apksigner 验证",1)
s=s.replace("- Docker-OSX + macOS + Xcode 构建路线\n- 自定义 Bundle ID、版本号、图标\n- 无签名构建模式", "- Docker-OSX + macOS + Xcode 构建路线（仅 x86_64 KVM Linux 宿主机）\n- 工程通过 SSH/SCP 传入 macOS，再把真实 xcodebuild 产出的 IPA 拉回服务器\n- 自定义 Bundle ID、版本号、图标\n- 当前明确为无签名 IPA 构建模式，不把 unsigned IPA 伪装成可直接安装的签名包\n- 永久 CI 另在真实 macOS Runner 上执行 iOS 模板 xcodebuild archive",1)
old_ios="""需要：\n\n- Linux 宿主机\n- KVM\n- Docker\n- 可运行的 Docker-OSX/macOS\n- Xcode\n- 建议 ≥ 8GB 内存\n- 建议预留 ≥ 50GB 磁盘"""
new_ios="""需要：\n\n- **x86_64 / amd64 Linux 宿主机**（Docker-OSX 不支持 ARM/aarch64 作为这条 KVM 路线的宿主）\n- KVM（`/dev/kvm`）\n- Docker\n- 可启动并可通过 SSH 登录的 Docker-OSX/macOS\n- Xcode\n- 建议 ≥ 8GB 内存\n- 建议预留 ≥ 50GB 磁盘\n\n`tools/setup-ios-env.sh` 只有在 Docker、KVM、容器和 **macOS SSH 全部真实通过**后才返回成功；不会再因为“容器已经创建”就把 iOS 环境标成完成。默认 `sickcodes/docker-osx:auto` 是官方预制 Catalina CLI 镜像，适合验证自动化链路；现代 Xcode 应使用已经完成 macOS 安装并启用 SSH 的较新自定义镜像。"""
if old_ios not in s: raise SystemExit('README iOS requirements anchor missing')
s=s.replace(old_ios,new_ios,1)
branch_anchor="""| `main` | 单用户 / 单分发站版本 |\n| `saas` | 多租户版本：根欢迎页、`/super` 超级后台、`/用户名` 独立分发站 |"""
branch_repl=branch_anchor+"""\n\n从 **v1.3.1** 开始两条 Release 线使用相同数字版本：同一批功能/修复同时发布为 `vX.Y.Z` 与 `saas-vX.Y.Z`。例如本次为 `v1.3.1` / `saas-v1.3.1`；tag 前缀继续区分 edition，数字版本保持同步。"""
if branch_anchor not in s: raise SystemExit('README branch anchor missing')
s=s.replace(branch_anchor,branch_repl,1)
write(p,s)

# Changelog prepend, edition-aware wording.
p='CHANGELOG.md'; s=read(p)
if '## v1.3.1 - 2026-08-12' not in s and '## saas-v1.3.1 - 2026-08-12' not in s:
    heading='saas-v1.3.1' if edition=='saas' else 'v1.3.1'
    section=f'''# Changelog\n\n## {heading} - 2026-08-12\n\n已知问题修复与真实构建链验证版本。\n\n- 恢复 Admin 2.0 “自定义代码”中遗漏的完整可视化内置特效：樱花、雪花、灯笼、粒子、鼠标跟随、彩带、灰色模式、右键美化、源码限制、背景音乐、节日欢迎及节日祝福编辑。\n- Android 模板新增默认 launcher icon，未填写自定义图标也可构建；永久 CI 真实执行环境安装、Gradle Release、JKS 签名、apksigner 与 aapt 验证。\n- iOS Builder 改为 SSH/SCP 传输工程与 IPA，不再依赖 macOS 实际无法保证存在的 `/mnt/build` 共享目录。\n- iOS Phase 1 增加 x86_64/KVM 检测、loopback SSH、专用 SSH identity；SSH 未就绪时安装任务明确失败，不再出现假阳性 done。\n- Xcode Worker 使用后台配置的 SSH 端口、known_hosts 与 identity，并新增可选 Xcode 版本；Xcode 是否就绪最终以 `xcodebuild` 实测为准。\n- Builder 明确标注当前 IPA 为 unsigned；永久 CI 在真实 macOS Runner 上执行 iOS 模板 `xcodebuild archive`。\n- iOS 卸载脚本尊重自定义容器/镜像参数，默认不删除 Docker 镜像，避免误伤共享资源。\n- 从本版本开始 `main` 与 `saas` Release 数字版本统一：`v1.3.1` / `saas-v1.3.1`，后续同批发布继续保持相同 X.Y.Z。\n\n'''
    s=section+s[len('# Changelog\n\n'):]
write(p,s)

print(f'v1.3.1 patch staged for edition={edition}')
