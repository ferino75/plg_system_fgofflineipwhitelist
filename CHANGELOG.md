# Changelog

All notable changes to this plugin are documented here.

## [1.0.2] - 2026-08-10
### Fixed
- Manifest was missing the `<updateservers>` block, so Joomla had no way to discover
  `updates.xml` and never checked for new versions. Added an extension update server
  pointing at the raw `updates.xml` on the `master` branch.

## [1.0.1] - 2026-08-10
### Fixed
- **Security**: `Trust X-Forwarded-For` was fail-open when `Trusted proxy IPs` was left empty -
  the header was trusted unconditionally in that case, letting a visitor spoof their IP and
  bypass the whitelist. Behaviour is now fail-closed: if no trusted proxies are configured,
  the `X-Forwarded-For` header is ignored entirely and the connecting peer's own IP
  (`REMOTE_ADDR`) is used, regardless of the `Trust X-Forwarded-For` setting.
- Updated the `Trusted proxy IPs` field description (en-GB, sk-SK) to reflect the new behaviour.

## [1.0.0] - 2026-08-10
### Added
- Initial release.
- System plugin (`plg_system_fgofflineipwhitelist`) that bypasses Joomla's Site Offline
  screen on the frontend for a configurable list of IP addresses.
- Support for exact IPv4/IPv6 addresses and CIDR ranges (e.g. `192.168.1.0/24`).
- Optional trust of `X-Forwarded-For`, gated by a configurable list of trusted proxy IPs
  to prevent header spoofing (for sites behind a reverse proxy / CDN).
- Optional logging of whitelist bypasses to the Joomla log (`fgofflineipwhitelist` category).
- Native Joomla 4/5/6 architecture: PSR-4 autoloading, `SubscriberInterface`,
  DI container registration via `services/provider.php`.
- English (en-GB) and Slovak (sk-SK) language files.
