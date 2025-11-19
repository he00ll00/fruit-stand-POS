<?php
class Category {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Add category
    public function addCategory($name) {
        $sql = "INSERT INTO category (category_name) VALUES (?)";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param("s", $name);

        if (!$stmt->execute()) {
            die("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    }

    public function getOrCreateCategory($category_name) {
    $query = "SELECT category_id FROM category WHERE category_name = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param("s", $category_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row["category_id"];
    } else {
        //new category 
        $insert = "INSERT INTO category (category_name) VALUES (?)";
        $stmt = $this->conn->prepare($insert);
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        return $stmt->insert_id;
    }
}
    // categories
    public function getAllCategories() {
        $sql = "SELECT * FROM category";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Query failed: " . $this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
}
?>
