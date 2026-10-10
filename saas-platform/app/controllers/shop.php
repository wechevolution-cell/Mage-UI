<?php
declare(strict_types=1);
final class ShopController { public static function dispatch(string $path): void { echo 'shop ' . e(App::$shop['name']); } }
