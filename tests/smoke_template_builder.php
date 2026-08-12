<?php
require_once __DIR__ . '/../includes/template_builder.php';
$cases=[['org.example.app','org.example.app',true],['org.example.*','org.example.app',true],['org.example.*','org.other.app',false],['*','anything.bundle',true]];
foreach($cases as [$pattern,$bundle,$want]){if(template_builder_profile_matches($pattern,$bundle)!==$want){fwrite(STDERR,"profile match failed: {$pattern} {$bundle}\n");exit(1);}}
$required=[__DIR__.'/../admin/api/template-builder.php',__DIR__.'/../admin/api/ios-signing.php',__DIR__.'/../tools/template-builder-worker.php',__DIR__.'/../builder/template-builder/template_builder.py',__DIR__.'/../admin-ui/src/views/BuilderView.vue'];foreach($required as $file){if(!is_file($file)||filesize($file)<50){fwrite(STDERR,"missing Template Builder file: {$file}\n");exit(1);}}
$vue=file_get_contents(__DIR__.'/../admin-ui/src/views/BuilderView.vue');foreach(['快速模板','完整编译','Apple P12 身份','Provisioning Profile'] as $marker){if(!str_contains($vue,$marker)){fwrite(STDERR,"BuilderView parity marker missing: {$marker}\n");exit(1);}}
echo "Template Builder smoke OK\n";
