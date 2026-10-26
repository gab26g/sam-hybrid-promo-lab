<?php
declare(strict_types=1);
namespace Lab;
use PDO;

final class EmailHistory
{
    public function __construct(private PDO $db) {}
    public function find(string $campaignId, string $customerId): ?array
    {
        $q=$this->db->prepare('SELECT * FROM sent_marketing_emails WHERE campaign_id=? AND customer_id=?');$q->execute([$campaignId,$customerId]);return $q->fetch() ?: null;
    }
    public function isComplete(string $campaignId, string $customerId): bool
    {
        return in_array($this->find($campaignId,$customerId)['status'] ?? null,['SENT','SKIPPED'],true);
    }
    public function save(array $job, string $status, array $details=[]): void
    {
        if (!in_array($status,['SENT','SKIPPED','FAILED'],true)) throw new \InvalidArgumentException('Invalid history status');
        $fields=['request_id'=>$job['requestId'],'promo_code'=>$job['promoCode'],'recipient_email'=>$details['recipient_email']??null,'subject'=>$job['subject'],
            'status'=>$status,'provider'=>$details['provider']??null,'message_id'=>$details['message_id']??null,'reason'=>$details['reason']??null,
            'processed_at'=>gmdate('Y-m-d H:i:s'),'sent_at'=>$details['sent_at']??null];
        if ($this->find($job['campaignId'],$job['customerId'])!==null) {
            $set=implode(',',array_map(fn($k)=>"$k=?",array_keys($fields)));
            $q=$this->db->prepare("UPDATE sent_marketing_emails SET $set WHERE campaign_id=? AND customer_id=?");$q->execute([...array_values($fields),$job['campaignId'],$job['customerId']]);
        } else {
            $fields=['campaign_id'=>$job['campaignId'],'customer_id'=>$job['customerId']]+$fields;
            $q=$this->db->prepare('INSERT INTO sent_marketing_emails ('.implode(',',array_keys($fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');$q->execute(array_values($fields));
        }
    }
}
