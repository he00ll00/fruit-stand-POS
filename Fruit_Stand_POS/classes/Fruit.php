<?php
class Fruit {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensure3NFSchema();
    }

    public function addFruit($fruit_name, $price_per_piece, $price_per_kg, $stock_qty, $unit_type, $category_id = null, $image_path = 'images/default.png') {
        
        $check = $this->conn->prepare("SELECT fruit_id, unit_type FROM fruit WHERE LOWER(fruit_name) = LOWER(?) LIMIT 1");
        if (!$check) {
            die('Prepare failed (check existing fruit): ' . $this->conn->error);
        }
        $check->bind_param("s", $fruit_name);
        $check->execute();
        $result = $check->get_result();

        if ($row = $result->fetch_assoc()) {
            $existing_id = $row['fruit_id'];
            // update 
            $update = $this->conn->prepare("UPDATE fruit SET category_id = ?, unit_type = ?, image_path = ? WHERE fruit_id = ?");
            if (!$update) { die('Prepare failed (update fruit): ' . $this->conn->error); }
            $update->bind_param("issi", $category_id, $unit_type, $image_path, $existing_id);
            if (!$update->execute()) { die('Execute failed (update fruit): ' . $update->error); }
            $update->close();

            // restock by adding to inventory 
            if ($stock_qty > 0) {
                $this->insertInventoryTxn($existing_id, $unit_type, $stock_qty, 'restock', 'manual', null, 'Initial/Additional stock on addFruit');
            }

            // update prices 
            if ($price_per_piece > 0) { $this->setPrice($existing_id, 'piece', $price_per_piece); }
            if ($price_per_kg > 0) { $this->setPrice($existing_id, 'kg', $price_per_kg); }
            $check->close();
            return "UPDATED_STOCK";
        }

        $check->close();

        $sql = "INSERT INTO fruit (fruit_name, unit_type, category_id, image_path) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { die('Prepare failed (insert fruit): ' . $this->conn->error); }
        $stmt->bind_param("ssis", $fruit_name, $unit_type, $category_id, $image_path);

        if (!$stmt->execute()) { die('Execute failed (addFruit): ' . $stmt->error); }
        $newFruitId = $this->conn->insert_id;
        $stmt->close();
        // initial stock 
        if ($stock_qty > 0) {
            $this->insertInventoryTxn($newFruitId, $unit_type, $stock_qty, 'restock', 'manual', null, 'Initial stock on addFruit');
        }
        // initial price
        if ($price_per_piece > 0) { $this->setPrice($newFruitId, 'piece', $price_per_piece); }
        if ($price_per_kg > 0) { $this->setPrice($newFruitId, 'kg', $price_per_kg); }
        return "ADDED_NEW";
    }

    public function getAllFruits() {
        $where = '';
        if ($this->columnExists('fruit', 'is_active')) {
            $where = 'WHERE is_active = 1';
        }
        $sql = "SELECT fruit_id, fruit_name, image_path, unit_type FROM fruit $where ORDER BY fruit_id ASC";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $fid = (int)$r['fruit_id'];
            $out[] = [
                'fruit_id' => $fid,
                'fruit_name' => $r['fruit_name'],
                'price_per_piece' => $this->getCurrentPrice($fid, 'piece'),
                'price_per_kg' => $this->getCurrentPrice($fid, 'kg'),
                'stock_qty' => $this->getStock($fid),
                'image_path' => $r['image_path'],
                'unit_type' => $r['unit_type']
            ];
        }
        return $out;
    }

    public function getFruit($fruit_id) {
        $stmt = $this->conn->prepare("SELECT fruit_id, fruit_name, image_path, unit_type FROM fruit WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('i', $fruit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) { return null; }
        $fid = (int)$row['fruit_id'];
        $row['price_per_piece'] = $this->getCurrentPrice($fid, 'piece');
        $row['price_per_kg'] = $this->getCurrentPrice($fid, 'kg');
        $row['stock_qty'] = $this->getStock($fid);
        return $row;
    }

    public function updateStock($fruit_id, $newStock) {
        $fid = intval($fruit_id);
        $current = $this->getStock($fid);
        $delta = floatval($newStock) - floatval($current);
        if (abs($delta) < 1e-9) { return true; }
        
        $fruit = $this->getFruit($fid);
        $unit = $fruit ? ($fruit['unit_type'] === 'kg' ? 'kg' : 'piece') : 'piece';
        $reason = ($delta < 0) ? 'sale' : 'restock';
        return $this->insertInventoryTxn($fid, $unit, $delta, $reason, ($reason==='sale'?'sale':'manual'), null, 'updateStock delta transaction');
    }

    public function updatePrice($fruit_id, $price) {
        return $this->setPrice($fruit_id, 'piece', $price);
    }

    public function updatePriceKg($fruit_id, $price) {
        return $this->setPrice($fruit_id, 'kg', $price);
    }

    public function updateImage($fruit_id, $image_path) {
        $stmt = $this->conn->prepare("UPDATE fruit SET image_path = ? WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('si', $image_path, $fruit_id);
        if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
        $stmt->close();
        return true;
    }

    public function deleteFruit($fruit_id) {
        $fid = intval($fruit_id);
        // fetch image path
        $stmt0 = $this->conn->prepare("SELECT image_path FROM fruit WHERE fruit_id = ?");
        if (!$stmt0) die('Prepare failed: ' . $this->conn->error);
        $stmt0->bind_param('i', $fid);
        $stmt0->execute();
        $res0 = $stmt0->get_result();
        $row0 = $res0 ? $res0->fetch_assoc() : null;
        $stmt0->close();

        if ($this->columnExists('fruit', 'is_active')) {
            $stmt = $this->conn->prepare("UPDATE fruit SET is_active = 0 WHERE fruit_id = ?");
            if (!$stmt) die('Prepare failed: ' . $this->conn->error);
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
            $stmt->close();
        } else {
            $stmt = $this->conn->prepare("DELETE FROM fruit WHERE fruit_id = ?");
            if (!$stmt) die('Prepare failed: ' . $this->conn->error);
            $stmt->bind_param('i', $fid);
            if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
            $stmt->close();
        }

        $img = $row0 ? ($row0['image_path'] ?? null) : null;
        if ($img && $img !== 'images/default.png') {
            $abs = __DIR__ . '/../' . $img;
            if (is_file($abs)) { @unlink($abs); }
        }
        return true;
    }

    private function getCurrentPrice($fruit_id, $unit) {
        $stmt = $this->conn->prepare("SELECT price FROM fruit_price WHERE fruit_id = ? AND unit = ? AND (effective_to IS NULL) ORDER BY effective_from DESC LIMIT 1");
        if (!$stmt) die('Prepare failed (getCurrentPrice): ' . $this->conn->error);
        $stmt->bind_param('is', $fruit_id, $unit);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (float)$row['price'] : 0.0;
    }

    private function setPrice($fruit_id, $unit, $price) {
        $fid = intval($fruit_id);
        $unit = ($unit === 'kg') ? 'kg' : 'piece';
    
        $stmt = $this->conn->prepare("UPDATE fruit_price SET effective_to = NOW() WHERE fruit_id = ? AND unit = ? AND effective_to IS NULL");
        if (!$stmt) die('Prepare failed (close price): ' . $this->conn->error);
        $stmt->bind_param('is', $fid, $unit);
        if (!$stmt->execute()) die('Execute failed (close price): ' . $stmt->error);
        $stmt->close();
        // insert new
        $stmt2 = $this->conn->prepare("INSERT INTO fruit_price (fruit_id, unit, price, effective_from) VALUES (?, ?, ?, NOW())");
        if (!$stmt2) die('Prepare failed (insert price): ' . $this->conn->error);
        $stmt2->bind_param('isd', $fid, $unit, $price);
        if (!$stmt2->execute()) die('Execute failed (insert price): ' . $stmt2->error);
        $stmt2->close();
        return true;
    }

    public function getStock($fruit_id) {
        $fid = intval($fruit_id);
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity),0) AS stock FROM inventory_txn WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed (getStock): ' . $this->conn->error);
        $stmt->bind_param('i', $fid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (float)$row['stock'] : 0.0;
    }

    private function insertInventoryTxn($fruit_id, $unit, $qty, $reason, $refType = null, $refId = null, $note = null) {
        $stmt = $this->conn->prepare("INSERT INTO inventory_txn (fruit_id, unit, quantity, reason, reference_type, reference_id, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) die('Prepare failed (insertInventoryTxn): ' . $this->conn->error);
        $stmt->bind_param('isdssis', $fruit_id, $unit, $qty, $reason, $refType, $refId, $note);
        $ok = $stmt->execute();
        if (!$ok) die('Execute failed (insertInventoryTxn): ' . $stmt->error);
        $stmt->close();
        return true;
    }

    private function ensure3NFSchema() {
        
        $this->conn->query("CREATE TABLE fruit_price (
            price_id BIGINT PRIMARY KEY NOT NULL AUTO_INCREMENT,
            fruit_id INT NOT NULL,
            unit VARCHAR(10) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            effective_to DATETIME DEFAULT NULL,
            KEY ix_price_fruit_unit (fruit_id, unit, effective_from)
        );

        $this->conn->query("CREATE TABLE inventory_txn (
            txn_id BIGINT PRIMARY KEY NOT NULL AUTO_INCREMENT,
            fruit_id INT NOT NULL,
            unit VARCHAR(10) NOT NULL,
            quantity DECIMAL(10,3) NOT NULL,
            reference_id BIGINT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY ix_inv_fruit (fruit_id)
        );

        if (!$this->columnExists('fruit', 'is_active')) {
            $this->conn->query("ALTER TABLE fruit ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        }
    }

    private function columnExists($table, $column) {
        $table = $this->conn->real_escape_string($table);
        $column = $this->conn->real_escape_string($column);
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1";
        $res = $this->conn->query($sql);
        if ($res && $res->fetch_assoc()) return true;
        return false;
    }
}
?>

