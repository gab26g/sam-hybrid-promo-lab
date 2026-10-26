<?php
require __DIR__.'/config.php';
$db=(new Lab\Database())->connect();$logger=new Lab\ActivityLogger();
$processor=new Lab\MarketingProcessor($db,new Lab\EmailHistory($db),new Lab\EmailSender(Lab\Settings::smtp()),$logger,Lab\Settings::integer('MAILTRAP_DELAY_SECONDS',11,2,60));
$worker=new Lab\QueueWorker(Lab\Settings::queue(),Lab\Settings::required('SQS_QUEUE_URL'),$processor,$logger);
$polls=isset($argv[1]) ? filter_var($argv[1],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : 0;
if ($polls===false) {fwrite(STDERR,"Usage: php worker.php [positive-max-polls]\n");exit(2);}
echo "One worker started. Follow logs/events.jsonl in a second terminal. Ctrl+C stops the worker.\n";
$worker->run($polls);
