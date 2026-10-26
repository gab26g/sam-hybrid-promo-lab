$ErrorActionPreference = 'Stop'
$labRoot = Split-Path $PSScriptRoot -Parent
$labMysql = Join-Path $labRoot '.local/mysql/mysql-8.4.13-winx64'
$labData = Join-Path $labRoot '.local/mysql-data'
if (!(Test-Path -LiteralPath (Join-Path $labMysql 'bin/mysqld.exe'))) { throw 'Portable MySQL is not installed. Use your own compliant local server and DB_* settings.' }
& (Join-Path $labMysql 'bin/mysqld.exe') --no-defaults "--basedir=$labMysql" "--datadir=$labData" --port=3308 --bind-address=127.0.0.1 --mysqlx=OFF --console
