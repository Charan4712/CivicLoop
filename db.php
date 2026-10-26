
cd ~/CivicLoop

cat > db.php <<'EOF'
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'civicloop';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);
$dbCa = getenv('DB_SSL_CA') ?: '';

try {
    $conn = mysqli_init();

    if ($dbCa !== '') {
        $conn->ssl_set(null, null, $dbCa, null, null);
        $conn->real_connect(
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            $dbPort,
            null,
            MYSQLI_CLIENT_SSL
        );
    } else {
        $conn->real_connect(
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            $dbPort
        );
    }

    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    exit('Database connection failed. Check deployment configuration.');
}
