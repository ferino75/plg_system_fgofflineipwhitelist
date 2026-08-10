# Changelog

All notable changes to this plugin are documented here.

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
