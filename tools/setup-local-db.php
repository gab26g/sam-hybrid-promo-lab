<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
// Local administration only. Password is generated and written privately, never printed.
$port=getenv('DB_PORT') ?: '3306';
$adminPath=dirname(__DIR__).'/.local/mysql-admin.json';
$admin=is_file($adminPath)?json_decode(file_get_contents($adminPath),true,512,JSON_THROW_ON_ERROR):[];
$db=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",getenv('DB_ADMIN_USER')?:'root',getenv('DB_ADMIN_PASSWORD')?:($admin['password']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$path=dirname(__DIR__).'/.local/environment.json';
$settings=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
$password=$settings['DB_PASSWORD']??bin2hex(random_bytes(24));
foreach (['marketing_lab','marketing_lab_test'] as $name) $db->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$quoted=$db->quote($password);
$db->exec("CREATE USER IF NOT EXISTS 'marketing_lab'@'127.0.0.1' IDENTIFIED BY $quoted");
foreach (['marketing_lab','marketing_lab_test'] as $name) $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, REFERENCES, INDEX ON `$name`.* TO 'marketing_lab'@'127.0.0.1'");
$settings=array_merge($settings,['DB_HOST'=>'127.0.0.1','DB_PORT'=>$port,'DB_NAME'=>'marketing_lab','DB_USER'=>'marketing_lab','DB_PASSWORD'=>$password,'AWS_REGION'=>'ap-southeast-2','MAILTRAP_HOST'=>'sandbox.smtp.mailtrap.io','MAILTRAP_PORT'=>'2525','MAIL_FROM_ADDRESS'=>'marketing@example.com','MAILTRAP_DELAY_SECONDS'=>'11']);
if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
file_put_contents($path,json_encode($settings,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
if ($port==='3308' && !isset($admin['password'])) {
    $adminPassword=bin2hex(random_bytes(24));
    // This is only the fresh, isolated portable MySQL instance, never XAMPP.
    file_put_contents($adminPath,json_encode(['password'=>$adminPassword],JSON_THROW_ON_ERROR));
    $db->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$db->quote($adminPassword));
}
echo 'Local database and restricted lab account ready; server version: '.$db->getAttribute(PDO::ATTR_SERVER_VERSION).". Private settings saved; password not displayed.\n";
