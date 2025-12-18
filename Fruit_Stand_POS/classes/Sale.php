
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
        $hasSaleDatetime = $this->columnExists('sale', 'sale_datetime');
        $hasUnitPrice = $this->columnExists('sale_item', 'unit_price');
        $dateExpr = $hasSaleDatetime ? 'DATE(s.sale_datetime)' : 's.sale_date';
        $totalExpr = $hasUnitPrice ? 'COALESCE(SUM(si.unit_price * si.quantity), 0)'
                                   : 'COALESCE(SUM(si.subtotal), 0)';
        $groupBy = $hasSaleDatetime ? 's.sale_id, s.sale_datetime, s.custom_customer_name, c.customer_name'
                                    : 's.sale_id, s.sale_date, s.custom_customer_name, c.customer_name';
        $sql = "SELECT 
                    s.sale_id,
                    $dateExpr AS sale_date,
                    $totalExpr AS total_amount,
                    s.custom_customer_name,
                    c.customer_name
                FROM sale s
                LEFT JOIN sale_item si ON si.sale_id = s.sale_id
                LEFT JOIN customer c ON s.customer_id = c.customer_id
                GROUP BY $groupBy
                ORDER BY s.sale_id DESC";
        $result = $this->conn->query($sql);
        if (!$result) {
            die('Query failed: ' . $this->conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getSale($sale_id) {
        $hasSaleDatetime = $this->columnExists('sale', 'sale_datetime');
        $hasUnitPrice = $this->columnExists('sale_item', 'unit_price');
        $dateExpr = $hasSaleDatetime ? 'DATE(s.sale_datetime)' : 's.sale_date';
        $totalExpr = $hasUnitPrice ? 'COALESCE(SUM(si.unit_price * si.quantity), 0)'
                                   : 'COALESCE(SUM(si.subtotal), 0)';
        $groupBy = $hasSaleDatetime ? 's.sale_id, s.sale_datetime, s.custom_customer_name, c.customer_name'
                                    : 's.sale_id, s.sale_date, s.custom_customer_name, c.customer_name';
        $sql = "SELECT 
                s.sale_id, 
                $dateExpr AS sale_date, 
                $totalExpr AS total_amount, 
                s.custom_customer_name,
                c.customer_name
            FROM sale s
            LEFT JOIN sale_item si ON si.sale_id = s.sale_id
            LEFT JOIN customer c ON s.customer_id = c.customer_id
            WHERE s.sale_id = ?
            GROUP BY $groupBy";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) die('Prepare failed (getSale): ' . $this->conn->error);
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function getSaleItems($sale_id) {
        $hasUnitPrice = $this->columnExists('sale_item', 'unit_price');
        $subtotalExpr = $hasUnitPrice ? '(si.unit_price * si.quantity)' : 'si.subtotal';
        $sql = "SELECT 
                    si.sale_item_id, 
                    COALESCE(f.fruit_name, '(Deleted)') AS fruit_name, 
                    si.quantity,
                    si.unit,
                    $subtotalExpr AS subtotal
                FROM sale_item si
                LEFT JOIN fruit f ON si.fruit_id = f.fruit_id
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
                $preparedItems[] = [
                    'fruit_id' => $fruit_id,
                    'quantity' => $qty,
                    'unit' => $unitSel,
                    'unit_price' => $price,
                    'new_stock' => floatval($fruit['stock_qty']) - $qty
                ];
            }

            // compute total for legacy schema if needed
            $needsTotalAmount = $this->columnExists('sale', 'total_amount');
            $total = 0.0;
            if ($needsTotalAmount) {
                foreach ($preparedItems as $pi) { $total += ($pi['unit_price'] * $pi['quantity']); }
            }

            $customer_id = !empty($customer_id) ? $customer_id : null;
            if ($customer_id === null) {
                if ($this->columnExists('sale', 'sale_datetime')) {
                    if ($needsTotalAmount) {
                        $sql = "INSERT INTO sale (sale_datetime, total_amount, customer_id, user_id, custom_customer_name) VALUES (NOW(), ?, NULL, 1, ?)";
                        $stmt = $this->conn->prepare($sql);
                        if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                        $stmt->bind_param("ds", $total, $custom_customer_name);
                    } else {
                        $sql = "INSERT INTO sale (sale_datetime, customer_id, user_id, custom_customer_name) VALUES (NOW(), NULL, 1, ?)";
                        $stmt = $this->conn->prepare($sql);
                        if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                        $stmt->bind_param("s", $custom_customer_name);
                    }
                } else {
                    // legacy table with sale_date (DATE) and likely total_amount
                    $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name) VALUES (CURDATE(), ?, NULL, 1, ?)";
                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) die('Prepare failed (insert Sale legacy): ' . $this->conn->error);
                    $stmt->bind_param("ds", $total, $custom_customer_name);
                }
            } else {
                if ($this->columnExists('sale', 'sale_datetime')) {
                    if ($needsTotalAmount) {
                        $sql = "INSERT INTO sale (sale_datetime, total_amount, customer_id, user_id, custom_customer_name) VALUES (NOW(), ?, ?, 1, ?)";
                        $stmt = $this->conn->prepare($sql);
                        if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                        $stmt->bind_param("dis", $total, $customer_id, $custom_customer_name);
                    } else {
                        $sql = "INSERT INTO sale (sale_datetime, customer_id, user_id, custom_customer_name) VALUES (NOW(), ?, 1, ?)";
                        $stmt = $this->conn->prepare($sql);
                        if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                        $stmt->bind_param("is", $customer_id, $custom_customer_name);
                    }
                } else {
                    $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name) VALUES (CURDATE(), ?, ?, 1, ?)";
                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) die('Prepare failed (insert Sale legacy): ' . $this->conn->error);
                    $stmt->bind_param("dis", $total, $customer_id, $custom_customer_name);
                }
            }

            if (!$stmt->execute()) { throw new Exception("Execute failed (insert Sale): " . $stmt->error); }
            $sale_id = $this->conn->insert_id;

            if ($this->columnExists('sale_item', 'unit_price')) {
                $itemSql = "INSERT INTO sale_item (sale_id, fruit_id, quantity, unit, unit_price) VALUES (?, ?, ?, ?, ?)";
            } else {
                $itemSql = "INSERT INTO sale_item (sale_id, fruit_id, quantity, unit, subtotal) VALUES (?, ?, ?, ?, ?)";
            }
            $stmtItem = $this->conn->prepare($itemSql);
            if (!$stmtItem) { throw new Exception("Prepare failed (insert Sale Item): " . $this->conn->error); }

            foreach ($preparedItems as $pi) {
                $value = $pi['unit_price'];
                if (!$this->columnExists('sale_item', 'unit_price')) { $value = $pi['unit_price'] * $pi['quantity']; }
                $stmtItem->bind_param("iidsd", $sale_id, $pi['fruit_id'], $pi['quantity'], $pi['unit'], $value);
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
        $hasSaleDatetime = $this->columnExists('sale', 'sale_datetime');
        $hasUnitPrice = $this->columnExists('sale_item', 'unit_price');
        $dateCond = $hasSaleDatetime ? 'DATE(s.sale_datetime) = CURDATE()' : 's.sale_date = CURDATE()';
        $sumExpr = $hasUnitPrice ? 'si.unit_price * si.quantity' : 'si.subtotal';
        $sql = "SELECT COALESCE(SUM($sumExpr),0) AS total
                FROM sale s
                JOIN sale_item si ON si.sale_id = s.sale_id
                WHERE $dateCond";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return (float)$row['total'];
    }

    public function getTodayTransactionCount() {
        $hasSaleDatetime = $this->columnExists('sale', 'sale_datetime');
        $sql = $hasSaleDatetime
            ? "SELECT COUNT(*) AS cnt FROM sale WHERE DATE(sale_datetime) = CURDATE()"
            : "SELECT COUNT(*) AS cnt FROM sale WHERE sale_date = CURDATE()";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return (int)$row['cnt'];
    }

    public function getBestSellingItemToday() {
        $hasSaleDatetime = $this->columnExists('sale', 'sale_datetime');
        $dateCond = $hasSaleDatetime ? 'DATE(s.sale_datetime) = CURDATE()' : 's.sale_date = CURDATE()';
        $sql = "SELECT f.fruit_name, SUM(si.quantity) AS qty
                FROM sale_item si
                JOIN sale s ON si.sale_id = s.sale_id
                JOIN fruit f ON si.fruit_id = f.fruit_id
                WHERE $dateCond
                GROUP BY si.fruit_id, f.fruit_name
                ORDER BY qty DESC
                LIMIT 1";
        $res = $this->conn->query($sql);
        if (!$res) { die('Query failed: ' . $this->conn->error); }
        $row = $res->fetch_assoc();
        return $row ? $row : null;
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
