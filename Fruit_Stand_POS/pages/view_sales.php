<?php $sales = $sale->getAllSales(); ?>
<h4>All Sales</h4>
<table class="table table-bordered">
  <tr><th>ID</th><th>Customer</th><th>Total</th><th>Receipt</th></tr>
  <?php foreach($sales as $s): ?>
  <tr>
    <td><?= $s['sale_id'] ?></td>
    <td><?= htmlspecialchars(
      !empty($s['custom_customer_name']) ? $s['custom_customer_name'] :
      (!empty($s['customer_name']) ? $s['customer_name'] : 'No Name Customer')
    ) ?></td>
    <td>₱<?= number_format($s['total_amount'],2) ?></td>
    <td><a href="?page=receipt&id=<?= $s['sale_id'] ?>" class="btn btn-sm btn-primary">View</a></td>
  </tr>
  <?php endforeach; ?>
</table>
