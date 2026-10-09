<?php
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect(home_path_for_role());
}

require_once 'public_home.php';
