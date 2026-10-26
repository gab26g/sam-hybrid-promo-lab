<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Lab\ActivityLogger;
use Lab\CampaignPreparer;
use Lab\CustomerSeeder;
use Lab\EmailHistory;
use Lab\EmailSender;
use Lab\MarketingProcessor;
use Lab\QueuePublisher;
use Lab\QueueWorker;
use PHPMailer\PHPMailer\PHPMailer;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function fails(callable $fn): void {
    try { $fn(); } catch (Throwable) { return; }
    throw new RuntimeException('Expected operation to fail');
}
function database(): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->sqliteCreateFunction('RAND', fn() => random_int(0, PHP_INT_MAX));
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('CREATE TABLE customers (customer_id TEXT PRIMARY KEY, full_name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, marketing_opt_in INTEGER NOT NULL CHECK(marketing_opt_in IN (0,1)), created_at TEXT NOT NULL)');
    $db->exec("CREATE TABLE marketing_outbox (request_id TEXT PRIMARY KEY, campaign_id TEXT NOT NULL, customer_id TEXT NOT NULL REFERENCES customers(customer_id), promo_code TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'PENDING', created_at TEXT NOT NULL, queued_at TEXT, sqs_message_id TEXT, UNIQUE(campaign_id,customer_id))");
    $db->exec('CREATE TABLE sent_marketing_emails (campaign_id TEXT NOT NULL, customer_id TEXT NOT NULL, request_id TEXT NOT NULL, promo_code TEXT NOT NULL, recipient_email TEXT, subject TEXT NOT NULL, status TEXT NOT NULL, provider TEXT, message_id TEXT, reason TEXT, processed_at TEXT NOT NULL, sent_at TEXT, PRIMARY KEY(campaign_id,customer_id))');
    return $db;
}
function sqs(MockHandler $mock): SqsClient {
    return new SqsClient(['region'=>'us-east-1','version'=>'2012-11-05','credentials'=>['key'=>'example','secret'=>'example'],'handler'=>$mock]);
}
function job(string $campaign='TEST', string $customer='CUST-00001'): array {
    return ['requestId'=>str_repeat('a',32),'campaignId'=>$campaign,'customerId'=>$customer,'promoCode'=>'PROMO15','subject'=>'Laboratory promotion','body'=>'Selected offer'];
}
// Only the external SMTP operation is replaced. PHPMailer builds the actual message.
class CapturingMailer extends PHPMailer {
    public int $submissions = 0;
    public bool $reject = false;
    public function send(): bool {
        if ($this->reject) throw new RuntimeException('Simulated SMTP unavailable');
        $this->submissions++;
        $this->preSend();
        return true;
    }
}
function sender(CapturingMailer $mailer): EmailSender {
    return new EmailSender(['host'=>'sandbox.smtp.mailtrap.io','port'=>2525,'username'=>'example','password'=>'example','from'=>'marketing@example.com'], fn()=>$mailer);
}

