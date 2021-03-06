<?php

namespace ABetter\Wordpress;

use Composer\Script\Event;

class ComposerScripts {

	public static function postInstall(Event $event) {
		self::checkMiddleware($event);
		self::renameHelperFunctions($event);
	}

	public static function postUpdate(Event $event) {
		self::checkMiddleware($event);
		self::renameHelperFunctions($event);
	}

	// ---

	public static function checkMiddleware(Event $event) {
		$vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
		$kernelPath = $vendorDir . '/../app/Http/Kernel.php';
		if (!file_exists($kernelPath)) return;
		$content = file_get_contents($kernelPath);
		if (preg_match('/ABetter/',$content)) return;
		echo "Warning: ABetter Middleware not registrerd in app/Http/Kernel.php".PHP_EOL;
	}

	// ---

	public static function renameHelperFunctions(Event $event) {
		self::renameHelperFoundation($event);
		self::renameHelperVoyager($event);
		self::modifyHelperWordpress($event);
	}

	public static function modifyHelperWordpress(Event $event) {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
		$helpersPath = $vendorDir . '/abetter/wordpress/core/wp-includes/l10n.php';
        if (!file_exists($helpersPath)) return;
        $content = file_get_contents($helpersPath);
        $content = str_replace('function __( ', "if (!function_exists('__')){function __(", $content);
		$content = str_replace('return translate( $text, $domain );', 'return translate($text,$domain);}', $content);
        file_put_contents($helpersPath, $content);
    }

    public static function renameHelperFoundation(Event $event) {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $helpersPath = $vendorDir . '/laravel/framework/src/Illuminate/Foundation/helpers.php';
        if (!file_exists($helpersPath)) return;
        $content = file_get_contents($helpersPath);
        $content = str_replace("function_exists('__')", "function_exists('___')", $content);
        $content = str_replace('function __(', 'function ___(', $content);
        file_put_contents($helpersPath, $content);
    }

	public static function renameHelperVoyager(Event $event) {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $helpersPath = $vendorDir . '/tcg/voyager/src/Helpers/helpersi18n.php';
        if (!file_exists($helpersPath)) return;
        $content = file_get_contents($helpersPath);
        $content = str_replace("function_exists('__')", "function_exists('___')", $content);
        $content = str_replace('function __(', 'function ___(', $content);
        file_put_contents($helpersPath, $content);
    }

	public static function restoreHelperFoundation(Event $event) {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $helpersPath = $vendorDir . '/laravel/framework/src/Illuminate/Foundation/helpers.php';
        if (!file_exists($helpersPath)) return;
        $content = file_get_contents($helpersPath);
        $content = str_replace("function_exists('___')", "function_exists('__')", $content);
        $content = str_replace('function ___(', 'function __(', $content);
        file_put_contents($helpersPath, $content);
    }

	public static function restoreHelperVoyager(Event $event) {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
		$helpersPath = $vendorDir . '/tcg/voyager/src/Helpers/helpersi18n.php';
        if (!file_exists($helpersPath)) return;
        $content = file_get_contents($helpersPath);
        $content = str_replace("function_exists('___')", "function_exists('__')", $content);
        $content = str_replace('function ___(', 'function __(', $content);
        file_put_contents($helpersPath, $content);
    }

}
