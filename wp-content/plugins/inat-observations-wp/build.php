#!/usr/bin/env php
<?php
/**
 * Build Script for iNaturalist Observations WordPress Plugin
 *
 * Creates a production-ready distribution package with:
 * - Minified PHP (comments stripped, whitespace normalized)
 * - Minified CSS (comments stripped, whitespace minimized)
 * - Minified JavaScript (comments stripped, whitespace minimized)
 * - Clean directory structure (no dev files)
 *
 * Usage:
 *   php build.php [--output=path] [--no-minify] [--version=X.Y.Z]
 *
 * Options:
 *   --output=PATH   Output directory (default: ./dist)
 *   --no-minify     Skip minification (for debugging)
 *   --version=X.Y.Z Override version number
 *   --help          Show this help message
 */

// Configuration
$pluginSlug = 'inat-observations-wp';
$pluginDir = __DIR__;
$defaultOutput = $pluginDir . '/dist';

// Parse command line arguments
$options = getopt('h', ['output:', 'no-minify', 'version:', 'help']);
$outputDir = $options['output'] ?? $defaultOutput;
$skipMinify = isset($options['no-minify']);
$versionOverride = $options['version'] ?? null;

if (isset($options['h']) || isset($options['help'])) {
    echo file_get_contents(__FILE__);
    echo "\n";
    exit(0);
}

echo "========================================\n";
echo "iNaturalist Observations Plugin Builder\n";
echo "========================================\n\n";

// Get version from main plugin file
$mainFile = file_get_contents($pluginDir . '/inat-observations-wp.php');
preg_match('/Version:\s*([0-9.]+)/', $mainFile, $matches);
$version = $versionOverride ?? ($matches[1] ?? '0.0.0');
echo "Version: $version\n";
echo "Output: $outputDir\n";
echo "Minify: " . ($skipMinify ? "NO" : "YES") . "\n\n";

// Files/directories to exclude from distribution
$excludePatterns = [
    'build.php',           // This script
    'phpunit.xml',         // Test configuration
    'composer.json',       // Dev dependencies
    'composer.lock',
    'TESTING.md',          // Dev documentation
    'TEST-ENHANCEMENTS.md',
    'TODO.md',
    '.gitignore',
    '.git',
    'tests',               // Test directory
    'debug',               // Debug directory
    'dist',                // Previous builds
    'node_modules',
    'vendor',
];

// Create clean output directory
$distDir = $outputDir . '/' . $pluginSlug;
if (is_dir($distDir)) {
    echo "Cleaning existing dist directory...\n";
    deleteDirectory($distDir);
}
mkdir($distDir, 0755, true);
mkdir($distDir . '/includes', 0755, true);
mkdir($distDir . '/assets/css', 0755, true);
mkdir($distDir . '/assets/js', 0755, true);

echo "Copying and processing files...\n";

// Process PHP files
$phpFiles = [
    'inat-observations-wp.php',
    'uninstall.php',
    'includes/init.php',
    'includes/settings.php',
    'includes/api.php',
    'includes/db-schema.php',
    'includes/shortcode.php',
    'includes/rest.php',
    'includes/admin.php',
];

foreach ($phpFiles as $file) {
    $source = $pluginDir . '/' . $file;
    $dest = $distDir . '/' . $file;

    if (!file_exists($source)) {
        echo "  WARNING: $file not found, skipping\n";
        continue;
    }

    if ($skipMinify) {
        copy($source, $dest);
        echo "  Copied: $file\n";
    } else {
        $content = minifyPHP($source);
        file_put_contents($dest, $content);
        $originalSize = filesize($source);
        $newSize = strlen($content);
        $reduction = round((1 - $newSize / $originalSize) * 100);
        echo "  Minified: $file (-{$reduction}%)\n";
    }
}

// Process CSS files
$cssFiles = glob($pluginDir . '/assets/css/*.css');
foreach ($cssFiles as $source) {
    $filename = basename($source);
    $dest = $distDir . '/assets/css/' . $filename;

    if ($skipMinify) {
        copy($source, $dest);
        echo "  Copied: assets/css/$filename\n";
    } else {
        $content = minifyCSS(file_get_contents($source));
        file_put_contents($dest, $content);
        $originalSize = filesize($source);
        $newSize = strlen($content);
        $reduction = round((1 - $newSize / $originalSize) * 100);
        echo "  Minified: assets/css/$filename (-{$reduction}%)\n";
    }
}

