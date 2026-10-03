<?php
/**
 * Shared assertions for installable and production plugin payloads.
 *
 * @package Packing_Lists_For_WooCommerce
 */

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Build-only CLI exceptions contain local filenames, never browser output.

/**
 * Reject developer files and missing runtime or redistribution notices.
 *
 * @param string $directory Extracted plugin directory.
 * @param bool   $production Whether the payload is a production image.
 * @throws RuntimeException When the package violates the distribution contract.
 */
function packing_lists_for_woocommerce_check_package( string $directory, bool $production ): void {
	$required = array(
		'packing-lists-for-woocommerce.php',
		'uninstall.php',
		'includes/class-packing-lists-for-woocommerce.php',
		'includes/class-packing-lists-for-woocommerce-orders.php',
		'includes/class-packing-lists-for-woocommerce-document.php',
		'includes/class-packing-lists-for-woocommerce-mailer.php',
		'lib/vendor/autoload.php',
		'lib/vendor/composer/installed.php',
		'LICENSE',
		'THIRD-PARTY-NOTICES.md',
		'lib/licenses/DejaVu-LICENSE.txt',
		'lib/licenses/GPL-3.0.txt',
		'lib/vendor/composer/LICENSE',
		'lib/vendor/dompdf/dompdf/LICENSE.LGPL',
		'lib/vendor/dompdf/dompdf/AUTHORS.md',
		'lib/vendor/dompdf/dompdf/lib/fonts/mustRead.html',
		'lib/vendor/dompdf/dompdf/lib/res/sRGB2014.icc.LICENSE',
		'lib/vendor/dompdf/php-font-lib/LICENSE',
		'lib/vendor/dompdf/php-svg-lib/LICENSE',
		'lib/vendor/dompdf/php-svg-lib/AUTHORS.md',
		'lib/vendor/masterminds/html5/LICENSE.txt',
		'lib/vendor/sabberworm/php-css-parser/LICENSE',
	);
	if ( ! $production ) {
		$required[] = 'readme.txt';
	}
	foreach ( $required as $file ) {
		if ( ! is_file( $directory . '/' . $file ) || 0 === filesize( $directory . '/' . $file ) ) {
			throw new RuntimeException( 'Packing list package missing required file: ' . $file );
		}
	}
	$metadata = require $directory . '/lib/vendor/composer/installed.php';
	if ( 'matthiasreinholz/packing-lists-for-woocommerce' !== ( $metadata['root']['name'] ?? '' )
		|| ! array_key_exists( 'reference', $metadata['root'] ?? array() )
		|| null !== $metadata['root']['reference']
	) {
		throw new RuntimeException( 'PDF runtime metadata must use the public package identity without checkout references.' );
	}
	$allowed_root = array( 'packing-lists-for-woocommerce.php', 'uninstall.php', 'includes', 'lib', 'LICENSE', 'THIRD-PARTY-NOTICES.md' );
	if ( ! $production ) {
		$allowed_root = array_merge( $allowed_root, array( 'readme.txt', 'composer.json', 'composer.lock' ) );
	}
	foreach ( new DirectoryIterator( $directory ) as $file ) {
		if ( ! $file->isDot() && ! in_array( $file->getFilename(), $allowed_root, true ) ) {
			throw new RuntimeException( 'Unexpected packing list package entry: ' . $file->getFilename() );
		}
	}
	$required_paths = array_map( static fn( $path ) => $directory . '/' . $path, $required );
	$files          = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $files as $file ) {
		$name = $file->getFilename();
		if ( $file->isDir() && in_array( strtolower( $name ), array( 'docs', 'doc', 'tests', 'test', 'tools', 'node_modules', '.git', '.github', '.wp-plugin-base' ), true ) ) {
			throw new RuntimeException( 'Developer directory leaked into packing list package: ' . $file->getPathname() );
		}
		if ( in_array( strtolower( $file->getExtension() ), array( 'md', 'rst' ), true ) && ! in_array( $file->getPathname(), $required_paths, true ) ) {
			throw new RuntimeException( 'Unexpected developer document in packing list package: ' . $file->getPathname() );
		}
		if ( ! $production && 'readme.txt' === $name && $directory . '/readme.txt' === $file->getPathname() ) {
			continue;
		}
		// Preserve legal Markdown (AUTHORS and THIRD-PARTY-NOTICES); exclude guides.
		if ( preg_match( '/^(?:readme|changelog|upgrading|release|contributing|security|agents).*$/i', $name ) ) {
			throw new RuntimeException( 'Developer documentation leaked into packing list package: ' . $file->getPathname() );
		}
	}
}
