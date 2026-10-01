<?php
require_once __DIR__ . '/partials/layout.php';
page_start('Dashboard', 'dashboard');
?>
    <div id="app"></div>
<?php page_end(['js/erp-core.js', 'js/dashboard.js']); ?>