// Process JS files
$jsFiles = glob($pluginDir . '/assets/js/*.js');
foreach ($jsFiles as $source) {
    $filename = basename($source);
    $dest = $distDir . '/assets/js/' . $filename;

    if ($skipMinify) {
        copy($source, $dest);
        echo "  Copied: assets/js/$filename\n";
    } else {
        $content = minifyJS(file_get_contents($source));
        file_put_contents($dest, $content);
        $originalSize = filesize($source);
        $newSize = strlen($content);
        $reduction = round((1 - $newSize / $originalSize) * 100);
        echo "  Minified: assets/js/$filename (-{$reduction}%)\n";
    }
}

// Create ZIP archive
$zipFile = $outputDir . "/{$pluginSlug}-{$version}.zip";
if (file_exists($zipFile)) {
    unlink($zipFile);
}

echo "\nCreating ZIP archive...\n";
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
    addDirectoryToZip($zip, $distDir, $pluginSlug);
    $zip->close();
    $zipSize = round(filesize($zipFile) / 1024, 1);
    echo "  Created: {$pluginSlug}-{$version}.zip ({$zipSize} KB)\n";
} else {
    echo "  ERROR: Failed to create ZIP archive\n";
    exit(1);
}

echo "\n========================================\n";
echo "Build complete!\n";
echo "========================================\n";
echo "Distribution: $distDir\n";
echo "ZIP Archive: $zipFile\n";
echo "\n";

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Minify PHP by stripping comments and normalizing whitespace
 */
function minifyPHP($file) {
    // Use PHP's built-in token-based stripping
    $content = php_strip_whitespace($file);

    // Preserve the file header comment for license/attribution
    $original = file_get_contents($file);
    if (preg_match('/^<\?php\s*\/\*\*.*?Plugin Name:.*?\*\//s', $original, $headerMatch)) {
        // Keep the main plugin header for WordPress
        $content = preg_replace('/^<\?php\s*/', "<?php\n" . $headerMatch[0] . "\n", $content);
    }

    // Ensure proper PHP opening
    if (strpos($content, '<?php') !== 0) {
        $content = '<?php ' . $content;
    }

    return $content;
}

/**
 * Minify CSS by removing comments and unnecessary whitespace
 */
function minifyCSS($css) {
    // Remove comments (but preserve /*! important comments)
    $css = preg_replace('/\/\*(?!!)[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);

    // Remove whitespace around special characters
    $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);

    // Remove leading/trailing whitespace
    $css = preg_replace('/^\s+|\s+$/m', '', $css);

    // Remove newlines
    $css = preg_replace('/\n+/', '', $css);

    // Remove multiple spaces
    $css = preg_replace('/\s+/', ' ', $css);

    // Remove space after opening brace
    $css = preg_replace('/{\s+/', '{', $css);

    // Remove last semicolon before closing brace
    $css = preg_replace('/;\s*}/', '}', $css);

    return trim($css);
}

/**
 * Minify JavaScript by removing comments and normalizing whitespace
 * Note: This is a simple minifier; for production use, consider UglifyJS or Terser
 */
function minifyJS($js) {
    // Remove single-line comments (but not URLs)
    $js = preg_replace('/(?<!:)\/\/(?![\*\/]).*$/m', '', $js);

    // Remove multi-line comments
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);

    // Remove leading/trailing whitespace from lines
    $js = preg_replace('/^\s+|\s+$/m', '', $js);

    // Collapse multiple newlines
    $js = preg_replace('/\n+/', "\n", $js);

    // Remove whitespace around operators (careful with regex literals)
    $js = preg_replace('/\s*([{}();,:])\s*/', '$1', $js);

    // Remove empty lines
    $js = preg_replace('/^\s*$/m', '', $js);

    // Keep single newlines for safety (full minification requires proper parser)
    $js = preg_replace('/\n+/', "\n", $js);

    return trim($js);
}

/**
 * Recursively delete a directory
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Recursively add directory to ZIP archive
 */
function addDirectoryToZip($zip, $dir, $zipPath) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . '/' . substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}
