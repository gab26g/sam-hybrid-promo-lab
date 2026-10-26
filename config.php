<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
date_default_timezone_set('UTC');
putenv('AWS_EC2_METADATA_DISABLED=true');
// Optional private local storage, excluded from Git. Explicit environment wins.
$local=__DIR__.'/.local/environment.json';
if (is_file($local)) {
    foreach (json_decode(file_get_contents($local),true,512,JSON_THROW_ON_ERROR) as $name=>$value) {
        if (getenv($name)===false) putenv($name.'='.$value);
    }
}
set_exception_handler(static function(Throwable $e): void {
    // Never print provider messages, connection strings, or credentials.
    fwrite(STDERR,'Operation failed ('.get_class($e).'). Check configuration and local logs; no credential values were printed.'.PHP_EOL);
    exit(1);
});
