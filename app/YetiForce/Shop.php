<?php

/**
 * YetiForce shop file.
 *
 * @package   App
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace App\YetiForce;

/**
 * YetiForce shop class.
 */
class Shop
{
	/**
	 * Premium icons.
	 */
	const PREMIUM_ICONS = [
		1 => 'yfi-premium color-red-600',
		2 => 'yfi-enterprise color-yellow-600',
		3 => 'yfi-partners color-grey-600'
	];

	/**
	 * Product instance cache.
	 *
	 * @var \App\YetiForce\Shop\AbstractBaseProduct[]
	 */
	public static $productCache = [];
	/**
	 * Invalid Product Name.
	 *
	 * @var string
	 */
	public static $verifyProduct = '';

	/**
	 * Get products.
	 *
	 * @param string $state
	 * @param string $department
	 *
	 * @return \App\YetiForce\Shop\AbstractBaseProduct[]
	 */
	public static function getProducts(string $state = '', string $department = ''): array
	{
		$products = [];
		$path = \ROOT_DIRECTORY . '/app/YetiForce/Shop/Product/' . $department;
		foreach ((new \DirectoryIterator($path)) as $item) {
			if (!$item->isDir()) {
				$fileName = $item->getBasename('.php');
				$instance = static::getProduct($fileName, $department);
				if (!$instance->active || ('featured' === $state && !$instance->featured)) {
					continue;
				}
				$products[$fileName] = $instance;
			}
		}
		return $products;
	}

	/**
	 * Get products.
	 *
	 * @param string $state
	 * @param string $department
	 * @param string $name
	 *
	 * @return \App\YetiForce\Shop\AbstractBaseProduct
	 */
	public static function getProduct(string $name, string $department = ''): Shop\AbstractBaseProduct
	{
		if ($department) {
			$className = "\\App\\YetiForce\\Shop\\Product\\$department\\$name";
		} else {
			$className = "\\App\\YetiForce\\Shop\\Product\\$name";
		}
		if (isset(self::$productCache[$className])) {
			return self::$productCache[$className];
		}
		$instance = new $className($name);
		if ($config = self::getConfig($name)) {
			$instance->loadConfig($config);
		}
		return self::$productCache[$className] = $instance;
	}

	/**
	 * Get variable payments.
	 *
	 * @param bool $isCustom
	 *
	 * @return array
	 */
	public static function getVariablePayments($isCustom = false): array
	{
		$crmData = [];
		if (!$isCustom) {
			$crmData = [
				'return' => \Config\Main::$site_URL . 'index.php?module=YetiForce&parent=Settings&view=Shop&status=success',
				'cancel_return' => \Config\Main::$site_URL . 'index.php?module=YetiForce&parent=Settings&view=Shop&status=fail',
				'custom' => \App\YetiForce\Register::getInstanceKey() . '|' . \App\YetiForce\Register::getCrmKey()
			];
		}
		return array_merge([
			'business' => 'paypal@yetiforce.com',
			'rm' => 2,
			'notify_url' => 'https://api.yetiforce.com/shop',
			'image_url' => 'https://public.yetiforce.com/shop/logo.png',
		], $crmData);
	}

	/**
	 * Get additional configuration.
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public static function getConfig(string $name): array
	{
		$config = [];
		if (\is_dir(ROOT_DIRECTORY . '/app_data/shop/') && \file_exists(ROOT_DIRECTORY . "/app_data/shop/{$name}.php")) {
			$config = require ROOT_DIRECTORY . "/app_data/shop/{$name}.php";
		}
		return \App\YetiForce\Register::getProducts($name) ?? $config;
	}

	/**
	 * Verification of product activity.
	 *
	 * @param string $productName
	 *
	 * @return bool
	 */
	public static function check(string $productName): bool
	{
		$productDetails = false;
		if (($products = \App\YetiForce\Register::getProducts()) && isset($products[$productName])) {
			$productDetails = $products[$productName];
		} elseif (file_exists(ROOT_DIRECTORY . "/app_data/shop/$productName.php")) {
			$productDetails = require ROOT_DIRECTORY . "/app_data/shop/$productName.php";
		}
		$status = false;
		if ($productDetails) {
			$status = self::verifyProductKey($productDetails['key']);
			if ($status) {
				$status = strtotime(date('Y-m-d')) <= strtotime($productDetails['date']);
			}
			if ($status) {
				$status = \App\Company::getSize() === $productDetails['package'];
			}
		}
		return $status;
	}

	/**
	 * Get paypal URL.
	 * https://www.paypal.com/cgi-bin/webscr
	 * https://www.sandbox.paypal.com/cgi-bin/webscr.
	 *
	 * @return string
	 */
	public static function getPaypalUrl(): string
	{
		return 'https://www.paypal.com/cgi-bin/webscr';
	}

	/**
	 * Verification of the product key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function verifyProductKey(string $key): bool
	{
		$key = base64_decode($key);
		$m = substr(substr($key, 5), 0, -2);
		$p = substr($m, -5);
		$m = substr($m, 0, -5);
		$d = substr($m, -10);
		$m = substr($m, 0, -10);
		$s = substr($m, -5);
		$m = substr($m, 0, -5);
		return substr(crc32($m), 2, 5) === substr($key, 0, 5)
			&& substr(sha1($d . $p), 5, 5) === $s
			&& substr($key, -2) === substr(sha1(substr(crc32($m), 2, 5) . $m . substr(sha1($d . $p), 5, 5) . $d . $p), 1, 2);
	}

	/**
	 * Verify or show a message about invalid products.
	 *
	 * @return bool
	 */
	public static function verify(): bool
	{
		foreach (self::getProducts() as $product) {
			if (!$product->verify()) {
				self::$verifyProduct = $product->getLabel();
				return false;
			}
		}
		return true;
	}

	/**
	 * Generate cache.
	 */
	public static function generateCache()
	{
		$content = [];
		foreach (self::getProducts() as $key => $product) {
			$content[$key] = $product->verify(false);
		}
		\App\Utils::saveToFile(ROOT_DIRECTORY . '/app_data/shop.php', $content, 'Modifying this file will breach the licence terms', 0, true);
	}

	/**
	 * Get from cache.
	 *
	 * @return bool
	 */
	public static function getFromCache()
	{
		$content = [];
		if (\file_exists(ROOT_DIRECTORY . '/app_data/shop.php')) {
			$content = include ROOT_DIRECTORY . '/app_data/shop.php';
		}
		return $content;
	}
}
