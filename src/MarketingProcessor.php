<?php
declare(strict_types=1);
namespace Lab;
use PDO;

final class MarketingProcessor
{
    public function __construct(private PDO $db, private EmailHistory $history, private EmailSender $sender, private ActivityLogger $logger, private int $delaySeconds=11)
    {
        if ($delaySeconds<0 || $delaySeconds>60) throw new \InvalidArgumentException('Send delay must be between 0 and 60 seconds');
    }
    public function process(array $job): void
    {
        // Validate before saving history: malformed jobs may lack usable keys.
        foreach (['requestId'=>32,'campaignId'=>64,'customerId'=>32,'promoCode'=>64,'subject'=>255,'body'=>16000] as $field=>$limit) {
            if (!isset($job[$field]) || !is_string($job[$field]) || trim($job[$field])==='' || mb_strlen($job[$field])>$limit) throw new \InvalidArgumentException("Invalid or missing $field");
        }
        if (!preg_match('/^[a-f0-9]{32}$/',$job['requestId'])) throw new \InvalidArgumentException('Invalid requestId');
        $meta=array_intersect_key($job,array_flip(['requestId','campaignId','customerId']));
        $this->logger->record('processing_started',$meta); // M2
        if ($this->history->isComplete($job['campaignId'],$job['customerId'])) { // P1
            $this->logger->record('duplicate_skipped',$meta);return;
        }
        try {
            $q=$this->db->prepare('SELECT customer_id,full_name,email,marketing_opt_in FROM customers WHERE customer_id=?');$q->execute([$job['customerId']]);$customer=$q->fetch(); // P2
            if (!$customer || (int)$customer['marketing_opt_in']!==1) { // P3
                $reason=$customer ? 'marketing_consent_absent' : 'customer_not_found';
                $this->history->save($job,'SKIPPED',['reason'=>$reason]);
                $this->logger->record('skipped',$meta+['reason'=>$reason]);return;
            }
            // No transaction is held while waiting for SMTP. Single worker only.
            if ($this->delaySeconds>0) sleep($this->delaySeconds);
            $messageId=$this->sender->send($customer,$job); // P4
            $this->history->save($job,'SENT',['recipient_email'=>$customer['email'],'provider'=>'Mailtrap Sandbox','message_id'=>$messageId,'sent_at'=>gmdate('Y-m-d H:i:s')]); // P5
            $this->logger->record('sent',$meta+['mimeMessageId'=>$messageId]); // M2: after storage
        } catch (\Throwable $e) {
            // Save only a safe diagnostic category; provider messages may contain PII.
            // Never turn an already-stored terminal result into FAILED if logging fails.
            if (!$this->history->isComplete($job['campaignId'],$job['customerId'])) $this->history->save($job,'FAILED',['reason'=>get_class($e)]);
            throw $e;
        }
    }
}
