<?php
// SaaS isolation smoke: emulate an authenticated tenant before loading helpers.
if (!function_exists('current_tenant')) {
    function current_tenant(bool $allowCli = false): ?array { return ['slug' => 'template-builder-smoke']; }
}
if (!function_exists('tenant_upload_dir')) {
    function tenant_upload_dir(): string { return dirname(__DIR__) . '/data/template-builder-smoke-tenant'; }
}

require_once __DIR__ . '/../includes/template_builder.php';

$cases=[['org.example.app','org.example.app',true],['org.example.*','org.example.app',true],['org.example.*','org.other.app',false],['*','anything.bundle',true]];
foreach($cases as [$pattern,$bundle,$want]){
    if(template_builder_profile_matches($pattern,$bundle)!==$want){
        fwrite(STDERR,"profile match failed: {$pattern} {$bundle}\n");
        exit(1);
    }
}

$required=[__DIR__.'/../admin/api/template-builder.php',__DIR__.'/../admin/api/ios-signing.php',__DIR__.'/../tools/template-builder-worker.php',__DIR__.'/../builder/template-builder/template_builder.py',__DIR__.'/../admin-ui/src/views/BuilderView.vue'];
foreach($required as $file){
    if(!is_file($file)||filesize($file)<50){
        fwrite(STDERR,"missing Template Builder file: {$file}\n");
        exit(1);
    }
}

$vue=file_get_contents(__DIR__.'/../admin-ui/src/views/BuilderView.vue');
foreach(['快速模板','完整编译','Apple P12 身份','Provisioning Profile','canManageEnv','共享构建环境由平台管理员统一维护'] as $marker){
    if(!str_contains($vue,$marker)){
        fwrite(STDERR,"BuilderView parity/isolation marker missing: {$marker}\n");
        exit(1);
    }
}

$api=file_get_contents(__DIR__.'/../admin/api/template-builder.php');
if(!str_contains($api,'SaaS 共享 Template Builder 环境仅允许平台管理员维护')){
    fwrite(STDERR,"SaaS shared-environment API guard missing\n");
    exit(1);
}

// A tenant may reference only its own uploaded files, never another tenant/project file.
$tenantDir=tenant_upload_dir();
@mkdir($tenantDir,0750,true);
$outside=dirname(__DIR__).'/data/template-builder-smoke-outside.png';
$inside=$tenantDir.'/inside.png';
file_put_contents($inside,'inside');
file_put_contents($outside,'outside');
$root=realpath(dirname(__DIR__));
$insideReal=realpath($inside);
$outsideReal=realpath($outside);
$insideRel=ltrim(substr($insideReal,strlen($root)),DIRECTORY_SEPARATOR);
$outsideRel=ltrim(substr($outsideReal,strlen($root)),DIRECTORY_SEPARATOR);
try {
    if(template_builder_resolve_project_file($insideRel)!==$insideReal){
        fwrite(STDERR,"current-tenant upload should be accepted\n");
        exit(1);
    }
    if(template_builder_resolve_project_file($outsideRel)!==''){
        fwrite(STDERR,"cross-tenant/project file boundary failed\n");
        exit(1);
    }
    if(template_builder_resolve_project_file('../etc/passwd')!=='' || template_builder_resolve_project_file('https://example.com/icon.png')!==''){
        fwrite(STDERR,"unsafe/non-local Builder file input accepted\n");
        exit(1);
    }
} finally {
    @unlink($inside);
    @unlink($outside);
    @rmdir($tenantDir);
}

echo "Template Builder SaaS smoke OK\n";
