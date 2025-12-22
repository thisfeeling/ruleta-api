import { readFileSync, writeFileSync } from 'fs';

const [,, templatePath, outputPath] = process.argv;

let config = readFileSync(templatePath, 'utf-8');

// Replace environment variables
config = config.replace(/\$\{PORT\}/g, process.env.PORT || '80');
config = config.replace(/\$\{NIXPACKS_PHP_ROOT_DIR\}/g, process.env.NIXPACKS_PHP_ROOT_DIR || '/app/public');
config = config.replace(/\$\{NIXPACKS_PHP_FALLBACK_PATH\}/g, process.env.NIXPACKS_PHP_FALLBACK_PATH || '/index.php');

// Handle conditional blocks
if (process.env.IS_LARAVEL === 'true') {
    config = config.replace(/\$if\(IS_LARAVEL\) \(([\s\S]*?)\) else \(\)/g, '$1');
} else {
    config = config.replace(/\$if\(IS_LARAVEL\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

if (process.env.NIXPACKS_PHP_ROOT_DIR) {
    config = config.replace(/\$if\(NIXPACKS_PHP_ROOT_DIR\) \(([\s\S]*?)\) else \([\s\S]*?\)/g, '$1');
} else {
    config = config.replace(/\$if\(NIXPACKS_PHP_ROOT_DIR\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

if (process.env.NIXPACKS_PHP_FALLBACK_PATH) {
    config = config.replace(/\$if\(NIXPACKS_PHP_FALLBACK_PATH\) \(([\s\S]*?)\) else \([\s\S]*?\)/g, '$1');
} else {
    config = config.replace(/\$if\(NIXPACKS_PHP_FALLBACK_PATH\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

// Replace nginx paths
config = config.replace(/\$!\{nginx\}/g, '/nix/var/nix/profiles/default');

writeFileSync(outputPath, config);
console.log('Nginx configuration generated successfully');
