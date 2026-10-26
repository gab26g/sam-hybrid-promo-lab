<?php
declare(strict_types=1);
namespace Lab;
use PDO;
use Aws\Sqs\SqsClient;

final class QueuePublisher
{
    public function __construct(private PDO $db, private SqsClient $sqs, private string $queueUrl, private ActivityLogger $logger) {}
    public function publishPending(): int
    {
        $rows=$this->db->query("SELECT * FROM marketing_outbox WHERE status='PENDING' ORDER BY created_at,request_id")->fetchAll();
        $update=$this->db->prepare("UPDATE marketing_outbox SET status='QUEUED',queued_at=?,sqs_message_id=? WHERE request_id=? AND status='PENDING'");
        $count=0;
        foreach ($rows as $row) {
            $job=['requestId'=>$row['request_id'],'campaignId'=>$row['campaign_id'],'customerId'=>$row['customer_id'],'promoCode'=>$row['promo_code'],'subject'=>$row['subject'],'body'=>$row['body']];
            $meta=array_intersect_key($job,array_flip(['requestId','campaignId','customerId']));
            try {
                $result=$this->sqs->sendMessage(['QueueUrl'=>$this->queueUrl,'MessageBody'=>json_encode($job,JSON_THROW_ON_ERROR)]); // Q1
                $id=$result['MessageId'] ?? null;
                if (!is_string($id) || $id==='') throw new \RuntimeException('SQS did not return an acknowledgment identifier');
                $update->execute([gmdate('Y-m-d H:i:s'),$id,$row['request_id']]); // Q2
                $this->logger->record('queued',$meta+['messageId'=>$id]); // M1
                $count++;
            } catch (\Throwable $e) {
                $this->logger->record('enqueue_failed',$meta+['phase'=>'publish','errorClass'=>get_class($e)]);
                throw $e;
            }
        }
        return $count;
    }
}
