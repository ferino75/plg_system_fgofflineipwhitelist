# Changelog

All notable changes to this plugin are documented here.

## [1.0.8] - 2026-08-10
### Added
- New **"Your detected IP"** read-only info box at the top of the plugin's settings,
  showing the IP currently detected for the admin's own request - resolved with the
  exact same shared logic (`Support\IpResolver`) the plugin uses at runtime, including
  any unsaved Trust X-Forwarded-For / Trusted proxy IPs / Client IP header values on
  the form. Makes it easy to find the right value to add to Allowed IP addresses
  without leaving the settings screen.
### Changed
- Refactored IP/CIDR matching and client-IP header resolution out of the plugin class
  into a shared `Support\IpResolver` helper, used by both the runtime plugin and the
  new preview field, so the preview can never diverge from actual enforcement.

## [1.0.7] - 2026-08-10
### Added
- New **Client IP header** option, selectable when Trust X-Forwarded-For is on:
  `X-Forwarded-For` (default, chain-parsed as before), `CF-Connecting-IP`
  (Cloudflare), or `True-Client-IP` (Cloudflare Enterprise / Akamai and some
  other CDNs). The single-value headers are read directly with no chain
  parsing needed, and - like X-Forwarded-For - are only ever honoured when the
  connecting peer matches the configured Trusted proxy IPs.

## [1.0.6] - 2026-08-10
### Changed
- Removed the `@` error-suppression operator from all `inet_pton()` calls. The
  `false` return value was already being checked explicitly right after each
  call, so the suppression added no benefit and only hid legitimate warnings
  for malformed configuration entries.

## [1.0.5] - 2026-08-10
### Changed
- When access logging is enabled and a whitelisted visitor is resolved through
  `X-Forwarded-For`, the log entry now also records the connecting proxy's own
  address (`Offline mode bypassed for whitelisted IP X via proxy Y`), instead of
  only the resolved client IP. Direct (non-proxied) matches are unchanged.

## [1.0.4] - 2026-08-10
### Fixed
- Exact-match IP comparison (non-CIDR entries) compared raw text, so two valid but
  differently-written notations of the same IPv6 address (e.g. `2001:db8::1` vs its
  fully expanded form) were treated as different addresses and would not match.
  Both sides are now normalized to binary via `inet_pton()` before comparing,
  matching the approach already used for CIDR ranges.

## [1.0.3] - 2026-08-10
### Fixed
- **Security**: `X-Forwarded-For` parsing used the left-most (client-supplied) entry
  in the chain, which a real client could freely spoof if a trusted proxy only
  appends its own hop instead of overwriting the header. Parsing now walks the
  chain right-to-left, validating each hop against the trusted proxy list, and
  returns the first entry that is not itself a trusted proxy - the same model
  used by e.g. `TRUSTED_PROXIES`-style handling in mainstream reverse-proxy setups.
- Updated the `Trusted proxy IPs` field description (en-GB, sk-SK) accordingly.

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
