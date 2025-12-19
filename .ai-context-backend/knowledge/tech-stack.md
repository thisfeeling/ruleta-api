# Tech Stack - Ruleta Familiar Backend

## Core

- **Framework**: Laravel 12
- **PHP**: 8.3+
- **WebSockets**: Laravel Reverb
- **Database**: MySQL 8
- **Cache/Queue**: Redis 7

## Audio

- **TTS Primary**: TopMediai API
- **TTS Fallback**: ElevenLabs API
- **Storage**: RustFS (S3-compatible)
- **Format**: MP3 44.1kHz 128kbps

## Infrastructure

- **Containerization**: Docker
- **Orchestration**: Dokploy
- **Proxy**: Traefik (automático)
- **SSL**: Let's Encrypt
- **DNS**: Cloudflare (recomendado)

## Development

- **Dependency Manager**: Composer
- **Code Style**: Laravel Pint (PSR-12)
- **Testing**: Pest PHP
- **Git**: GitHub

## Monitoring (Futuro)

- **Logs**: Laravel Log
- **Metrics**: Custom (Redis-based)
- **Errors**: Laravel Log / Sentry (futuro)

**Ver también**: `architecture.md`, `docker-deployment.md`
