<?php
$fid = isset($_GET['fruit_id']) ? intval($_GET['fruit_id']) : 0;
$details = $fruit->getFruit($fid);
if (!$details) { echo "<p>Item not found.</p>"; return; }
$stock = (float)$details['stock_qty'];
$max_piece = max(0, floor($stock));
$can_piece = ($details['price_per_piece'] > 0);
$can_kg = ($details['price_per_kg'] > 0);
?>
<div style="display:flex; gap:20px; align-items:flex-start;">
  <div style="flex:1;">
    <div class="fruit-card" style="padding:15px;">
      <img src="<?= htmlspecialchars(!empty($details['image_path']) ? $details['image_path'] : 'images/default.png') ?>" alt="<?= htmlspecialchars($details['fruit_name']) ?>">
      <h4><?= htmlspecialchars($details['fruit_name']) ?></h4>
      <p>
        <?php if ($can_piece): ?>
          <strong>₱<?= number_format($details['price_per_piece'],2) ?></strong> / pc
        <?php endif; ?>
        <?php if ($can_kg): ?>
          <span style="margin-left:6px;"><strong>₱<?= number_format($details['price_per_kg'],2) ?></strong> / kg</span>
        <?php endif; ?>
      </p>
      <div style="color:#6c757d;">In stock: <?= number_format($stock,3) ?></div>
    </div>
  </div>
  <div style="flex:1; background:white; border:1px solid #4c555e; border-radius:8px; padding:15px;">
    <h4 style="margin-top:0;">Sell</h4>
    <?php if ($can_piece): ?>
    <form method="post" style="margin-bottom:12px;">
      <input type="hidden" name="add_to_cart" value="1">
      <input type="hidden" name="fruit_id" value="<?= $fid ?>">
      <input type="hidden" name="unit" value="piece">
      <div class="mb-3">
        <label>Quantity (pc)</label>
        <input type="number" name="quantity" min="1" step="1" value="1" <?= $max_piece > 0 ? "max=\"$max_piece\"" : '' ?> style="width:120px;">
      </div>
      <button class="btn btn-primary" <?= $stock <= 0 ? 'disabled' : '' ?>>Add per Piece</button>
    </form>
    <?php endif; ?>

    <?php if ($can_kg): ?>
    <form method="post">
      <input type="hidden" name="add_to_cart" value="1">
      <input type="hidden" name="fruit_id" value="<?= $fid ?>">
      <input type="hidden" name="unit" value="kg">
      <div class="mb-3">
        <label>Weight (kg)</label>
        <input type="number" name="quantity" min="0.001" step="0.001" value="0.500" max="<?= $stock ?>" style="width:120px;">
      </div>
      <button class="btn btn-primary" <?= $stock <= 0 ? 'disabled' : '' ?>>Add per KG</button>
    </form>
    <?php endif; ?>

    <?php if (!$can_piece && !$can_kg): ?>
      <div class="alert alert-danger" style="margin-top:10px;">No price available.</div>
    <?php endif; ?>

    <div style="margin-top:12px;">
      <a href="?page=fruits" class="btn btn-secondary btn-sm">Back</a>
    </div>
  </div>
</div>
