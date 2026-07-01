# Changelog

## 0.1.0 - 2026-07-01

### Added

- Add Coremail to Nextcloud one-way contacts and calendar synchronization.
- Add administrator-managed Production/Public and Demo/VM security modes.
- Support an administrator default Coremail DAV base URL with optional per-user override.

### Security

- Keep Demo/VM mode compatible with HTTP, private-network hosts, and legacy Coremail DAV paths.
- Add guarded Production/Public mode defaults for HTTPS, TLS verification, and private-network host blocking.
