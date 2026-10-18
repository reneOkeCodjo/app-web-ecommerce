<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

if ($_SESSION['compte_id'] !== 1) {
    header('Location: index.php?page=login');
    exit;
}

echo 'you are logged in as admin';

?>