<?php
declare(strict_types=1);
require dirname(__DIR__).'/config.php';

$db=(new Lab\Database())->connect();
$campaign='CAMP-001';
$q=$db->prepare('SELECT status,COUNT(*) AS total FROM sent_marketing_emails WHERE campaign_id=? GROUP BY status');$q->execute([$campaign]);
$counts=['SENT'=>0,'SKIPPED'=>0,'FAILED'=>0];
foreach ($q->fetchAll() as $row) $counts[$row['status']]=(int)$row['total'];
$outbox=$db->prepare('SELECT request_id,customer_id,promo_code,sqs_message_id FROM marketing_outbox WHERE campaign_id=? ORDER BY customer_id');$outbox->execute([$campaign]);$selected=$outbox->fetchAll();
$bad=$db->prepare('SELECT COUNT(*) FROM sent_marketing_emails h LEFT JOIN marketing_outbox o ON o.campaign_id=h.campaign_id AND o.customer_id=h.customer_id WHERE h.campaign_id=? AND (o.request_id IS NULL OR h.request_id<>o.request_id OR h.promo_code<>o.promo_code)');$bad->execute([$campaign]);
$unmatched=(int)$bad->fetchColumn();
$details=$db->prepare("SELECT COUNT(*) FROM sent_marketing_emails WHERE campaign_id=? AND status='SENT' AND (recipient_email IS NULL OR provider IS NULL OR message_id IS NULL OR sent_at IS NULL)");$details->execute([$campaign]);$missing=(int)$details->fetchColumn();
$events=[];$log=dirname(__DIR__).'/logs/events.jsonl';
if (is_file($log)) foreach (file($log,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) $events[]=json_decode($line,true,512,JSON_THROW_ON_ERROR);
$traces=[];$complete=0;
foreach ($selected as $row) {
    $trace=[];
    foreach ($events as $i=>$event) if (($event['requestId']??null)===$row['request_id'] || ($event['messageId']??null)===$row['sqs_message_id']) $trace[]=['sequence'=>$i]+$event;
    $names=array_column($trace,'event');$positions=[];
    foreach (['queued','received','processing_started','sent','deleted'] as $name) $positions[$name]=array_search($name,$names,true);
    if (!in_array(false,$positions,true) && $positions['queued']<$positions['received'] && $positions['received']<$positions['processing_started'] && $positions['processing_started']<$positions['sent'] && $positions['sent']<$positions['deleted']) $complete++;
    $traces[$row['customer_id']]=$trace;
}
$pass=count($selected)===50 && $counts===['SENT'=>50,'SKIPPED'=>0,'FAILED'=>0] && $unmatched===0 && $missing===0 && $complete===50;
$summary=['checked_at_utc'=>gmdate('c'),'campaign'=>$campaign,'selected_recipients'=>count($selected),'outcomes'=>$counts,'unmatched_outcomes_or_promo_codes'=>$unmatched,'sent_rows_missing_acceptance_details'=>$missing,'complete_ordered_traces'=>$complete,'baseline_pass'=>$pass];
$dir=dirname(__DIR__).'/evidence';if (!is_dir($dir)) mkdir($dir,0700,true);
file_put_contents($dir.'/baseline-verification.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
foreach ($traces as $trace) if (in_array('deleted',array_column($trace,'event'),true)) {
    file_put_contents($dir.'/baseline-sample-trace.json',json_encode($trace,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));break;
}
$lines=['E11 - Baseline processing evidence','Checked UTC: '.$summary['checked_at_utc'],'Campaign: '.$campaign,'Selected recipients: '.count($selected),'SENT: '.$counts['SENT'],'SKIPPED: '.$counts['SKIPPED'],'FAILED: '.$counts['FAILED'],'Unmatched outcomes or promo codes: '.$unmatched,'SENT rows missing acceptance details: '.$missing,'Complete queued -> received -> processing_started -> sent -> deleted traces: '.$complete.'/50','Result: '.($pass?'PASS - baseline completed':'IN PROGRESS - not yet a completed baseline')];
file_put_contents($dir.'/baseline-summary.txt',implode(PHP_EOL,$lines).PHP_EOL);
echo implode(PHP_EOL,$lines).PHP_EOL;
if (($argv[1]??'')==='--final' && !$pass) exit(1);
