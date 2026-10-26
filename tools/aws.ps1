$ErrorActionPreference = 'Stop'
$labRoot = Split-Path $PSScriptRoot -Parent
$python = 'C:\Users\Gab\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe'
if (!(Test-Path -LiteralPath $python)) { throw 'Use an installed AWS CLI, or update the Python path in tools/aws.ps1.' }
$oldPythonPath = $env:PYTHONPATH
try {
    $env:PYTHONPATH = Join-Path $labRoot '.local/awscli'
    & $python -m awscli @args
    $result = $LASTEXITCODE
} finally { $env:PYTHONPATH = $oldPythonPath }
exit $result
