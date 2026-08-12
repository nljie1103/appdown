<?php
/** Template Builder 2.0 API. */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/template_builder.php';
require_auth();

$pdo = get_db();
ensure_template_builder_schema($pdo);
$method = get_request_method();
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'status' || $action === '') json_response(template_builder_status($pdo));
    if ($action === 'install_log') {
        $log = template_builder_data_dir() . '/install.log';
        json_response(['status'=>get_setting($pdo,'template_builder_install_status','idle'),'log'=>is_file($log)?file_get_contents($log):'']);
    }
    if ($action === 'tasks') {
        $platform = ($_GET['platform'] ?? 'android') === 'ios' ? 'ios' : 'android';
        $type = $platform === 'ios' ? 'template-ipa' : 'template-apk';
        $stmt = $pdo->prepare("SELECT id,build_type,status,progress,progress_msg,result_url,result_size,error_msg,created_at,updated_at,params FROM build_tasks WHERE build_type=? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$type]); $rows=$stmt->fetchAll();
        foreach($rows as &$r){$p=json_decode($r['params']??'{}',true)?:[];$r['app_name']=$p['app_name']??'';$r['identifier']=$p[$platform==='ios'?'bundle_id':'package_name']??'';unset($r['params']);}unset($r);
        json_response($rows);
    }
    json_response(['error'=>'无效 action'],400);
}

