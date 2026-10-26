<?php
require dirname(__DIR__).'/config.php';
$client=new Aws\Sts\StsClient(['version'=>'2011-06-15','region'=>Lab\Settings::required('AWS_REGION'),'retries'=>0,'http'=>['connect_timeout'=>5,'timeout'=>15]]);
$r=$client->getCallerIdentity();
echo json_encode(['Account'=>$r['Account'],'Arn'=>$r['Arn']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
