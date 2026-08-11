<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RefreshCw, Save, Upload } from '@lucide/vue'
import { get, post } from '../api'
import { useAppStore } from '../stores/app'
const store=useAppStore();const family=ref('CustomFont');const url=ref('');const uploading=ref(false)
const builtins=[['system-ui','系统默认'],['Arial, sans-serif','Arial'],['"Segoe UI", sans-serif','Segoe UI'],['"PingFang SC", sans-serif','苹方'],['"Microsoft YaHei", sans-serif','微软雅黑'],['"Noto Sans SC", sans-serif','Noto Sans SC'],['serif','衬线体'],['monospace','等宽体']]
async function load(){try{const s:any=await get('/admin/api/settings.php');family.value=s.font_family||'CustomFont';url.value=s.font_url||''}catch(e:any){store.notify(e?.message||'字体设置加载失败','error')}}
async function save(){try{await post('/admin/api/settings.php',{settings:{font_family:family.value.trim(),font_url:url.value.trim()}});store.notify('字体设置已保存')}catch(e:any){store.notify(e?.message||'保存失败','error')}}

async function readFontName(file:File):Promise<string|null>{
  try{
    const buf=await file.arrayBuffer();const view=new DataView(buf);if(view.byteLength<12)return null
    const sig=view.getUint32(0);if(sig!==0x00010000&&sig!==0x4f54544f)return null
    const numTables=view.getUint16(4);let nameOffset=0
    for(let i=0;i<numTables;i++){
      const p=12+i*16;if(p+16>view.byteLength)break
      const tag=String.fromCharCode(view.getUint8(p),view.getUint8(p+1),view.getUint8(p+2),view.getUint8(p+3))
      if(tag==='name'){nameOffset=view.getUint32(p+8);break}
    }
    if(!nameOffset||nameOffset+6>view.byteLength)return null
    const count=view.getUint16(nameOffset+2);const storage=nameOffset+view.getUint16(nameOffset+4);let fallback:string|null=null
    for(let i=0;i<count;i++){
      const p=nameOffset+6+i*12;if(p+12>view.byteLength)break
      const platform=view.getUint16(p);const nameId=view.getUint16(p+6);const len=view.getUint16(p+8);const off=view.getUint16(p+10);if(nameId!==1&&nameId!==4)continue
      const start=storage+off;if(start+len>view.byteLength)continue
      let name=''
      if(platform===3||platform===0){for(let j=0;j+1<len;j+=2)name+=String.fromCharCode(view.getUint16(start+j,false))}
      else if(platform===1){for(let j=0;j<len;j++)name+=String.fromCharCode(view.getUint8(start+j))}
      name=name.replace(/\0/g,'').trim();if(!name)continue
      if(nameId===4)return name;if(!fallback)fallback=name
    }
    return fallback
  }catch{return null}
}

async function upload(file?:File){
  if(!file)return;uploading.value=true
  try{
    const detected=await readFontName(file)
    const fd=new FormData();fd.append('category','font');fd.append('file',file)
    const r:any=await post('/admin/api/upload.php',fd);if(!r.ok)throw new Error(r.error||'上传失败')
    url.value=r.url||'';family.value=detected||'用户上传字体'
    store.notify(detected?`字体已上传，识别为「${detected}」`:'字体已上传；当前格式未解析出内部字体名')
  }catch(e:any){store.notify(e?.message||'上传失败','error')}finally{uploading.value=false}
}
onMounted(load)
</script>
<template><div>
<div class="page-head"><div><h1>字体管理</h1><p>选择系统字体或上传 TTF / OTF / WOFF / WOFF2；TTF/OTF 会优先读取内部 Family / Full Name。</p></div><button class="button" @click="load"><RefreshCw :size="14"/>重新读取</button></div>
<div class="two-col">
 <section class="form-card"><div class="form-section"><h3>当前字体</h3><p>设置会写入站点配置，并自动进入公开 config API。</p><div class="field"><label>字体族</label><input v-model="family" class="input"></div><div class="field" style="margin-top:10px"><label>字体文件 URL</label><div class="toolbar" style="margin:0"><input v-model="url" class="input" style="flex:1"><label class="button"><Upload :size="13"/>{{uploading?'识别并上传中…':'上传字体'}}<input type="file" accept=".ttf,.otf,.woff,.woff2" hidden @change="upload(($event.target as HTMLInputElement).files?.[0])"></label></div></div><div :style="{fontFamily:family}" style="margin-top:14px;padding:20px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);font-size:22px;line-height:1.5">字体预览 · AppDown<br>ABCDEFG abcdefg 1234567890<br>应用分发，简洁而现代。</div><button class="button primary" style="margin-top:12px" @click="save"><Save :size="13"/>保存字体设置</button></div></section>
 <section class="card"><h3>系统字体</h3><p class="subtle">不需要下载外部字体文件，性能最好。</p><div class="task-list" style="margin-top:12px"><button v-for="f in builtins" :key="f[0]" class="task-card" style="text-align:left;color:var(--text)" :style="{fontFamily:f[0]}" @click="family=f[0];url=''" type="button"><div class="task-head"><b>{{f[1]}}</b><span class="badge muted" v-if="family===f[0]">当前</span></div><p style="font-size:16px;color:var(--text);margin-top:8px">AppDown 应用分发 ABC 123</p></button></div></section>
</div>
</div></template>
