<?php
exec("git pull origin main");
exec("systemctl restart php-fpm");
exec("systemctl reload nginx");

header("Location: /");
exit();