csrf_validate();
if ($method === 'POST') {
    $data=get_json_input(); $action=$data['action']??$action;
    if(in_array($action,['install','uninstall'],true) && defined('APPDOWN_EDITION') && APPDOWN_EDITION==='saas') json_response(['error'=>'SaaS 共享 Template Builder 环境仅允许平台管理员维护'],403);
    if(in_array($action,['install','uninstall'],true)){
        if(get_setting($pdo,'template_builder_install_status','idle')==='running')json_response(['error'=>'环境管理任务正在运行'],409);
        $runner=template_builder_runner();
        if(!is_file($runner))json_response(['error'=>'尚未完成一次性安全 Bootstrap。请先在服务器执行：sudo bash tools/setup-template-builder.sh'],400);
        $worker=realpath(__DIR__.'/../../tools/template-builder-env-worker.php');if(!$worker)json_response(['error'=>'环境 Worker 不存在'],500);
        $log=template_builder_data_dir().'/install.log';@file_put_contents($log,"正在启动 {$action}...\n");set_setting($pdo,'template_builder_install_status','running');
        $php=is_file(PHP_BINDIR.'/php')?PHP_BINDIR.'/php':'php';$prefix='';
        if(defined('APPDOWN_EDITION')&&APPDOWN_EDITION==='saas'&&function_exists('current_tenant')){$tenant=current_tenant(true);if($tenant)$prefix='APPDOWN_TENANT='.escapeshellarg($tenant['slug']).' ';}
        exec($prefix.'nohup '.escapeshellarg($php).' '.escapeshellarg($worker).' '.escapeshellarg($action).' '.escapeshellarg($log).' >/dev/null 2>&1 &');
        json_response(['ok'=>true]);
    }
    if($action==='build'){
        $platform=($data['platform']??'android')==='ios'?'ios':'android';$buildType=$platform==='ios'?'template-ipa':'template-apk';
        $url=trim($data['url']??'');$appName=trim($data['app_name']??'');$versionName=trim($data['version_name']??'1.0.0');$versionCode=max(1,(int)($data['version_code']??1));$iconUrl=trim($data['icon_url']??'');
        if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true))json_response(['error'=>'请输入有效 HTTP/HTTPS URL'],400);
        if($appName==='')json_response(['error'=>'请输入应用名称'],400);
        if($iconUrl!==''&&template_builder_resolve_project_file($iconUrl)==='')json_response(['error'=>'图标必须来自本站已上传文件'],400);
        if(!template_builder_status($pdo)['all_ok'])json_response(['error'=>'Template Builder 尚未 Ready，请先安装/修复构建环境'],400);
        $running=$pdo->prepare("SELECT COUNT(*) FROM build_tasks WHERE status IN ('pending','building') AND build_type=?");$running->execute([$buildType]);if((int)$running->fetchColumn()>0)json_response(['error'=>'当前已有同平台快速构建任务'],409);
        $params=['builder_engine'=>'template','url'=>$url,'app_name'=>$appName,'version_name'=>$versionName?:'1.0.0','version_code'=>$versionCode,'icon_url'=>$iconUrl,'status_bar_color'=>trim($data['status_bar_color']??'#000000')];$keystoreId=0;
        if($platform==='android'){
            $package=trim($data['package_name']??'');if(!preg_match('/^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)+$/',$package))json_response(['error'=>'Package Name 格式错误'],400);
            $keystoreId=(int)($data['keystore_id']??0);$ks=$pdo->prepare('SELECT id FROM keystores WHERE id=?');$ks->execute([$keystoreId]);if(!$keystoreId||!$ks->fetch())json_response(['error'=>'请选择有效 Android Keystore'],400);
            $params+=['package_name'=>$package,'keystore_id'=>$keystoreId,'splash_color'=>trim($data['splash_color']??'#FFFFFF'),'enable_splash'=>!empty($data['enable_splash']),'splash_duration'=>min(10000,max(0,(int)($data['splash_duration']??1200)))];
        }else{
            $bundle=trim($data['bundle_id']??'');if(!preg_match('/^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)+$/',$bundle))json_response(['error'=>'Bundle ID 格式错误'],400);
            $identityId=(int)($data['identity_id']??0);$profileId=(int)($data['profile_id']??0);
            $idStmt=$pdo->prepare('SELECT id,cert_expires,cert_sha256 FROM ios_signing_identities WHERE id=?');$idStmt->execute([$identityId]);$identity=$idStmt->fetch();
            $pfStmt=$pdo->prepare('SELECT id,bundle_pattern,expires_at,cert_sha256_json FROM ios_provisioning_profiles WHERE id=?');$pfStmt->execute([$profileId]);$profile=$pfStmt->fetch();
            if(!$identity||!$profile)json_response(['error'=>'请选择有效 P12 身份和 Provisioning Profile'],400);
            if($identity['cert_expires']&&strtotime($identity['cert_expires'])<=time())json_response(['error'=>'P12 证书已过期'],400);
            if($profile['expires_at']&&strtotime($profile['expires_at'])<=time())json_response(['error'=>'Provisioning Profile 已过期'],400);
            if(!template_builder_profile_matches($profile['bundle_pattern'],$bundle))json_response(['error'=>'Provisioning Profile 与 Bundle ID 不匹配'],400);
            $certs=json_decode($profile['cert_sha256_json']??'[]',true)?:[];if($certs&&!in_array(strtoupper($identity['cert_sha256']),array_map('strtoupper',$certs),true))json_response(['error'=>'所选 P12 证书不属于该 Provisioning Profile'],400);
            $params+=['bundle_id'=>$bundle,'identity_id'=>$identityId,'profile_id'=>$profileId];
        }
        $stmt=$pdo->prepare("INSERT INTO build_tasks (build_type,status,params,keystore_id) VALUES (?,'pending',?,?)");$stmt->execute([$buildType,json_encode($params,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$keystoreId]);$taskId=(int)$pdo->lastInsertId();
        $worker=realpath(__DIR__.'/../../tools/template-builder-worker.php');if(!$worker)json_response(['error'=>'Template Builder Worker 不存在'],500);$php=is_file(PHP_BINDIR.'/php')?PHP_BINDIR.'/php':'php';$log=template_builder_data_dir().'/task_'.$taskId.'.log';$prefix='';
        if(defined('APPDOWN_EDITION')&&APPDOWN_EDITION==='saas'&&function_exists('current_tenant')){$tenant=current_tenant(true);if($tenant)$prefix='APPDOWN_TENANT='.escapeshellarg($tenant['slug']).' ';}
        $out=[];exec($prefix.'nohup '.escapeshellarg($php).' '.escapeshellarg($worker).' '.$taskId.' > '.escapeshellarg($log).' 2>&1 & echo $!',$out);$pid=(int)($out[0]??0);if($pid>0)$pdo->prepare('UPDATE build_tasks SET pid=? WHERE id=?')->execute([$pid,$taskId]);json_response(['ok'=>true,'task_id'=>$taskId]);
    }
    json_response(['error'=>'无效 action'],400);
}
json_response(['error'=>'method not allowed'],405);
