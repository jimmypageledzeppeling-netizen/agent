<?php
/**
 * Plugin verification and test script
 * 
 * Run this file to verify all plugin files exist and are syntactically correct
 * Usage: php test-plugin.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

$plugin_dir = dirname( __FILE__ );
$errors     = array();
$warnings   = array();
$success    = array();

// Check if plugin directory exists
if ( ! is_dir( $plugin_dir ) ) {
	die( "Plugin directory not found: $plugin_dir\n" );
}

echo "========================================\n";
echo "Agent007 Plugin Verification\n";
echo "========================================\n\n";

// List of required files
$required_files = array(
	'agent007.php',
	'includes/class-plugin.php',
	'includes/class-database.php',
	'includes/class-rest-api.php',
	'includes/class-logger.php',
	'admin/class-admin.php',
	'admin/views/dashboard.php',
	'admin/views/logging.php',
	'assets/css/admin.css',
	'assets/js/admin.js',
	'.gitignore',
);

// Check all required files exist
echo "1. Checking required files...\n";
foreach ( $required_files as $file ) {
	$file_path = $plugin_dir . '/' . $file;
	if ( file_exists( $file_path ) ) {
		$size       = filesize( $file_path );
		$success[]  = "✓ $file ($size bytes)";
		echo "  ✓ $file\n";
	} else {
		$errors[] = "✗ Missing file: $file";
		echo "  ✗ $file\n";
	}
}
echo "\n";

// Check required directories
echo "2. Checking required directories...\n";
$required_dirs = array(
	'includes',
	'admin',
	'admin/views',
	'assets',
	'assets/css',
	'assets/js',
	'logs',
	'.git',
);

foreach ( $required_dirs as $dir ) {
	$dir_path = $plugin_dir . '/' . $dir;
	if ( is_dir( $dir_path ) ) {
		echo "  ✓ $dir\n";
		$success[] = "✓ Directory exists: $dir";
	} else {
		echo "  ✗ $dir\n";
		$warnings[] = "⚠ Missing directory: $dir (may be created later)";
	}
}
echo "\n";

// Check PHP syntax for PHP files
echo "3. Checking PHP syntax...\n";
$php_files = array(
	'agent007.php',
	'includes/class-plugin.php',
	'includes/class-database.php',
	'includes/class-rest-api.php',
	'includes/class-logger.php',
	'admin/class-admin.php',
	'admin/views/dashboard.php',
	'admin/views/logging.php',
);

foreach ( $php_files as $file ) {
	$file_path = $plugin_dir . '/' . $file;
	if ( file_exists( $file_path ) ) {
		$output = shell_exec( "php -l \"$file_path\" 2>&1" );
		if ( strpos( $output, 'No syntax errors' ) !== false || strpos( $output, 'Syntax OK' ) !== false ) {
			echo "  ✓ $file\n";
			$success[] = "✓ PHP syntax OK: $file";
		} else {
			echo "  ✗ $file\n";
			echo "    " . trim( $output ) . "\n";
			$errors[] = "✗ PHP syntax error in $file: $output";
		}
	}
}
echo "\n";

// Check WordPress plugin header
echo "4. Checking WordPress plugin header...\n";
$main_file   = $plugin_dir . '/agent007.php';
$file_content = file_get_contents( $main_file );

$header_fields = array(
	'Plugin Name:',
	'Plugin URI:',
	'Description:',
	'Version:',
	'Author:',
	'License:',
);

foreach ( $header_fields as $field ) {
	if ( strpos( $file_content, $field ) !== false ) {
		echo "  ✓ $field found\n";
		$success[] = "✓ Plugin header field: $field";
	} else {
		echo "  ✗ $field NOT found\n";
		$warnings[] = "⚠ Missing plugin header field: $field";
	}
}
echo "\n";

// Check class definitions
echo "5. Checking class definitions...\n";
$classes = array(
	'Agent007\\Plugin',
	'Agent007\\Database',
	'Agent007\\RestAPI',
	'Agent007\\Logger',
	'Agent007\\Admin',
);

foreach ( $classes as $class ) {
	$class_file = str_replace( '\\', '/', $class );
	if ( strpos( $file_content, "class $class" ) !== false || 
	     strpos( $file_content, "namespace Agent007" ) !== false ) {
		echo "  ✓ $class namespace/class found\n";
		$success[] = "✓ Class definition: $class";
	} else {
		// Try to find the class in included files
		$found = false;
		foreach ( glob( $plugin_dir . '/includes/class-*.php' ) as $inc_file ) {
			if ( strpos( file_get_contents( $inc_file ), 'class ' . explode( '\\', $class )[1] ) !== false ) {
				echo "  ✓ $class\n";
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$warnings[] = "⚠ Could not verify class: $class";
		}
	}
}
echo "\n";

// Check if .git exists
echo "6. Checking Git repository...\n";
$git_dir = $plugin_dir . '/.git';
if ( is_dir( $git_dir ) ) {
	echo "  ✓ Git repository initialized\n";
	$success[] = "✓ Git repository found";
	
	// Try to get last commit
	$last_commit = shell_exec( "cd \"$plugin_dir\" && git log -1 --oneline 2>&1" );
	if ( $last_commit && strpos( $last_commit, 'fatal' ) === false ) {
		echo "  ✓ Last commit: " . trim( $last_commit ) . "\n";
	}
} else {
	echo "  ✗ Git repository NOT found\n";
	$warnings[] = "⚠ Git repository not initialized";
}
echo "\n";

// Summary
echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";

if ( count( $success ) > 0 ) {
	echo "Successes: " . count( $success ) . "\n";
	foreach ( $success as $msg ) {
		echo "  $msg\n";
	}
	echo "\n";
}

if ( count( $warnings ) > 0 ) {
	echo "Warnings: " . count( $warnings ) . "\n";
	foreach ( $warnings as $msg ) {
		echo "  $msg\n";
	}
	echo "\n";
}

if ( count( $errors ) > 0 ) {
	echo "Errors: " . count( $errors ) . "\n";
	foreach ( $errors as $msg ) {
		echo "  $msg\n";
	}
	echo "\n";
	exit( 1 );
} else {
	echo "✓ All checks passed! Plugin is ready for activation.\n\n";
	exit( 0 );
}
