<?php
/**
 * DesignLab Entry Point
 * 
 * كل Requests للمختبر تمر من هنا
 */

// إلزامي: تفعيل وضع المختبر
define('LAB_MODE', true);
define('LAB_READONLY', true);

// تحميل النواة - نفس طريقة index.php
require_once __DIR__ . '/app/Support/autoload.php';
require_once __DIR__ . '/design-lab/core/Router.php';
require_once __DIR__ . '/design-lab/core/LabMode.php';
require_once __DIR__ . '/design-lab/core/DataAccess.php';

// تفعيل حماية القراءة فقط
LabMode::enableReadOnlyMode();

// التوجيه
$router = new LabRouter();
$router->route($_SERVER['REQUEST_URI']);
