<?php
  $fruits = $fruit->getAllFruits();
  $customers = $customer->getAllCustomers();
  $cart = $_SESSION['cart'] ?? [];
  $total_amount = 0; foreach ($cart as $ci) { $total_amount += $ci['unit_price'] * $ci['quantity']; }
?>
<div style="display:flex; gap:20px; align-items:flex-start;">
  <div style="flex:1;">
    <h4>Fruits</h4>
    <div class="fruit-grid">
      <?php foreach($fruits as $f): ?>
      <?php $disabled = (floatval($f['stock_qty']) <= 0); ?>
      <div class="fruit-card" style="margin:0; padding:10px;">
        <a href="?page=sell&fruit_id=<?= $f['fruit_id'] ?>" style="display:block; width:100%; height:100%; text-decoration:none; color:#212529;">
          <img src="<?= htmlspecialchars(!empty($f['image_path']) ? $f['image_path'] : 'images/default.png') ?>" alt="<?= htmlspecialchars($f['fruit_name']) ?>">
          <h4><?= htmlspecialchars($f['fruit_name']) ?></h4>
          <p>
            <?php if ($f['price_per_piece'] > 0): ?>
              <strong>₱<?= number_format($f['price_per_piece'], 2) ?></strong> / pc
            <?php endif; ?>
            <?php if ($f['price_per_kg'] > 0): ?>
              <span style="margin-left:6px;"><strong>₱<?= number_format($f['price_per_kg'], 2) ?></strong> / kg</span>
            <?php endif; ?>
          </p>
          <div style="color:#6c757d; margin-top:4px;">Stock: <?= number_format((float)$f['stock_qty'], 3) ?></div>
          <?php if ($disabled): ?>
            <div style="margin-top:8px; background:#dc3545; color:#fff; padding:4px 8px; border-radius:4px; display:inline-block;">Out of stock</div>
          <?php endif; ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="flex:1; background:white; border:1px solid #4c555e; border-radius:8px; padding:15px;">
    <div style="display:flex; justify-content:center; align-items:center;">
      <h4>Checkout</h4>
    </div>
    <form method="post" id="checkoutForm">
      <input type="text" name="customer_name" value="" placeholder="Customer name (optional)" style="width:100%; padding:8px; border:1px solid #ced4da; border-radius:4px; margin-bottom:10px;">
      <select name="customer_id" style="width:100%; padding:8px; border:1px solid #ced4da; border-radius:4px; margin-bottom:10px;">
        <option value="">Walk-in</option>
        <?php foreach($customers as $c): ?>
        <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="process_sale" value="1">
    </form>

    <div style="display:flex; justify-content:space-between; font-weight:600; margin:8px 0;">
      <span>Fruit</span><span>Price</span>
    </div>

    <?php if (empty($cart)): ?>
      <div class="text-muted">No items</div>
    <?php else: ?>
      <?php foreach($cart as $key => $it): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
          <div style="display:flex; align-items:center; gap:8px;">
            <a href="?page=fruits&remove_key=<?= urlencode($key) ?>" style="text-decoration:none; color:#dc3545; font-weight:700;">×</a>
            <div>
              <div><strong><?= htmlspecialchars($it['name']) ?></strong> <span style="color:#6c757d; font-size:12px;">(<?= $it['unit'] === 'kg' ? 'kg' : 'pc' ?>)</span></div>
              <div style="margin-top:6px; display:flex; align-items:center; gap:6px;">
                <?php if ($it['unit'] === 'piece'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="change_qty" value="1">
                    <input type="hidden" name="fruit_id" value="<?= intval($it['fruit_id']) ?>">
                    <input type="hidden" name="unit" value="piece">
                    <input type="hidden" name="delta" value="-1">
                    <button type="submit">−</button>
                  </form>
                  <span><?= intval($it['quantity']) ?></span>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="change_qty" value="1">
                    <input type="hidden" name="fruit_id" value="<?= intval($it['fruit_id']) ?>">
                    <input type="hidden" name="unit" value="piece">
                    <input type="hidden" name="delta" value="1">
                    <button type="submit">+</button>
                  </form>
                <?php else: ?>
                  <span><?= number_format((float)$it['quantity'], 3) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div><strong>₱<?= number_format($it['unit_price'] * $it['quantity'], 2) ?></strong></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:12px; font-weight:700; text-align:center;">Total: ₱<?= number_format($total_amount,2) ?></div>

    <button type="submit" form="checkoutForm" class="btn btn-primary" style="display:block; margin:10px auto 0;" <?= empty($cart) ? 'disabled' : '' ?>>Proceed</button>
    <form method="post" style="margin-top:8px;">
      <input type="hidden" name="clear_cart" value="1">
      <button type="submit" class="btn btn-danger" <?= empty($cart) ? 'disabled' : '' ?>>Clear Cart</button>
    </form>
  </div>
</div>
