<?php
require dirname(__DIR__).'/config.php';
$mode=$argv[1]??'recovery';
if ($mode==='recovery') {
    $job=['requestId'=>bin2hex(random_bytes(16)),'campaignId'=>'CAMP-RECOVERY','customerId'=>'CUST-08001','promoCode'=>'PROMO15','subject'=>'Diagnostic recovery','body'=>'Consent check only.'];
} elseif ($mode==='duplicate') {
    $db=(new Lab\Database())->connect();
    $row=$db->query("SELECT o.* FROM marketing_outbox o JOIN sent_marketing_emails h ON h.campaign_id=o.campaign_id AND h.customer_id=o.customer_id WHERE o.campaign_id='CAMP-001' AND h.status='SENT' ORDER BY o.customer_id LIMIT 1")->fetch();
    if (!$row) throw new RuntimeException('Baseline must have a SENT result before duplicate testing');
    $job=['requestId'=>$row['request_id'],'campaignId'=>$row['campaign_id'],'customerId'=>$row['customer_id'],'promoCode'=>$row['promo_code'],'subject'=>$row['subject'],'body'=>$row['body']];
} else {throw new InvalidArgumentException('Use recovery or duplicate');}
echo json_encode($job,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
