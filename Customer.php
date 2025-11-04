<?php
class Customer {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addCustomer($name, $contact, $type) {
        $sql = "INSERT INTO customer (customer_name, contact_number, customer_type)
                VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $name, $contact, $type);
        return $stmt->execute();
    }

    public function getAllCustomers() {
        $result = $this->conn->query("SELECT * FROM customer");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getCustomer($customer_id) {
        $sql = "SELECT * FROM customer WHERE customer_id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>