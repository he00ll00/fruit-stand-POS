<?php
$id = $_GET['id'] ?? 0;
$saleHeader = $sale->getSale($id);
$items = $sale->getSaleItems($id);
if (!$saleHeader) { echo "<p>Receipt not found.</p>"; return; }

define('RECEIPT_URL', 'receipt_print.php?id=' . $saleHeader['sale_id']);
?>

<div style="display:flex; justify-content:center;">
  <div class="card p-3 w-50">
    <h3>Receipt #<?= $saleHeader['sale_id'] ?></h3>
    <p>Customer: <?= htmlspecialchars(
        !empty($saleHeader['custom_customer_name']) ? $saleHeader['custom_customer_name'] : 
        (!empty($saleHeader['customer_name']) ? $saleHeader['customer_name'] : 'No Name Customer')
    ) ?></p>
    <hr>
    <table class="table">
      <tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>
      <?php foreach($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['fruit_name']) ?></td>
        <td><?= ($it['unit'] === 'kg') ? (number_format((float)$it['quantity'], 3) . ' kg') : (intval($it['quantity']) . ' pc') ?></td>
        <td>₱<?= number_format($it['subtotal'],2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <h4>Total: ₱<?= number_format($saleHeader['total_amount'],2) ?></h4>
    <a href="<?= RECEIPT_URL ?>" target="_blank" class="btn btn-primary mt-2">Print Receipt</a>
  </div>
</div>