$tests = [];
$tests['seed is repeatable, preserves existing records, and enforces 8000/2000 consent split'] = function() {
    $db=database(); $faker=Faker\Factory::create('en_US'); $faker->seed(2026);
    $seeder=new CustomerSeeder($db,$faker); $seeder->seed();
    check((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()===10000,'total');
    check((int)$db->query('SELECT SUM(marketing_opt_in) FROM customers')->fetchColumn()===8000,'consent');
    check($db->query("SELECT email FROM customers WHERE customer_id='CUST-10000'")->fetchColumn()==='customer10000@example.com','reserved email');
    $db->exec("UPDATE customers SET full_name='Preserve me', marketing_opt_in=0 WHERE customer_id='CUST-00001'");
    $seeder->seed();
    check((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()===10000,'repeat count');
    check($db->query("SELECT full_name FROM customers WHERE customer_id='CUST-00001'")->fetchColumn()==='Preserve me','existing changed');
};
$tests['campaign preserves 50 eligible winners, request IDs and promo code, and rejects partial selection'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed();
    $p=new CampaignPreparer($db); $p->prepare('CAMP-001','PROMO15');
    $before=$db->query('SELECT * FROM marketing_outbox ORDER BY customer_id')->fetchAll();
    check(count($before)===50,'count');
    check(count(array_unique(array_column($before,'customer_id')))===50,'distinct');
    check((int)$db->query('SELECT COUNT(*) FROM marketing_outbox o JOIN customers c ON c.customer_id=o.customer_id WHERE c.marketing_opt_in<>1')->fetchColumn()===0,'ineligible');
    foreach($before as $row) check((bool)preg_match('/^[a-f0-9]{32}$/',$row['request_id']),'persistent hex ID');
    $p->prepare('CAMP-001','CHANGED');
    check($db->query('SELECT * FROM marketing_outbox ORDER BY customer_id')->fetchAll()===$before,'selection mutated');
    $db->exec("DELETE FROM marketing_outbox WHERE request_id='".$before[0]['request_id']."'");
    fails(fn()=>$p->prepare('CAMP-001','PROMO15'));
    check((int)$db->query('SELECT COUNT(*) FROM marketing_outbox')->fetchColumn()===49,'partial filled');
};
$tests['publication stores acknowledgment, excludes personal data, and does not republish'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed(); (new CampaignPreparer($db))->prepare('CAMP-001','PROMO15');
    $seen=[]; $mock=new MockHandler();
    for($i=0;$i<50;$i++) $mock->append(function($command) use (&$seen,$i){$seen[]=json_decode($command['MessageBody'],true,512,JSON_THROW_ON_ERROR); return new Result(['MessageId'=>'sqs-'.$i]);});
    $log=new ActivityLogger(sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl');
    $p=new QueuePublisher($db,sqs($mock),'https://sqs.us-east-1.amazonaws.com/123456789012/test',$log);
    check($p->publishPending()===50,'publication count'); check($p->publishPending()===0,'republished');
    check((int)$db->query("SELECT COUNT(*) FROM marketing_outbox WHERE status='QUEUED' AND sqs_message_id IS NOT NULL AND queued_at IS NOT NULL")->fetchColumn()===50,'ack state');
    check(count($seen)===50,'messages');
    check(array_keys($seen[0])===['requestId','campaignId','customerId','promoCode','subject','body'],'payload contract');
};
$tests['unconfirmed publish stays pending and can retry'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed(); (new CampaignPreparer($db))->prepare('CAMP-001','PROMO15');
    $mock=new MockHandler([new RuntimeException('Simulated queue unavailable')]);
    $log=new ActivityLogger(sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl');
    fails(fn()=>(new QueuePublisher($db,sqs($mock),'https://sqs.us-east-1.amazonaws.com/123456789012/test',$log))->publishPending());
    check((int)$db->query("SELECT COUNT(*) FROM marketing_outbox WHERE status='PENDING'")->fetchColumn()===50,'failed send marked queued');
};
$tests['processor sends personalized promo, suppresses terminal duplicates, and skips revoked or missing customers'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed();
    $mail=new CapturingMailer(true); $history=new EmailHistory($db);
    $log=new ActivityLogger(sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl');
    $p=new MarketingProcessor($db,$history,sender($mail),$log,0);
    $p->process(job()); $p->process(job());
    check($mail->submissions===1,'duplicate send');
    $row=$history->find('TEST','CUST-00001');
    check($row['status']==='SENT' && $row['message_id']!==null && $row['sent_at']!==null,'missing sent details');
    check(str_contains($mail->Body,'PROMO15') && str_contains($mail->Body,$db->query("SELECT full_name FROM customers WHERE customer_id='CUST-00001'")->fetchColumn()),'personalized content');
    $p->process(job('RECOVERY','CUST-08001')); $p->process(job('UNKNOWN','CUST-99999'));
    check($history->find('RECOVERY','CUST-08001')['status']==='SKIPPED','consent');
    check($history->find('UNKNOWN','CUST-99999')['status']==='SKIPPED','unknown customer');
    $db->exec("UPDATE customers SET marketing_opt_in=0 WHERE customer_id='CUST-00002'");
    $p->process(job('REVOKED','CUST-00002'));
    check($history->find('REVOKED','CUST-00002')['status']==='SKIPPED','current consent');
    check($mail->submissions===1,'skip sent email');
};
$tests['SMTP failure records FAILED and retry can later succeed'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed();
    $mail=new CapturingMailer(true);$mail->reject=true; $h=new EmailHistory($db);
    $p=new MarketingProcessor($db,$h,sender($mail),new ActivityLogger(sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl'),0);
    fails(fn()=>$p->process(job())); check($h->find('TEST','CUST-00001')['status']==='FAILED','failure not saved');
    $mail->reject=false; $p->process(job()); check($h->find('TEST','CUST-00001')['status']==='SENT','retry blocked');
};
$tests['worker stores outcome before deletion using receipt handle and does not acknowledge invalid input'] = function() {
    $db=database(); (new CustomerSeeder($db,Faker\Factory::create()))->seed();
    $h=new EmailHistory($db); $mail=new CapturingMailer(true);
    $path=sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl'; $log=new ActivityLogger($path);
    $p=new MarketingProcessor($db,$h,sender($mail),$log,0); $deletions=0;
    $mock=new MockHandler([function($cmd) use($h,&$deletions){check($cmd->getName()==='DeleteMessage','wrong operation');check($cmd['ReceiptHandle']==='current-receipt','wrong handle');check($h->find('TEST','CUST-00001')['status']==='SENT','delete before storage');$deletions++;return new Result([]);}]);
    $w=new QueueWorker(sqs($mock),'https://sqs.us-east-1.amazonaws.com/123456789012/test',$p,$log);
    check($w->handle(['MessageId'=>'message-one','ReceiptHandle'=>'current-receipt','Body'=>json_encode(job())])===true,'success');
    check($w->handle(['MessageId'=>'broken','ReceiptHandle'=>'broken-receipt','Body'=>'{"campaignId":"CAMP-BROKEN"}'])===false,'invalid acknowledged');
    check($w->handle(['MessageId'=>'malformed','ReceiptHandle'=>'bad','Body'=>'{'])===false,'bad JSON acknowledged');
    check($deletions===1,'unexpected deletion');
    $events=array_map(fn($l)=>json_decode($l,true),file($path,FILE_IGNORE_NEW_LINES));
    $names=array_column($events,'event');check(array_search('sent',$names)<array_search('deleted',$names),'log order');
};
$tests['failed acknowledgment is distinguished from processing failure and redelivery does not resend'] = function() {
    $db=database();(new CustomerSeeder($db,Faker\Factory::create()))->seed();$mail=new CapturingMailer(true);
    $path=sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl';$log=new ActivityLogger($path);
    $p=new MarketingProcessor($db,new EmailHistory($db),sender($mail),$log,0);
    $mock=new MockHandler([new RuntimeException('Simulated delete failure'),new Result([])]);
    $w=new QueueWorker(sqs($mock),'https://sqs.us-east-1.amazonaws.com/123456789012/test',$p,$log);
    $m=['MessageId'=>'one','ReceiptHandle'=>'first','Body'=>json_encode(job())];check(!$w->handle($m),'delete failed but returned success');
    $m['ReceiptHandle']='second';check($w->handle($m),'redelivery failed');check($mail->submissions===1,'redelivery resent');
    $events=array_map(fn($l)=>json_decode($l,true),file($path,FILE_IGNORE_NEW_LINES));check(in_array('ack_failed',array_column($events,'event'),true),'missing ack event');
};
$tests['logger filters secrets, personal data, body and raw exception messages'] = function() {
    $path=sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl';$l=new ActivityLogger($path);
    $l->record('test',['requestId'=>str_repeat('a',32),'email'=>'private@example.com','password'=>'private','body'=>'private','error'=>'private']);
    $text=file_get_contents($path);check(!str_contains($text,'private'),'data leaked');
    $data=json_decode($text,true,512,JSON_THROW_ON_ERROR);check(isset($data['timestamp'],$data['event'],$data['requestId']),'metadata');
};

$tests['failure logs reject malformed identifiers containing personal data or oversized values'] = function() {
    $db=database();$mail=new CapturingMailer(true);$path=sys_get_temp_dir().'/lab-'.bin2hex(random_bytes(6)).'.jsonl';$log=new ActivityLogger($path);
    $p=new MarketingProcessor($db,new EmailHistory($db),sender($mail),$log,0);
    $w=new QueueWorker(sqs(new MockHandler()),'https://sqs.us-east-1.amazonaws.com/123456789012/test',$p,$log);
    $bad=job();$bad['requestId']='private.person@example.com';$bad['campaignId']=str_repeat('private-value',1000);
    check(!$w->handle(['MessageId'=>'safe-message-id','ReceiptHandle'=>'unused','Body'=>json_encode($bad)]),'bad identifiers accepted');
    $text=file_get_contents($path);check(!str_contains($text,'private'),'unvalidated ID leaked');
    check(str_contains($text,'safe-message-id'),'safe identifier missing');
};

$failed=0;
foreach($tests as $name=>$test) {try{$test();echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: ".get_class($e).': '.$e->getMessage()."\n";}}
echo count($tests).' tests; '.$failed." failures\n";
exit($failed ? 1 : 0);
