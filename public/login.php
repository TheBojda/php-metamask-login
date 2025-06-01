<?php
require '../vendor/autoload.php';

use SWeb3\Accounts;

// Get the session ID from the query parameter
$sid = null;
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    if (isset($parts[0]) && $parts[0] !== '') {
        $sid = $parts[0];
    }
}
$challenge = null;
$error = null;

if ($sid) {
    try {
        $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/metamask_sessions.db');
        $stmt = $pdo->prepare('SELECT challenge FROM sessions WHERE session_id = :sid');
        $stmt->execute([':sid' => $sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $challenge = $row['challenge'];
        }
    } catch (Exception $e) {
        $error = 'Database error.';
    }
} else {
    $error = 'No session ID provided.';
}

$ethAddress = null;
if (isset($_GET['signature']) && $challenge) {
    $signature = $_GET['signature'];
    try {
        $ethAddress = Accounts::signedMessageToAddress($challenge, $signature);

        $db = new PDO('sqlite:' . dirname(__DIR__) . '/metamask_sessions.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare('SELECT expiry FROM sessions WHERE session_id = :sid');
        $stmt->execute([':sid' => $sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['expiry'])) {
            $expiresAt = strtotime($row['expiry']);
            if ($expiresAt !== false && $expiresAt < time()) {
                $ethAddress = null;
                $error = 'Your session has expired.';
            }
        }

        if ($ethAddress) {
            $update = $db->prepare('UPDATE sessions SET eth_address = :eth_address WHERE session_id = :sid');
            $update->execute([
                ':eth_address' => $ethAddress,
                ':sid' => $sid
            ]);
        }
    } catch (Exception $e) {
        $error = 'Signature verification failed.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login Challenge</title>
    <style>
        body,
        html {
            height: 100%;
            margin: 0;
        }

        .centered {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-size: 1.5em;
        }
    </style>
    <?php if (!isset($_GET['signature'])): ?>
        <script src="https://unpkg.com/@metamask/detect-provider/dist/detect-provider.min.js"></script>
        <script>
            async function main() {
                const provider = await detectEthereumProvider();

                if (!provider) {
                    alert('This page should be opened in the MetaMask mobile app.');
                    return;
                }

                const challenge = <?php echo json_encode($challenge); ?>;
                const accounts = await provider.request({
                    method: 'eth_requestAccounts'
                });
                const from = accounts[0];

                try {
                    const signature = await provider.request({
                        method: 'personal_sign',
                        params: [challenge, from],
                    });
                    // Redirect to the same URL with the signature as a query parameter
                    const url = new URL(window.location.href);
                    url.searchParams.set('signature', signature);
                    window.location.href = url.toString();
                } catch (err) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('signature', 'invalid');
                    window.location.href = url.toString();
                }
            }
            window.addEventListener('DOMContentLoaded', main)
        </script>
    <?php endif; ?>
</head>

<body>
    <div class="centered">
        <div style="text-align: center; width: 100%;">
            <?php if ($ethAddress): ?>
                <div>
                    <p>Your Ethereum address is:</p>
                    <pre style="display: inline-block; text-align: left;"><b><?php echo htmlspecialchars($ethAddress); ?></b></pre>
                    <p>Check the login page. It will automatically refresh and you will be logged in.</p>
                </div>
            <?php elseif ($sid && $challenge && !$error): ?>
                <div>
                    <p>Your challenge is:</p>
                    <pre style="display: inline-block; text-align: left;"><b><?php echo htmlspecialchars($challenge); ?></b></pre>
                    <p>Please use MetaMask to sign this challenge for login.</p>
                </div>
            <?php else: ?>
                <div>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>