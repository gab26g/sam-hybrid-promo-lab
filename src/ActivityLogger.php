<?php
declare(strict_types=1);
namespace Lab;

final class ActivityLogger
{
    private const ALLOWED = ['requestId','campaignId','customerId','messageId','mimeMessageId','phase','status','reason','errorClass','receiveCount','count'];
    public function __construct(private string $path=__DIR__.'/../logs/events.jsonl') {}
    public function record(string $event, array $metadata=[]): void
    {
        $dir=dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('Cannot create log directory');
        $safe=array_intersect_key($metadata,array_flip(self::ALLOWED));
        // Queue input is untrusted even when its keys are approved.
        foreach (['requestId'=>32,'campaignId'=>64,'customerId'=>32,'messageId'=>100,'mimeMessageId'=>255] as $field=>$limit) {
            if (!array_key_exists($field,$safe)) continue;
            $value=$safe[$field];
            $valid=is_string($value) && strlen($value)<=$limit;
            if ($field==='requestId') $valid=$valid && preg_match('/^[a-f0-9]{32}$/D',$value);
            elseif ($field!=='mimeMessageId') $valid=$valid && preg_match('/^[A-Za-z0-9_-]+$/D',$value);
            else $valid=$valid && !preg_match('/[\r\n]/',$value);
            if (!$valid) unset($safe[$field]);
        }
        $row=['timestamp'=>gmdate('Y-m-d\TH:i:s\Z'),'event'=>$event]+$safe;
        if (file_put_contents($this->path,json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX)===false) throw new \RuntimeException('Cannot write operational log');
    }
}
