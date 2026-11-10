<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
admin_logout();
redirect(app_url('admin/login.php'));
