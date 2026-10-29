<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../inc/dbconn.inc.php';
require_once __DIR__ . '/transactions.inc.php';

$user_id = $_SESSION['user_id'];
ensure_transactions_table($conn);

$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

$stmt = $conn->prepare("SELECT t.*, u.username AS counterparty_username
                        FROM token_transactions t
                        LEFT JOIN users u ON u.id = t.counterparty_id
                        WHERE t.user_id = ?
                        ORDER BY t.created_at DESC, t.id DESC
                        LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $user_id, $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$row['id'],
        'amount' => (int)$row['amount'],
        'direction' => $row['direction'],
        'type' => $row['type'],
        'counterparty_id' => $row['counterparty_id'] ? (int)$row['counterparty_id'] : null,
        'counterparty_username' => $row['counterparty_username'] ?: null,
        'message_id' => $row['message_id'] ? (int)$row['message_id'] : null,
        'request_id' => $row['request_id'] ? (int)$row['request_id'] : null,
        'description' => $row['description'] ?: null,
        'created_at' => $row['created_at'],
    ];
}

echo json_encode($rows);
