<?php
require_once __DIR__ . '/../includes/Mailer.php';
class Notification {
    private $conn;
    private $threshold_piece = 2.0;
    private $threshold_kg = 1.0;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTable();
    }

    private function ensureTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notification (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";
        if (!$this->conn->query($sql)) {
            die('Failed to ensure notification table: ' . $this->conn->error);
        }
    }

    public function log($type, $message) {
        $stmt = $this->conn->prepare("INSERT INTO notification (type, message) VALUES (?, ?)");
        if (!$stmt) { die('Prepare failed (log notification): ' . $this->conn->error); }
        $stmt->bind_param('ss', $type, $message);
        if (!$stmt->execute()) { die('Execute failed (log notification): ' . $stmt->error); }
        $stmt->close();
        $this->sendEmailIfEnabled('POS Notification: ' . $type, $message);
        return true;
    }

    public function getRecent($limit = 5) {
        $limit = max(1, intval($limit));
        $sql = "SELECT id, type, message, created_at FROM notification ORDER BY id DESC LIMIT " . $limit;
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed (getRecent): ' . $this->conn->error); }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getAll() {
        $sql = "SELECT id, type, message, created_at FROM notification ORDER BY id DESC";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed (getAll): ' . $this->conn->error); }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function checkAndNotifyLowStock($fruit_id) {
        $stmt = $this->conn->prepare("SELECT f.fruit_name, f.unit_type, COALESCE(SUM(it.quantity),0) AS stock
                                       FROM fruit f
                                       LEFT JOIN inventory_txn it ON it.fruit_id = f.fruit_id
                                       WHERE f.fruit_id = ?
                                       GROUP BY f.fruit_id, f.fruit_name, f.unit_type");
        if (!$stmt) { die('Prepare failed (fetch fruit for low stock): ' . $this->conn->error); }
        $stmt->bind_param('i', $fruit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) { return false; }
        $stock = (float)$row['stock'];
        $unit_type = ($row['unit_type'] === 'kg') ? 'kg' : 'piece';
        $threshold = ($unit_type === 'kg') ? $this->threshold_kg : $this->threshold_piece;
        if ($stock < $threshold) {
            $msg = 'Low stock: ' . $row['fruit_name'] . ' (' . number_format($stock, 3) . ' remaining)';
            $this->log('low_stock', $msg);
            $this->pushToast($msg, 'warning');
            return true;
        }
        return false;
    }

    private function sendEmailIfEnabled($subject, $body) {
        $res = $this->conn->query("SELECT notify_email, notify_enabled FROM app_settings WHERE id=1 LIMIT 1");
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row && intval($row['notify_enabled']) === 1) {
                $to = $row['notify_email'];
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    sendEmail($to, $subject, $body, false);
                }
            }
        }
    }

    private function pushToast($text, $type = 'info') {
        if (!isset($_SESSION)) { session_start(); }
        if (!isset($_SESSION['toasts'])) { $_SESSION['toasts'] = []; }
        $_SESSION['toasts'][] = ['type' => $type, 'text' => $text];
    }
}
?>
