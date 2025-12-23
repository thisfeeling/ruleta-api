// prestart.mjs
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'fs';
import path from 'path';

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

// Replace nginx paths with a tried list and a sensible fallback
const candidates = [
    '/nix/var/nix/profiles/default/etc/nginx',
    '/nix/var/nix/profiles/default/etc',
    '/nix/var/nix/profiles/default',
    '/etc/nginx',
    '/etc'
];
let chosen = null;
for (const c of candidates) {
    if (existsSync(path.join(c, 'conf', 'mime.types')) || existsSync(path.join(c, 'mime.types'))) {
        chosen = c;
        break;
    }
}

if (!chosen) {
    // create a minimal mime.types so nginx won't fail if nothing is found
    try {
        if (!existsSync('/etc/nginx')) mkdirSync('/etc/nginx', { recursive: true });
        writeFileSync('/etc/nginx/mime.types', 'types {\n    text/html html;\n    text/css css;\n    application/javascript js;\n}');
        chosen = '/etc/nginx';
    } catch (e) {
        chosen = '/nix/var/nix/profiles/default';
    }
}

config = config.replace(/\$!\{nginx\}/g, chosen);

writeFileSync(outputPath, config);
console.log('Nginx configuration generated successfully');
