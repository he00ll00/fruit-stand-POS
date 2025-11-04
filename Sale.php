
<?php
class Sale {
    private $conn;
    private $fruit;

    public function __construct($db, $fruitObj) {
        $this->conn = $db;
        $this->fruit = $fruitObj;
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

    public function createSaleFromCart($customer_id, $custom_customer_name, $payment_type, $items) {
        if (empty($items)) { return "ERROR"; }

        $this->conn->begin_transaction();
        try {
            $total = 0;
            $preparedItems = [];

            foreach ($items as $it) {
                $fruit_id = intval($it['fruit_id']);
                $qty = intval($it['quantity']);
                if ($qty <= 0) { continue; }

                $fruit = $this->fruit->getFruit($fruit_id);
                if (!$fruit) { throw new Exception('Fruit not found'); }
                if ($qty > $fruit['stock_qty']) { $this->conn->rollback(); return "NOT_ENOUGH_STOCK"; }

                $unit = floatval($fruit['price_per_kg']);
                $subtotal = $unit * $qty;
                $total += $subtotal;
                $preparedItems[] = [
                    'fruit_id' => $fruit_id,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                    'new_stock' => $fruit['stock_qty'] - $qty
                ];
            }

            $customer_id = !empty($customer_id) ? $customer_id : null;
            if ($customer_id === null) {
                $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name, payment_type)
                        VALUES (CURDATE(), ?, NULL, 1, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                $stmt->bind_param("dss", $total, $custom_customer_name, $payment_type);
            } else {
                $sql = "INSERT INTO sale (sale_date, total_amount, customer_id, user_id, custom_customer_name, payment_type)
                        VALUES (CURDATE(), ?, ?, 1, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) die('Prepare failed (insert Sale): ' . $this->conn->error);
                $stmt->bind_param("diss", $total, $customer_id, $custom_customer_name, $payment_type);
            }

            if (!$stmt->execute()) { throw new Exception("Execute failed (insert Sale): " . $stmt->error); }
            $sale_id = $this->conn->insert_id;

            $itemSql = "INSERT INTO sale_item (sale_id, fruit_id, quantity, subtotal) VALUES (?, ?, ?, ?)";
            $stmtItem = $this->conn->prepare($itemSql);
            if (!$stmtItem) { throw new Exception("Prepare failed (insert Sale Item): " . $this->conn->error); }

            foreach ($preparedItems as $pi) {
                $stmtItem->bind_param("iiid", $sale_id, $pi['fruit_id'], $pi['quantity'], $pi['subtotal']);
                if (!$stmtItem->execute()) { throw new Exception("Execute failed (insert Sale Item): " . $stmtItem->error); }
                $this->fruit->updateStock($pi['fruit_id'], $pi['new_stock']);
            }

            $this->conn->commit();
            return $sale_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            die('Transaction failed: ' . $e->getMessage());
        }
    }
}
?>
