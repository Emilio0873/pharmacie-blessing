<?php
require_once 'includes/functions.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'Caissier') {
        redirect('modules/caisse/index.php');
    } else {
        redirect('dashboard.php');
    }
}

require_once 'public_home.php';
