
<?php
class Sale {
    private $conn;
    private $fruit;
    private $notification;

    public function __construct($db, $fruitObj, $notification = null) {
        $this->conn = $db;
        $this->fruit = $fruitObj;
        $this->notification = $notification;
    }



    public function getAllSales() {
        $sql = "SELECT 
                    s.sale_id,
                    s.sale_date,
                    s.total_amount,
                    s.custom_customer_name,
                    c.customer_name
                FROM sale s
                LEFT JOIN customer c ON s.customer_id = c.customer_id
                ORDER BY s.sale_id DESC";
        $result = $this->conn->query($sql);
        if (!$result) {
            die('Query failed: ' . $this->conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getSale($sale_id) {
        $sql = "SELECT 
                s.sale_id, 
                s.sale_date, 
                s.total_amount, 
                s.custom_customer_name,
                c.customer_name
            FROM sale s
            LEFT JOIN customer c ON s.customer_id = c.customer_id
            WHERE s.sale_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) die('Prepare failed (getSale): ' . $this->conn->error);
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function getSaleItems($sale_id) {
        $sql = "SELECT 
                    si.sale_item_id, 
                    f.fruit_name, 
                    si.quantity,
                    si.unit,
                    si.subtotal
                FROM sale_item si
                JOIN fruit f ON si.fruit_id = f.fruit_id
                WHERE si.sale_id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) die('Prepare failed (getSaleItems): ' . $this->conn->error);
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function createSaleFromCart($customer_id, $custom_customer_name, $items) {
        if (empty($items)) { return "ERROR"; }

        $this->conn->begin_transaction();
        try {
            $total = 0;
            $preparedItems = [];

            foreach ($items as $it) {
                $fruit_id = intval($it['fruit_id']);
                $qty = floatval($it['quantity']);
                $unitSel = isset($it['unit']) ? $it['unit'] : 'piece';
                if ($qty <= 0) { continue; }

                $fruit = $this->fruit->getFruit($fruit_id);
                if (!$fruit) { throw new Exception('Fruit not found'); }
                if ($qty > (float)$fruit['stock_qty']) { $this->conn->rollback(); return "NOT_ENOUGH_STOCK"; }

                $price = ($unitSel === 'kg') ? floatval($fruit['price_per_kg']) : floatval($fruit['price_per_piece']);
                $subtotal = $price * $qty;
                $total += $subtotal;
                $preparedItems[] = [
                    'fruit_id' => $fruit_id,
                    'quantity' => $qty,
                    'unit' => $unitSel,
                    'subtotal' => $subtotal,
                    'new_stock' => floatval($fruit['stock_qty']) - $qty
                ];
            }

            $customer_id = !empty($customer_id) ? $customer_id : null;
            if ($customer_id === null) {
                $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name)
                        VALUES (CURDATE(), ?, NULL, 1, ?)";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                $stmt->bind_param("ds", $total, $custom_customer_name);
            } else {
                $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name)
                        VALUES (CURDATE(), ?, ?, 1, ?)";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                $stmt->bind_param("dis", $total, $customer_id, $custom_customer_name);
            }

            if (!$stmt->execute()) { throw new Exception("Execute failed (insert Sale): " . $stmt->error); }
            $sale_id = $this->conn->insert_id;

            $itemSql = "INSERT INTO sale_item (sale_id, fruit_id, quantity, unit, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmtItem = $this->conn->prepare($itemSql);
            if (!$stmtItem) { throw new Exception("Prepare failed (insert Sale Item): " . $this->conn->error); }

            foreach ($preparedItems as $pi) {
                $stmtItem->bind_param("iidsd", $sale_id, $pi['fruit_id'], $pi['quantity'], $pi['unit'], $pi['subtotal']);
                if (!$stmtItem->execute()) { throw new Exception("Execute failed (insert Sale Item): " . $stmtItem->error); }
                $this->fruit->updateStock($pi['fruit_id'], $pi['new_stock']);
            }

            $this->conn->commit();

            if ($this->notification) {
                foreach ($preparedItems as $pi) {
                    $this->notification->checkAndNotifyLowStock($pi['fruit_id']);
                }
            }

            return $sale_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            die('Transaction failed: ' . $e->getMessage());
        }
    }

    public function getTodaySalesTotal() {
        $sql = "SELECT COALESCE(SUM(total_amount),0) AS total FROM sale WHERE sale_date = CURDATE()";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return (float)$row['total'];
    }

    public function getTodayTransactionCount() {
        $sql = "SELECT COUNT(*) AS cnt FROM sale WHERE sale_date = CURDATE()";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return (int)$row['cnt'];
    }

    public function getBestSellingItemToday() {
        $sql = "SELECT f.fruit_name, SUM(si.quantity) AS qty
                FROM sale_item si
                JOIN sale s ON si.sale_id = s.sale_id
                JOIN fruit f ON si.fruit_id = f.fruit_id
                WHERE s.sale_date = CURDATE()
                GROUP BY si.fruit_id, f.fruit_name
                ORDER BY qty DESC
                LIMIT 1";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return $row ? $row : null;
    }
}
?>
