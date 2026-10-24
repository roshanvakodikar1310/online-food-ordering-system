<?php
// Keep old bookmarks working; edit form is handled by the unified manager.
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
header('Location: products.php' . ($id > 0 ? '?edit=' . $id : '') . '#product-form');
exit;
