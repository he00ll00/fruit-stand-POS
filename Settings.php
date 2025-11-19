<?php
class Settings {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTable();
        $this->ensureRow();
    }

    private function ensureTable() {
        $sql = "CREATE TABLE IF NOT EXISTS app_settings (
            id INT PRIMARY KEY,
            notify_email VARCHAR(255) DEFAULT NULL,
            notify_enabled TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB";
        if (!$this->conn->query($sql)) {
            die('Failed to ensure app_settings table: ' . $this->conn->error);
        }
    }

    private function ensureRow() {
        $res = $this->conn->query("SELECT id FROM app_settings WHERE id=1 LIMIT 1");
        if ($res && $res->num_rows === 0) {
            $stmt = $this->conn->prepare("INSERT INTO app_settings (id, notify_email, notify_enabled) VALUES (1, NULL, 0)");
            if (!$stmt) { die('Prepare failed (ensureRow): ' . $this->conn->error); }
            if (!$stmt->execute()) { die('Execute failed (ensureRow): ' . $stmt->error); }
            $stmt->close();
        }
    }

    public function get() {
        $res = $this->conn->query("SELECT id, notify_email, notify_enabled FROM app_settings WHERE id=1 LIMIT 1");
        if (!$res) { die('Query failed (settings get): ' . $this->conn->error); }
        return $res->fetch_assoc();
    }

    public function update($email, $enabled) {
        $enabled = $enabled ? 1 : 0;
        $email = $email !== '' ? $email : null;
        $stmt = $this->conn->prepare("UPDATE app_settings SET notify_email = ?, notify_enabled = ? WHERE id = 1");
        if (!$stmt) { die('Prepare failed (settings update): ' . $this->conn->error); }
        $stmt->bind_param('si', $email, $enabled);
        if (!$stmt->execute()) { die('Execute failed (settings update): ' . $stmt->error); }
        $stmt->close();
        return true;
    }
}
?>
