<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

unset(
    $_SESSION['client_account_id'],
    $_SESSION['client_id'],
    $_SESSION['client_name'],
    $_SESSION['client_email'],
    $_SESSION['client_phone']
);

redirect('client_login.php');
