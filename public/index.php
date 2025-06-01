<?php
require '../vendor/autoload.php';

use Dotenv\Dotenv;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

session_start();

$db = new PDO('sqlite:' . dirname(__DIR__) . '/metamask_sessions.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if it doesn't exist
$db->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            session_id TEXT PRIMARY KEY,
            challenge TEXT,
            expiry DATETIME,
            eth_address TEXT DEFAULT NULL
        )
");

if (!isset($_SESSION['metamask_session'])) {
    $_SESSION['metamask_session'] = createMetaMaskSession();
} else {
    $stmt = $db->prepare("SELECT expiry FROM sessions WHERE session_id = :session_id");
    $stmt->execute([':session_id' => $_SESSION['metamask_session']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Session not found in DB, create new
        $_SESSION['metamask_session'] = createMetaMaskSession();
    } else {
        $expiry = DateTime::createFromFormat('Y-m-d H:i:s', $row['expiry']);
        $now = new DateTime();
        $interval = $now->diff($expiry);
        $minutesLeft = ($expiry > $now) ? ($interval->days * 24 * 60 + $interval->h * 60 + $interval->i) : -1;

        if ($expiry < $now || $minutesLeft <= 2) {
            $_SESSION['metamask_session'] = createMetaMaskSession();
        }
    }
}

$ethAddress = null;
$stmt = $db->prepare("SELECT eth_address FROM sessions WHERE session_id = :session_id");
$stmt->execute([':session_id' => $_SESSION['metamask_session']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty($row['eth_address'])) {
    $ethAddress = $row['eth_address'];
}

function createMetaMaskSession()
{
    global $db;

    // Generate random session ID and challenge
    $sessionId = bin2hex(random_bytes(16)); // 32-char hex string
    $challenge = generateChallenge(8); // 8 uppercase letters
    $expiry = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    // Insert into database
    $stmt = $db->prepare("
        INSERT INTO sessions (session_id, challenge, expiry)
        VALUES (:session_id, :challenge, :expiry)
    ");
    $stmt->execute([
        ':session_id' => $sessionId,
        ':challenge'  => $challenge,
        ':expiry'     => $expiry,
    ]);

    return $sessionId;
}

function generateChallenge($length = 8)
{
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $challenge = '';
    for ($i = 0; $i < $length; $i++) {
        $challenge .= $letters[random_int(0, 25)];
        if ($i == 3 && $length == 8) {
            $challenge .= ' ';
        }
    }
    return $challenge;
}

$sessionId = $_SESSION['metamask_session'];
?>

<!DOCTYPE html>
<html>

<head>
    <title>MetaMask Session</title>
    <script>
        setInterval(function() {
            window.location.reload();
        }, 10000);
    </script>
</head>

<body>
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 80vh;">
        <?php if ($ethAddress): ?>
            <h1>Logged in with MetaMask</h1>
            <p>Ethereum Address: <code><?= htmlspecialchars($ethAddress) ?></code></p>
        <?php else: ?>
            <h1>Your MetaMask Session</h1>
            <p>Session ID: <code><?= htmlspecialchars($sessionId) ?></code></p>
            <?php
            $siteHost = $_ENV['SITE_HOST'] ?? 'localhost';
            $url = "https://metamask.app.link/dapp/{$siteHost}/login.php/{$sessionId}";

            $builder = new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: $url,
                size: 300,
                margin: 10,
            );

            $result = $builder->build();

            header('Content-Type: text/html; charset=utf-8');
            $qrImage = base64_encode($result->getString());
            ?>
            <p>Scan this QR code with MetaMask mobile:</p>
            <img src="data:image/png;base64,<?= $qrImage ?>" alt="MetaMask QR Code" />
            <p><a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a></p>
        <?php endif; ?>
    </div>
</body>

</html>