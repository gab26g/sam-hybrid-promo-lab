<?php
declare(strict_types=1);
namespace Lab;
use Aws\Sqs\SqsClient;

final class Settings
{
    public static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') throw new \RuntimeException("Missing environment variable: $name");
        return $value;
    }
    public static function queue(): SqsClient
    {
        return new SqsClient(['region'=>self::required('AWS_REGION'), 'version'=>'2012-11-05']);
    }
    public static function smtp(): array
    {
        return ['host'=>self::required('MAILTRAP_HOST'), 'port'=>self::integer('MAILTRAP_PORT',2525,1,65535),
            'username'=>self::required('MAILTRAP_USERNAME'), 'password'=>self::required('MAILTRAP_PASSWORD'),
            'from'=>getenv('MAIL_FROM_ADDRESS') ?: 'marketing@example.com'];
    }
    public static function integer(string $name, int $default, int $min, int $max): int
    {
        $value=getenv($name);
        if ($value===false || $value==='') return $default;
        $parsed=filter_var($value,FILTER_VALIDATE_INT);
        if ($parsed===false || $parsed<$min || $parsed>$max) throw new \InvalidArgumentException("Invalid $name");
        return $parsed;
    }
}
