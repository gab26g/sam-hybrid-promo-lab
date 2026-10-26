<?php
declare(strict_types=1);
namespace Lab;
use Aws\Sqs\SqsClient;

final class QueueWorker
{
    public function __construct(private SqsClient $sqs, private string $queueUrl, private MarketingProcessor $processor, private ActivityLogger $logger) {}
    public function handle(array $message): bool
    {
        $meta=['messageId'=>$message['MessageId']??'unknown','receiveCount'=>$message['Attributes']['ApproximateReceiveCount']??null];
        $phase='decode';
        try {
            $this->logger->record('received',$meta);
            $job=json_decode($message['Body']??'',true,512,JSON_THROW_ON_ERROR);
            if (!is_array($job) || array_is_list($job)) throw new \InvalidArgumentException('Job must be a JSON object');
            foreach (['requestId','campaignId','customerId'] as $key) if (isset($job[$key]) && is_string($job[$key])) $meta[$key]=$job[$key];
            $phase='process';$this->processor->process($job); // W1
            $phase='delete'; // M3: distinguish acknowledgment failures
            if (empty($message['ReceiptHandle'])) throw new \InvalidArgumentException('Missing ReceiptHandle');
            $this->sqs->deleteMessage(['QueueUrl'=>$this->queueUrl,'ReceiptHandle'=>$message['ReceiptHandle']]); // W2
            $this->logger->record('deleted',$meta); // M3: after acknowledgment
            return true;
        } catch (\Throwable $e) {
            $this->logger->record($phase==='delete'?'ack_failed':'processing_failed',$meta+['phase'=>$phase,'errorClass'=>get_class($e)]);
            return false;
        }
    }
    public function run(int $maxPolls=0): void
    {
        for ($poll=0;$maxPolls===0 || $poll<$maxPolls;$poll++) {
            try {
                // One message at a time avoids expiring a batch while SMTP is throttled.
                $result=$this->sqs->receiveMessage(['QueueUrl'=>$this->queueUrl,'MaxNumberOfMessages'=>1,'WaitTimeSeconds'=>20,'VisibilityTimeout'=>180,'MessageSystemAttributeNames'=>['ApproximateReceiveCount']]);
                foreach ($result['Messages']??[] as $message) $this->handle($message);
            } catch (\Throwable $e) {
                $this->logger->record('processing_failed',['phase'=>'receive','errorClass'=>get_class($e)]);
                throw $e;
            }
        }
    }
}
