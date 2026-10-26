<?php
declare(strict_types=1);
require dirname(__DIR__).'/config.php';

use Lab\Database;
use Lab\Settings;
use PHPMailer\PHPMailer\PHPMailer;

$mode=$argv[1]??'local';
if (!in_array($mode,['local','aws','smtp'],true)) {
    fwrite(STDERR,"Usage: php tools/preflight.php [local|aws|smtp]\n");exit(2);
}
try {
    if ($mode==='local') {
        $db=(new Database())->connect();
        $total=(int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        $eligible=(int)$db->query('SELECT COUNT(*) FROM customers WHERE marketing_opt_in=1')->fetchColumn();
        $pending=(int)$db->query("SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id='CAMP-001' AND status='PENDING'")->fetchColumn();
        $distinct=(int)$db->query("SELECT COUNT(DISTINCT customer_id) FROM marketing_outbox WHERE campaign_id='CAMP-001'")->fetchColumn();
        $ineligible=(int)$db->query("SELECT COUNT(*) FROM marketing_outbox o JOIN customers c ON c.customer_id=o.customer_id WHERE o.campaign_id='CAMP-001' AND c.marketing_opt_in<>1")->fetchColumn();
        $history=(int)$db->query("SELECT COUNT(*) FROM sent_marketing_emails WHERE campaign_id='CAMP-001'")->fetchColumn();
        echo "Baseline readiness (UTC ".gmdate('Y-m-d H:i:s').")\n";
        echo "Customers: $total\nOpted in: $eligible\nOpted out: ".($total-$eligible)."\nPENDING: $pending\nDistinct recipients: $distinct\nIneligible selected: $ineligible\nBaseline history rows: $history\n";
        if ($total!==10000 || $eligible!==8000 || $pending!==50 || $distinct!==50 || $ineligible!==0 || $history!==0) throw new RuntimeException('Baseline not ready');
        echo "PASS: local baseline ready. No internet connection is used by this check.\n";
    } elseif ($mode==='aws') {
        $identity=(new Aws\Sts\StsClient(['version'=>'2011-06-15','region'=>Settings::required('AWS_REGION'),'http'=>['connect_timeout'=>5,'timeout'=>15],'retries'=>0]))->getCallerIdentity();
        $r=Settings::queue()->getQueueAttributes(['QueueUrl'=>Settings::required('SQS_QUEUE_URL'),'AttributeNames'=>['QueueArn','VisibilityTimeout','MessageRetentionPeriod','ReceiveMessageWaitTimeSeconds','RedrivePolicy','ApproximateNumberOfMessages','ApproximateNumberOfMessagesNotVisible']]);
        echo json_encode(['applicationIdentity'=>$identity['Arn'],'queue'=>$r['Attributes']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        echo "Read-only check complete. No message was sent or received.\n";
    } else {
        $s=Settings::smtp();
        // This lab is an Email Sandbox activity. Never authenticate against a
        // production SMTP host accidentally or submit an email as a test.
        if (!in_array(strtolower($s['host']),['sandbox.smtp.mailtrap.io','sandbox.smtp.mailtrap.live'],true)) throw new InvalidArgumentException('Expected Mailtrap sandbox SMTP host');
        $mail=new PHPMailer(true);$mail->isSMTP();
        $mail->Host=$s['host'];$mail->Port=(int)$s['port'];
        $mail->SMTPAuth=true;$mail->Username=$s['username'];$mail->Password=$s['password'];
        $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout=15;$mail->Timelimit=15;$mail->SMTPDebug=0;
        try {
            if (!$mail->smtpConnect()) throw new RuntimeException('SMTP connection rejected');
            echo "PASS: Mailtrap STARTTLS connection and SMTP authentication accepted.\n";
            echo "No email was submitted. Confirm remaining allowance separately in Mailtrap.\n";
        } finally {$mail->smtpClose();}
    }
} catch (Throwable $e) {
    fwrite(STDERR,'Preflight failed ('.get_class($e)."). Check private configuration; credential values are not displayed.\n");exit(1);
}
