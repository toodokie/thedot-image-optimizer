#!/usr/bin/env php
<?php
/**
 * Fix ALL PHP files in the plugin that have classes
 */

$plugin_dir = __DIR__ . '/msh-image-optimizer';

// Find ALL PHP files
$php_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $php_files[] = $file->getPathname();
    }
}

echo "Checking " . count($php_files) . " PHP files\n\n";

$fixed_count = 0;
$skipped_count = 0;

foreach ($php_files as $file_path) {
    $content = file_get_contents($file_path);

    // Find class name from file
    if (!preg_match('/^class\s+([A-Za-z0-9_]+)/m', $content, $matches)) {
        $skipped_count++;
        continue; // No class in this file
    }

    $class_name = $matches[1];

    // Check if THIS class has a guard (not other classes)
    if (preg_match('/if\s*\(\s*!\s*class_exists\s*\(\s*[\'"]' . preg_quote($class_name) . '[\'"]\s*\)\s*\)/', $content)) {
        $skipped_count++;
        continue; // Already has correct guard
    }

    // Find the class declaration line
    if (!preg_match('/(.*?)(^class\s+' . preg_quote($class_name) . '\s)/ms', $content, $parts)) {
        echo "SKIP (can't match): " . basename($file_path) . "\n";
        $skipped_count++;
        continue;
    }

    $before_class = $parts[1];
    $class_line = $parts[2];
    $after_class = substr($content, strlen($before_class) + strlen($class_line));

    // Build new content with guard
    $new_content = $before_class;
    $new_content .= "if ( ! class_exists( '{$class_name}' ) ) {\n\n";
    $new_content .= $class_line;
    $new_content .= $after_class;

    // Add closing brace at end of file (before closing PHP tag if it exists)
    if (preg_match('/\n\?\>\s*$/', $new_content)) {
        $new_content = preg_replace('/(\n)(\?\>\s*)$/', "$1\n} // End if class_exists\n$2", $new_content);
    } else {
        // Trim trailing whitespace and add closing brace
        $new_content = rtrim($new_content) . "\n\n} // End if class_exists\n";
    }

    file_put_contents($file_path, $new_content);

    $rel_path = str_replace($plugin_dir . '/', '', $file_path);
    echo "FIXED: {$rel_path} (class: {$class_name})\n";
    $fixed_count++;
}

echo "\n";
echo "Fixed: {$fixed_count}\n";
echo "Skipped: {$skipped_count}\n";
echo "Total: " . count($php_files) . "\n";
