<h4>Add Fruit</h4>
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="add_fruit" value="1">
  <div class="mb-3">
    <label>Name</label>
    <input type="text" name="fruit_name" required>
  </div>
  <div class="mb-3">
    <label>Price per piece</label>
    <input type="number" step="0.01" name="price_per_piece" min="0">
  </div>
  <div class="mb-3">
    <label>Price per kilogram</label>
    <input type="number" step="0.01" name="price_per_kg" min="0">
  </div>
  <div class="mb-3">
    <label>Stock Quantity</label>
    <input type="number" step="0.001" name="stock_qty" required>
  </div>
  <div class="mb-3">
    <label>Default Unit Type</label>
    <select name="unit_type">
      <option value="piece">Piece</option>
      <option value="kg">Kilogram</option>
    </select>
  </div>
  <div class="mb-3">
    <label>Category</label>
    <input type="text" name="category_name" required>
  </div>
  <div class="mb-3">
    <label>Fruit Image</label>
    <input type="file" name="fruit_image" accept="image/*">
  </div>
  <button class="btn btn-primary">Add Fruit</button>
</form>
