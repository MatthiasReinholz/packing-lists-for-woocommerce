<?php
/** Dependency namespace isolation; application code is never rewritten. */
return array(
	'prefix'                  => 'PackingListsForWooCommerceVendor',
	'patchers'                => array(
		static function ( string $file, string $prefix, string $contents ): string {
			if ( str_ends_with( $file, '/FontLib/Font.php' ) ) {
				$contents = str_replace( '"' . $prefix . '\\\\', '"', $contents );
				$contents = str_replace( '"FontLib\\\\{$class}"', '"' . $prefix . '\\\\FontLib\\\\{$class}"', $contents );
			}
			if ( str_ends_with( $file, '/FontLib/TrueType/File.php' ) ) {
				$contents = str_replace( '$class_parts[1]', '$class_parts[2]', $contents );
				$contents = str_replace( '"FontLib\\\\', '"' . $prefix . '\\\\FontLib\\\\', $contents );
			}
			// Dompdf builds these class names with interpolated strings.
			$contents = str_replace( "'\\Dompdf\\Positioner", "'\\" . $prefix . '\\Dompdf\\Positioner', $contents );
			return str_replace( '"Dompdf\\\\Frame', '"' . $prefix . '\\\\Dompdf\\\\Frame', $contents );
		},
	),
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
	'expose-global-constants' => false,
);
