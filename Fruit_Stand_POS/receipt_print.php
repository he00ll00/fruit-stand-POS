<?php
session_start();
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Fruit.php';
require_once __DIR__ . '/classes/Sale.php';

$db = new Database();
$conn = $db->connect();
$fruit = new Fruit($conn);
$sale = new Sale($conn, $fruit);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$saleHeader = $sale->getSale($id);
$items = $sale->getSaleItems($id);
if (!$saleHeader) { echo '<!DOCTYPE html><html><body><p>Receipt not found.</p></body></html>'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Receipt #<?= $saleHeader['sale_id'] ?></title>
  <style>
    @page { margin: 6mm; }
    body { background:#fff; color:#000; margin:0; }
    .receipt { width: 280px; margin: 0 auto; font-family: monospace; font-size: 12px; }
    .title { text-align:center; }
    .title h3 { margin: 6px 0 2px; font-size: 16px; }
    .muted { color:#333; }
    hr { border:0; border-top:1px dashed #000; margin:8px 0; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding: 4px 0; text-align:left; }
    th.qty, td.qty { width: 30px; text-align:center; }
    th.sub, td.sub { width: 80px; text-align:right; }
    .tot { font-weight:bold; text-align:right; margin-top:8px; }
    /* Hide everything except .receipt when printing (in case browser UI or headers appear) */
    @media print {
      body { background:#fff; }
    }
    /* Optional screen centering */
    @media screen {
      .wrap { display:flex; justify-content:center; padding: 10px; }
    }
  </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
  <div class="wrap">
    <div class="receipt">
      <div class="title">
        <h3>FRUIT STAND POS</h3>
        <div class="muted">Receipt #<?= $saleHeader['sale_id'] ?></div>
      </div>

      <div>Customer: <?= htmlspecialchars(
        !empty($saleHeader['custom_customer_name']) ? $saleHeader['custom_customer_name'] :
        (!empty($saleHeader['customer_name']) ? $saleHeader['customer_name'] : 'No Name Customer')
      ) ?></div>
      <hr>

      <table>
        <tr>
          <th>Item</th>
          <th class="qty">Qty</th>
          <th class="sub">Subtotal</th>
        </tr>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['fruit_name']) ?></td>
          <td class="qty"><?= ($it['unit'] === 'kg') ? (number_format((float)$it['quantity'], 3) . ' kg') : (intval($it['quantity']) . ' pc') ?></td>
          <td class="sub">₱<?= number_format($it['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>

      <hr>
      <div class="tot">Total: ₱<?= number_format($saleHeader['total_amount'], 2) ?></div>
    </div>
  </div>
</body>
</html>
