<?php
require dirname(__DIR__).'/config.php';
$production=getenv('DB_NAME')?:'marketing_lab';
$test=getenv('DB_TEST_NAME')?:'marketing_lab_test';
if ($test===$production || !str_ends_with($test,'_test')) throw new RuntimeException('Refusing integration tests outside a dedicated _test database');
putenv('DB_NAME='.$test);$db=(new Lab\Database())->connect();
(new Lab\LocalDatabaseTools($db))->initialize(dirname(__DIR__).'/schema.sql');
$db->exec('DELETE FROM sent_marketing_emails');$db->exec('DELETE FROM marketing_outbox');$db->exec('DELETE FROM customers');
function expectRejected(callable $fn): void {try{$fn();}catch(PDOException){return;}throw new RuntimeException('Constraint failed to reject invalid data');}
$s=new Lab\CustomerSeeder($db,Faker\Factory::create());$s->seed();$s->seed();
if ((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()!==10000 || (int)$db->query('SELECT SUM(marketing_opt_in) FROM customers')->fetchColumn()!==8000) throw new RuntimeException('Population mismatch');
expectRejected(fn()=>$db->exec("UPDATE customers SET marketing_opt_in=2 WHERE customer_id='CUST-00001'"));
expectRejected(fn()=>$db->exec("UPDATE customers SET email='customer00001@example.com' WHERE customer_id='CUST-00002'"));
echo "PASS MariaDB/MySQL population, consent CHECK and unique email constraints\n";
$p=new Lab\CampaignPreparer($db);$p->prepare('CAMP-001','PROMO15');
$before=$db->query('SELECT * FROM marketing_outbox ORDER BY customer_id')->fetchAll();$p->prepare('CAMP-001','CHANGED');
if (count($before)!==50 || $before!==$db->query('SELECT * FROM marketing_outbox ORDER BY customer_id')->fetchAll()) throw new RuntimeException('Selection changed');
expectRejected(fn()=>$db->exec("UPDATE marketing_outbox SET customer_id='MISSING' LIMIT 1"));
expectRejected(fn()=>$db->exec("UPDATE marketing_outbox SET status='SENT' LIMIT 1"));
$h=new Lab\EmailHistory($db);$h->save(['requestId'=>str_repeat('a',32),'campaignId'=>'UNKNOWN','customerId'=>'MISSING','promoCode'=>'PROMO15','subject'=>'Diagnostic'],'SKIPPED',['reason'=>'customer_not_found']);
if (!$h->isComplete('UNKNOWN','MISSING')) throw new RuntimeException('Unknown-customer history rejected');
echo "PASS real schema selection persistence, outbox foreign key, status ENUM and logical history association\n";
echo "Integration tests used only $test. Production campaign untouched.\n";
