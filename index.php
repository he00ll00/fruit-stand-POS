<?php
session_start();
include 'classes/Database.php';
include 'classes/Fruit.php';
include 'classes/Customer.php';
include 'classes/Sale.php';
include 'classes/Category.php';

$db = new Database();
$conn = $db->connect();
$fruit = new Fruit($conn);
$customer = new Customer($conn);
$sale = new Sale($conn, $fruit);
$category = new Category($conn);

$page = $_GET['page'] ?? 'fruits';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD FRUIT
    if (isset($_POST['add_fruit'])) {
        $name = trim($_POST['fruit_name']);
        $price = isset($_POST['price_per_kg']) ? floatval($_POST['price_per_kg']) : floatval($_POST['price_per_qty'] ?? 0);
        $stock = intval($_POST['stock_qty']);
        $cat_name = trim($_POST['category_name'] ?? '');
        
        // ✅ Validations
        if (empty($cat_name)) {
            $message = "<div class='alert alert-danger'>Category is required!</div>";
        } elseif ($price <= 0) {
            $message = "<div class='alert alert-danger'>Price must be greater than zero.</div>";
        } else {
            $cat_id = $category->getOrCreateCategory($cat_name);

            // ✅ Handle image upload
            $image_path = "images/default.png"; // default image
            if (isset($_FILES['fruit_image']) && $_FILES['fruit_image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "images/"; // store uploade images
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

            $fileName = time() . "_" . basename($_FILES["fruit_image"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["fruit_image"]["tmp_name"], $targetFilePath)) {
            $image_path = $targetFilePath;
        } else {
            $message = "<div class='alert alert-danger'>Failed to upload image.</div>";
        }
        } else {
        $message = "<div class='alert alert-danger'>Invalid image type (JPG, PNG, GIF only).</div>";
    }
}


            // Insert fruit with image path
            $fruit->addFruit($name, $price, $stock, $cat_id, $image_path);
            $message = "<div class='alert alert-success'>Fruit added successfully!</div>";
        }

        $page = 'fruits';
    }
    // RESTOCK FRUIT (increase stock)
    if (isset($_POST['restock'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        if ($fid > 0 && $amount > 0) {
            $details = $fruit->getFruit($fid);
            if ($details) {
                $newStock = intval($details['stock_qty']) + $amount;
                $fruit->updateStock($fid, $newStock);
                $message = "<div class='alert alert-success'>Restocked " . htmlspecialchars($details['fruit_name']) . " by $amount.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Enter a valid restock amount.</div>";
        }
        $page = 'restock';
    }
    // RESTOCK - change price
    if (isset($_POST['update_price'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : 0;
        if ($fid > 0 && $new_price > 0) {
            $fruit->updatePrice($fid, $new_price);
            $details = $fruit->getFruit($fid);
            $message = "<div class='alert alert-success'>Updated price of " . htmlspecialchars($details['fruit_name'] ?? 'Fruit') . " to ₱" . number_format($new_price,2) . ".</div>";
        } else {
            $message = "<div class='alert alert-danger'>Enter a valid price greater than zero.</div>";
        }
        $page = 'restock';
    }
    // RESTOCK - change photo
    if (isset($_POST['update_image'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        if ($fid > 0 && isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "images/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $fileName = time() . "_" . basename($_FILES["new_image"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg','jpeg','png','gif'];
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $targetFilePath)) {
                    $fruit->updateImage($fid, $targetFilePath);
                    $details = $fruit->getFruit($fid);
                    $message = "<div class='alert alert-success'>Updated photo of " . htmlspecialchars($details['fruit_name'] ?? 'Fruit') . ".</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Failed to upload image.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Invalid image type (JPG, PNG, GIF only).</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Please choose an image to upload.</div>";
        }
        $page = 'restock';
    }
    if (isset($_POST['add_to_cart'])) {
        $fruit_id = intval($_POST['fruit_id']);
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $details = $fruit->getFruit($fruit_id);
        if ($details) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $in_cart = isset($_SESSION['cart'][$fruit_id]) ? intval($_SESSION['cart'][$fruit_id]['quantity']) : 0;
            $stock = intval($details['stock_qty']);
            if ($stock <= 0 || $in_cart >= $stock) {
                $message = "<div class='alert alert-danger'>Out of stock for " . htmlspecialchars($details['fruit_name']) . ".</div>";
            } else {
                $can_add = min($quantity, $stock - $in_cart);
                if (!isset($_SESSION['cart'][$fruit_id])) {
                    $_SESSION['cart'][$fruit_id] = [
                        'name' => $details['fruit_name'],
                        'price' => $details['price_per_kg'],
                        'quantity' => 0
                    ];
                }
                $_SESSION['cart'][$fruit_id]['quantity'] += $can_add;
                header('Location: ?page=fruits');
                exit;
            }
        }
    }

    if (isset($_POST['change_qty'])) {
        $fid = intval($_POST['fruit_id']);
        $delta = intval($_POST['delta']);
        if (isset($_SESSION['cart'][$fid])) {
            if ($delta > 0) {
                $details = $fruit->getFruit($fid);
                $stock = intval($details['stock_qty'] ?? 0);
                $current = intval($_SESSION['cart'][$fid]['quantity']);
                if ($current >= $stock) {
                    $message = "<div class='alert alert-danger'>Max quantity reached for this item.</div>";
                } else {
                    $_SESSION['cart'][$fid]['quantity'] = min($stock, $current + 1);
                    header('Location: ?page=fruits');
                    exit;
                }
            } else {
                $_SESSION['cart'][$fid]['quantity'] += $delta;
                if ($_SESSION['cart'][$fid]['quantity'] <= 0) {
                    unset($_SESSION['cart'][$fid]);
                }
                header('Location: ?page=fruits');
                exit;
            }
        }
    }

    

    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        header('Location: ?page=fruits');
        exit;
    }

    if (isset($_POST['process_sale'])) {
        $name_input = trim($_POST['customer_name'] ?? '');
        if ($name_input !== '') {
            $customer_id = null;
            $custom_name = $name_input;
        } else {
            $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $custom_name = $customer_id ? '' : 'Walk-in';
        }
        $payment_type = $_POST['payment_type'] ?? 'cash';
        $items = [];
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $fid => $it) {
                $items[] = ['fruit_id' => intval($fid), 'quantity' => intval($it['quantity'])];
            }
            $result = $sale->createSaleFromCart($customer_id, $custom_name, $payment_type, $items);
            if ($result === "NOT_ENOUGH_STOCK") {
                $message = "<div class='alert alert-danger'>Not enough stock for one or more items.</div>";
            } elseif (is_numeric($result)) {
                $_SESSION['cart'] = [];
                header('Location: ?page=receipt&id=' . $result);
                exit;
            } else {
                $message = "<div class='alert alert-danger'>Error processing sale.</div>";
            }
        }
    }
}
if (isset($_GET['remove'])) {
    $fid = intval($_GET['remove']);
    if (isset($_SESSION['cart'][$fid])) {
        unset($_SESSION['cart'][$fid]);
    }
    header('Location: ?page=fruits');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Fruit POS System</title>
  <link href="style.css" rel="stylesheet">
  <style>
    .fruit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .fruit-card {
      background-color: #fff8ef;
      border: 1px solid #ccc;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      padding: 10px;
      text-align: center;
      transition: transform 0.2s;
    }

    .fruit-card img {
      width: 100%;
      height: 120px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .fruit-card h4 { margin: 5px 0; }
    .fruit-card p { margin: 3px 0; font-size: 14px; }

    
  </style>
</head>
<body>
<div class="container">
  <h2> Fruit Stand POS </h2>

  <nav>
    <a href="?page=fruits" class="btn btn-secondary btn-sm">Fruits</a>

    <a href="?page=add_fruit" class="btn btn-primary btn-sm">Add Fruit</a>
    <a href="?page=restock" class="btn btn-primary btn-sm">Restock</a>
    <a href="?page=view_sales" class="btn btn-dark btn-sm">View Sales</a>
  </nav>

  <?= $message ?>

  <?php if ($page == 'fruits'): ?>
    <?php include __DIR__ . '/pages/fruits.php'; ?>

  <?php elseif ($page == 'restock'): ?>
    <?php include __DIR__ . '/pages/restock.php'; ?>

  <?php elseif ($page == 'add_fruit'): ?>
    <?php include __DIR__ . '/pages/add_fruit.php'; ?>


  <?php elseif ($page == 'view_sales'): ?>
    <?php include __DIR__ . '/pages/view_sales.php'; ?>

  <?php elseif ($page == 'receipt'): ?>
    <?php include __DIR__ . '/pages/receipt.php'; ?>
  <?php endif; ?>

</body>
</html>
