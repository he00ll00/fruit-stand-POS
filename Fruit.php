<?php
class Fruit {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addFruit($fruit_name, $price_per_piece, $price_per_kg, $stock_qty, $unit_type, $category_id = null, $image_path = 'images/default.png') {
        
        $check = $this->conn->prepare("SELECT fruit_id, stock_qty FROM fruit WHERE LOWER(fruit_name) = LOWER(?) LIMIT 1");
        if (!$check) {
            die('Prepare failed (check existing fruit): ' . $this->conn->error);
        }
        $check->bind_param("s", $fruit_name);
        $check->execute();
        $result = $check->get_result();

        if ($row = $result->fetch_assoc()) {
            $existing_id = $row['fruit_id'];
            $new_stock = $row['stock_qty'] + $stock_qty;

            $update = $this->conn->prepare("UPDATE fruit SET stock_qty = ?, price_per_piece = ?, price_per_kg = ?, category_id = ?, unit_type = ? WHERE fruit_id = ?");
            if (!$update) {
                die('Prepare failed (update stock): ' . $this->conn->error);
            }
            $update->bind_param("dddisi", $new_stock, $price_per_piece, $price_per_kg, $category_id, $unit_type, $existing_id);
            $update->execute();
            $update->close();
            $check->close();
            return "UPDATED_STOCK";
        }

        $check->close();

        if ($category_id !== null) {
            $sql = "INSERT INTO fruit (fruit_name, price_per_piece, price_per_kg, stock_qty, category_id, unit_type, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                die('Prepare failed (insert fruit with category): ' . $this->conn->error);
            }
            $stmt->bind_param("sdddiss", $fruit_name, $price_per_piece, $price_per_kg, $stock_qty, $category_id, $unit_type, $image_path);
        } else {
            $sql = "INSERT INTO fruit (fruit_name, price_per_piece, price_per_kg, stock_qty, unit_type, image_path) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                die('Prepare failed (insert fruit no category): ' . $this->conn->error);
            }
            $stmt->bind_param("sdddss", $fruit_name, $price_per_piece, $price_per_kg, $stock_qty, $unit_type, $image_path);
        }

        if (!$stmt->execute()) {
            die('Execute failed (addFruit): ' . $stmt->error);
        }
        $stmt->close();
        return "ADDED_NEW";
    }

    public function getAllFruits() {
        $sql = "SELECT fruit_id, fruit_name, price_per_piece, price_per_kg, stock_qty, image_path, unit_type FROM fruit ORDER BY fruit_id ASC";
        $result = $this->conn->query($sql);
        if (!$result) {
            die('Query failed: ' . $this->conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getFruit($fruit_id) {
        $stmt = $this->conn->prepare("SELECT * FROM fruit WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('i', $fruit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function updateStock($fruit_id, $newStock) {
        $stmt = $this->conn->prepare("UPDATE fruit SET stock_qty = ? WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('di', $newStock, $fruit_id);
        if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
        $stmt->close();
        return true;
    }

    public function updatePrice($fruit_id, $price) {
        $stmt = $this->conn->prepare("UPDATE fruit SET price_per_piece = ? WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('di', $price, $fruit_id);
        if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
        $stmt->close();
        return true;
    }

    public function updatePriceKg($fruit_id, $price) {
        $stmt = $this->conn->prepare("UPDATE fruit SET price_per_kg = ? WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('di', $price, $fruit_id);
        if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
        $stmt->close();
        return true;
    }

    public function updateImage($fruit_id, $image_path) {
        $stmt = $this->conn->prepare("UPDATE fruit SET image_path = ? WHERE fruit_id = ?");
        if (!$stmt) die('Prepare failed: ' . $this->conn->error);
        $stmt->bind_param('si', $image_path, $fruit_id);
        if (!$stmt->execute()) die('Execute failed: ' . $stmt->error);
        $stmt->close();
        return true;
    }
}
?>
