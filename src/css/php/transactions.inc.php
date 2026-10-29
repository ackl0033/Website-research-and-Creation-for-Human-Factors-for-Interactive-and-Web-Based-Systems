<?php
// Utilities for token transaction logging

function ensure_transactions_table(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS token_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        counterparty_id INT NULL,
        amount INT NOT NULL,
        direction ENUM('credit','debit') NOT NULL,
        type VARCHAR(50) NOT NULL,
        message_id INT NULL,
        request_id INT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (counterparty_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @$conn->query($sql); // suppress hard failure; non-critical if creation fails
}

function log_transaction(mysqli $conn, int $userId, ?int $counterpartyId, int $amount, string $direction, string $type, ?int $messageId = null, ?int $requestId = null, ?string $description = null): void {
    $stmt = $conn->prepare("INSERT INTO token_transactions (user_id, counterparty_id, amount, direction, type, message_id, request_id, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iiississ', $userId, $counterpartyId, $amount, $direction, $type, $messageId, $requestId, $description);
        @$stmt->execute();
        $stmt->close();
    }
}

?>
