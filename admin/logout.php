
<?php
require_once '../config.php';

unset($_SESSION['admin_logged_in']);
session_regenerate_id(true);

header('Location: login.php');
exit;
?>
