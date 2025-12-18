<?php
session_start();
include 'classes/Database.php';
include 'classes/Fruit.php';
include 'classes/Customer.php';
include 'classes/Sale.php';
include 'classes/Category.php';
include 'classes/Notification.php';
include 'classes/Settings.php';

$db = new Database();
$conn = $db->connect();
$fruit = new Fruit($conn);
$customer = new Customer($conn);
$settings = new Settings($conn);
$notification = new Notification($conn);
$sale = new Sale($conn, $fruit, $notification);
$category = new Category($conn);

$kpi_sales_total = $sale->getTodaySalesTotal();
$kpi_txn_count = $sale->getTodayTransactionCount();
$kpi_best_row = $sale->getBestSellingItemToday();
$kpi_best_display = $kpi_best_row ? ($kpi_best_row['fruit_name'] . ' (' . (int)$kpi_best_row['qty'] . ')') : '—';

$page = $_GET['page'] ?? 'fruits';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD FRUIT
    if (isset($_POST['add_fruit'])) {
        $name = trim($_POST['fruit_name']);
        $price_piece = isset($_POST['price_per_piece']) && $_POST['price_per_piece'] !== '' ? floatval($_POST['price_per_piece']) : 0;
        $price_kg = isset($_POST['price_per_kg']) && $_POST['price_per_kg'] !== '' ? floatval($_POST['price_per_kg']) : 0;
        $stock = isset($_POST['stock_qty']) ? floatval($_POST['stock_qty']) : 0;
        $unit_type = in_array($_POST['unit_type'] ?? 'piece', ['piece','kg']) ? $_POST['unit_type'] : 'piece';
        $cat_name = trim($_POST['category_name'] ?? '');
        
        if (empty($cat_name)) {
            $message = "<div class='alert alert-danger'>Category is required!</div>";
        } elseif ($price_piece <= 0 && $price_kg <= 0) {
            $message = "<div class='alert alert-danger'>Enter at least one price.</div>";
        } else {
            $cat_id = $category->getOrCreateCategory($cat_name);

            $image_path = "images/default.png";
            if (isset($_FILES['fruit_image']) && $_FILES['fruit_image']['error'] === UPLOAD_ERR_OK) {
                $targetDir = "images/";
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

            $fruit->addFruit($name, $price_piece, $price_kg, $stock, $unit_type, $cat_id, $image_path);
            $message = "<div class='alert alert-success'>Fruit added successfully!</div>";
            $stmtF = $conn->prepare("SELECT fruit_id FROM fruit WHERE LOWER(fruit_name)=LOWER(?) LIMIT 1");
            if ($stmtF) {
                $stmtF->bind_param('s', $name);
                $stmtF->execute();
                $resF = $stmtF->get_result();
                if ($rowF = $resF->fetch_assoc()) {
                    $notification->checkAndNotifyLowStock((int)$rowF['fruit_id']);
                }
                $stmtF->close();
            }
        }

        $page = 'fruits';
    }
    // RESTOCK FRUIT (increase stock)
    if (isset($_POST['restock'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($fid>0 && $amount>0) {
            $details = $fruit->getFruit($fid);
            if ($details) {
                $newStock = floatval($details['stock_qty']) + $amount;
                $fruit->updateStock($fid, $newStock);
                $message = "<div class='alert alert-success'>Restocked " . htmlspecialchars($details['fruit_name']) . " by $amount.</div>";
                $notification->checkAndNotifyLowStock($fid);
            }
        } else {
            $message = "<div class='alert alert-danger'>Enter a valid restock amount.</div>";
        }
        $page = 'restock';
    }
    // RESTOCK - delete fruit
    if (isset($_POST['delete_fruit'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        if ($fid > 0) {
            $details = $fruit->getFruit($fid);
            if ($details) {
                $fname = $details['fruit_name'];
                $fruit->deleteFruit($fid);
                $message = "<div class='alert alert-success'>Deleted " . htmlspecialchars($fname) . ".</div>";
            }
        }
        $page = 'restock';
    }
    // RESTOCK - change price
    if (isset($_POST['update_price_piece'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        $new_price = isset($_POST['new_price_piece']) ? floatval($_POST['new_price_piece']) : 0;
        if ($fid > 0 && $new_price >= 0) {
            $fruit->updatePrice($fid, $new_price);
            $details = $fruit->getFruit($fid);
            $message = "<div class='alert alert-success'>Updated price per piece of " . htmlspecialchars($details['fruit_name'] ?? 'Fruit') . " to ₱" . number_format($new_price,2) . ".</div>";
        } else {
            $message = "<div class='alert alert-danger'>Enter a valid price.</div>";
        }
        $page = 'restock';
    }
    if (isset($_POST['update_price_kg'])) {
        $fid = intval($_POST['fruit_id'] ?? 0);
        $new_price = isset($_POST['new_price_kg']) ? floatval($_POST['new_price_kg']) : 0;
        if ($fid > 0 && $new_price >= 0) {
            $fruit->updatePriceKg($fid, $new_price);
            $details = $fruit->getFruit($fid);
            $message = "<div class='alert alert-success'>Updated price per kg of " . htmlspecialchars($details['fruit_name'] ?? 'Fruit') . " to ₱" . number_format($new_price,2) . ".</div>";
        } else {
            $message = "<div class='alert alert-danger'>Enter a valid price.</div>";
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
        $fruit_id = intval($_POST['fruit_id'] ?? 0);
        $unit = ($_POST['unit'] ?? 'piece') === 'kg' ? 'kg' : 'piece';
        $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
        if ($unit === 'piece') { $quantity = max(1, intval($quantity)); }
        if ($fruit_id > 0 && $quantity > 0) {
            $details = $fruit->getFruit($fruit_id);
            if ($details) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $key = $fruit_id . '|' . $unit;
                $stock = floatval($details['stock_qty']);
                $in_cart_total = isset($_SESSION['cart'][$key]) ? floatval($_SESSION['cart'][$key]['quantity']) : 0.0;
                if ($stock <= 0 || $in_cart_total >= $stock) {
                    $message = "<div class='alert alert-danger'>Out of stock for " . htmlspecialchars($details['fruit_name']) . ".</div>";
                } else {
                    $can_add = min($quantity, $stock - $in_cart_total);
                    $unit_price = ($unit === 'kg') ? floatval($details['price_per_kg']) : floatval($details['price_per_piece']);
                    if (!isset($_SESSION['cart'][$key])) {
                        $_SESSION['cart'][$key] = [
                            'fruit_id' => $fruit_id,
                            'name' => $details['fruit_name'],
                            'unit' => $unit,
                            'unit_price' => $unit_price,
                            'quantity' => 0
                        ];
                    }
                    $_SESSION['cart'][$key]['quantity'] += $can_add;
                    header('Location: ?page=fruits');
                    exit;
                }
            }
        }
    }

    if (isset($_POST['change_qty'])) {
        $fid = intval($_POST['fruit_id']);
        $unit = ($_POST['unit'] ?? 'piece') === 'kg' ? 'kg' : 'piece';
        $delta = intval($_POST['delta']);
        $key = $fid . '|' . $unit;
        if ($unit === 'piece' && isset($_SESSION['cart'][$key])) {
            if ($delta > 0) {
                $details = $fruit->getFruit($fid);
                $stock = floatval($details['stock_qty'] ?? 0);
                $current = intval($_SESSION['cart'][$key]['quantity']);
                if ($current >= $stock) {
                    $message = "<div class='alert alert-danger'>Max quantity reached for this item.</div>";
                } else {
                    $_SESSION['cart'][$key]['quantity'] = min($stock, $current + 1);
                    header('Location: ?page=fruits');
                    exit;
                }
            } else {
                $_SESSION['cart'][$key]['quantity'] += $delta;
                if ($_SESSION['cart'][$key]['quantity'] <= 0) {
                    unset($_SESSION['cart'][$key]);
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
            $custom_name = '';
        }
        
        $items = [];
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $key => $it) {
                $items[] = [
                    'fruit_id' => intval($it['fruit_id']),
                    'quantity' => floatval($it['quantity']),
                    'unit' => $it['unit']
                ];
            }
            $result = $sale->createSaleFromCart($customer_id, $custom_name, $items);
            if ($result === "NOT_ENOUGH_STOCK") {
                $message = "<div class='alert alert-danger'>Not enough stock for one or more items.</div>";
            } elseif (is_numeric($result)) {
                $sale_total_tmp = 0;
                foreach ($_SESSION['cart'] as $ci) { $sale_total_tmp += ($ci['unit_price'] * $ci['quantity']); }
                $notification->log('sale_complete', 'Sale #' . $result . ' completed. Total: ₱' . number_format($sale_total_tmp, 2));
                $_SESSION['cart'] = [];
                header('Location: ?page=receipt&id=' . $result);
                exit;
            } else {
                $message = "<div class='alert alert-danger'>Error processing sale.</div>";
            }
        }
    }

    if (isset($_POST['save_settings'])) {
        $email = trim($_POST['notify_email'] ?? '');
        $enabled = isset($_POST['notify_enabled']) ? 1 : 0;
        $settings->update($email, $enabled);
        $message = "<div class='alert alert-success'>Settings saved.</div>";
        $page = 'settings';
    }
}
if (isset($_GET['remove_key'])) {
    $k = $_GET['remove_key'];
    if (isset($_SESSION['cart'][$k])) {
        unset($_SESSION['cart'][$k]);
    }
    header('Location: ?page=fruits');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Fruits</title>
  <link href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>" rel="stylesheet">
  <style>
    /* POS Layout Overrides */
    body { padding: 0; align-items: stretch; }
    .app { display: flex; min-height: 100vh; }
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0; width: 240px;
      background: #1f2937; color: #e5e7eb; border-right: 1px solid #111827; padding: 16px 12px;
    }
    .sidebar-header { font-weight: 700; font-size: 18px; margin: 6px 8px 16px; letter-spacing: .3px; }
    .menu { display: flex; flex-direction: column; gap: 6px; }
    .menu a { display: block; padding: 10px 12px; color: #e5e7eb; text-decoration: none; border-radius: 6px; font-size: 14px; }
    .menu a:hover { background: #374151; }
    .menu a.active { background: #183779ff; border: 1px solid #374151; }
    .main { margin-left: 240px; width: calc(100% - 240px); padding: 24px 0; }
    .main .container { width: 100%; max-width: 1400px; margin: 0 auto; background: transparent; padding: 0 24px 24px; border-radius: 0; box-shadow: none; text-align: left; }
    /* Fruits grid/cards */
    .fruit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-top: 20px; }
    .fruit-card { background-color: #dde6e9ff; border: 1px solid #ccc; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 10px; text-align: center; transition: transform 0.2s; }
    .fruit-card img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
    .fruit-card h4 { margin: 5px 0; }
    .fruit-card p { margin: 3px 0; font-size: 14px; }
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-header"></div>
    <nav class="menu">
      <a href="?page=fruits" class="<?= ($page==='fruits') ? 'active' : '' ?>">Fruits</a>
      <a href="?page=add_fruit" class="<?= ($page==='add_fruit') ? 'active' : '' ?>">Add Fruit</a>
      <a href="?page=restock" class="<?= ($page==='restock') ? 'active' : '' ?>">Restock</a>
      <a href="?page=view_sales" class="<?= ($page==='view_sales') ? 'active' : '' ?>">View Sales</a>
      <a href="?page=notifications" class="<?= ($page==='notifications') ? 'active' : '' ?>">Notifications</a>
      <a href="?page=settings" class="<?= ($page==='settings') ? 'active' : '' ?>">Settings</a>
    </nav>
  </aside>
  <main class="main">
    <div class="container">
      <h2>Fruit Stand POS</h2>

  <?php if (!empty($_SESSION['toasts'])): ?>
    <div class="toast-container">
      <?php foreach($_SESSION['toasts'] as $t): ?>
        <?php $tt = $t['type']; $cls = 'toast-info'; if ($tt==='warning' || $tt==='low_stock') $cls='toast-warning'; elseif($tt==='success') $cls='toast-success'; elseif($tt==='danger') $cls='toast-danger'; ?>
        <div class="toast <?= $cls ?>">
          <?= htmlspecialchars($t['text']) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php $_SESSION['toasts'] = []; ?>
  <?php endif; ?>

      <?= $message ?>

  <?php if ($page == 'view_sales'): ?>
  <div style="display:flex; gap:10px; justify-content:space-between; margin:10px 0;">
    <div style="flex:1; background:#fff8ef; border:1px solid #4c555e; border-radius:8px; padding:10px; text-align:left;">
      <div style="font-size:12px; color:#6c757d;">Total Sales Today</div>
      <div style="font-weight:700;">₱<?= number_format($kpi_sales_total, 2) ?></div>
    </div>
    <div style="flex:1; background:#fff8ef; border:1px solid #4c555e; border-radius:8px; padding:10px; text-align:left;">
      <div style="font-size:12px; color:#6c757d;">Total Transactions</div>
      <div style="font-weight:700;"><?= (int)$kpi_txn_count ?></div>
    </div>
    <div style="flex:1; background:#fff8ef; border:1px solid #4c555e; border-radius:8px; padding:10px; text-align:left;">
      <div style="font-size:12px; color:#6c757d;">Best-Selling Item</div>
      <div style="font-weight:700;">
        <?= $kpi_best_row ? htmlspecialchars($kpi_best_row['fruit_name']) . ' (' . (int)$kpi_best_row['qty'] . ')' : '—' ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
  <?php elseif ($page == 'sell'): ?>
    <?php include __DIR__ . '/pages/sell.php'; ?>
  <?php elseif ($page == 'notifications'): ?>
    <?php include __DIR__ . '/pages/notifications.php'; ?>
  <?php elseif ($page == 'settings'): ?>
    <?php include __DIR__ . '/pages/settings.php'; ?>
  <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>

