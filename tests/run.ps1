$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$phpBinary = if ($env:PHP_BINARY) { $env:PHP_BINARY } else { 'C:/xampp/php/php.exe' }
Push-Location -LiteralPath $projectRoot
try {
    foreach ($test in @('security_test.php','passwords_test.php','money_test.php','output_test.php','uploads_test.php')) {
        & $phpBinary (Join-Path $PSScriptRoot $test)
        if ($LASTEXITCODE -ne 0) { throw "Failed: $test" }
    }
    # Verify actual decode/re-encode too when the XAMPP GD DLL is available.
    if (Test-Path -LiteralPath 'C:/xampp/php/ext/php_gd.dll') {
        & $phpBinary -d extension=gd tests/uploads_test.php
        if ($LASTEXITCODE -ne 0) { throw 'GD image reconstruction failed' }
    }
    foreach ($test in @('http_security_test.mjs','v2_integration_test.mjs','apache_security_test.mjs')) {
        node (Join-Path $PSScriptRoot $test)
        if ($LASTEXITCODE -ne 0) { throw "Failed: $test" }
    }
    $phpFiles = Get-ChildItem -Recurse -Filter '*.php' -File | Where-Object { $_.FullName -notlike '*\delivery\*' }
    foreach ($file in $phpFiles) {
        $result = & $phpBinary -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) { throw ($result -join "`n") }
    }
    Write-Output "PASS: all security suites and syntax checks ($($phpFiles.Count) PHP files)"
} finally { Pop-Location }
