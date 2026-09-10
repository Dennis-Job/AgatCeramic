param(
    [Parameter(Mandatory = $true)]
    [switch] $IUnderstandThisDestroysCiTestDatabase,
    [string] $HostName = '127.0.0.1',
    [int] $Port = 5432,
    [string] $UserName = 'agatceramic',
    [Parameter(Mandatory = $true)]
    [string] $Password
)

$ErrorActionPreference = 'Stop'

if (-not $IUnderstandThisDestroysCiTestDatabase) {
    throw 'Pass -IUnderstandThisDestroysCiTestDatabase to run destructive migrations only against agatceramic_test.'
}

if ($HostName -notin @('127.0.0.1', 'localhost')) {
    throw 'The local runner accepts only localhost; use the GitHub Actions PostgreSQL service for remote CI.'
}

if (-not (php -m | Select-String -Quiet '^pdo_pgsql$')) {
    throw 'The active PHP runtime has no pdo_pgsql extension.'
}

$env:CI = 'true'
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = $HostName
$env:DB_PORT = "$Port"
$env:DB_DATABASE = 'agatceramic_test'
$env:DB_USERNAME = $UserName
$env:DB_PASSWORD = $Password
$env:DB_SSLMODE = 'prefer'

php artisan config:clear
php artisan migrate:fresh --force
vendor\bin\phpunit --configuration=phpunit.postgres.xml tests\Integration\PostgresAuditLogImmutabilityTest.php
vendor\bin\phpunit --configuration=phpunit.postgres.xml tests\Integration\PostgresCatalogConcurrencyTest.php
