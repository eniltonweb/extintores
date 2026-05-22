<?php
$files = glob("*.php");

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Pattern to catch the if/else block for headers
    $pattern_block = "/\/\/\s*Incluir o cabe.alho.*?if\s*\(\\$user_level.*?else\s*\{.*?header\.php['\"];\s*\}/s";
    
    if (preg_match($pattern_block, $content)) {
        $content = preg_replace($pattern_block, "include 'templates/header_controller.php';", $content);
        file_put_contents($file, $content);
        echo "Replaced header block in $file\n";
    } else {
        // Also try to catch simple includes
        $pattern_simple = "/include\s+['\"].*?templates\/header[123]?\.php['\"];/";
        if (preg_match($pattern_simple, $content)) {
            $content = preg_replace($pattern_simple, "include 'templates/header_controller.php';", $content);
            file_put_contents($file, $content);
            echo "Replaced simple header include in $file\n";
        }
    }
}
echo "Finished replacing headers.\n";
?>
