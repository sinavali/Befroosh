<?php
$files = glob('routes_*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Simple parsing line by line since regex over multi-lines in PHP can be tricky and prone to nesting issues
    $lines = explode("\n", $content);
    $currentRoute = null;
    $currentRoles = 'Public (Guest)';
    $inRoute = false;

    foreach ($lines as $line) {
        if (preg_match('/route\(\s*\'(.*?)\'\s*,\s*\'(.*?)\'/', $line, $matches)) {
            if ($currentRoute) {
                echo str_pad($currentRoute['method'], 10) . " | " . str_pad($currentRoute['path'], 50) . " | " . $currentRoles . " | " . $file . "\n";
            }
            $currentRoute = [
                'method' => $matches[1],
                'path' => $matches[2]
            ];
            $currentRoles = 'Public (Guest)';
            $inRoute = true;
        }

        if ($inRoute) {
            if (preg_match('/require_roles\(\[(.*?)\]\)/', $line, $rMatches)) {
                $currentRoles = str_replace("'", "", $rMatches[1]);
            } elseif (preg_match('/require_login\(\)/', $line)) {
                if ($currentRoles === 'Public (Guest)') {
                    $currentRoles = 'Any Authenticated User';
                }
            } elseif (preg_match('/is_shop_owner\(/', $line)) {
                $currentRoles .= ' (Checks shop owner)';
            }
        }
    }
    if ($currentRoute) {
        echo str_pad($currentRoute['method'], 10) . " | " . str_pad($currentRoute['path'], 50) . " | " . $currentRoles . " | " . $file . "\n";
    }
}
