<p align="center">
  <img src="assets/logo.png" width="120" alt="plg_system_fgofflineipwhitelist logo">
</p>

<h1 align="center">System - FG Offline IP Whitelist</h1>

<p align="center">
  <img src="https://img.shields.io/badge/Joomla-4%20%7C%205%20%7C%206-1A6877?logo=joomla&logoColor=white" alt="Joomla 4 | 5 | 6">
  <img src="https://img.shields.io/badge/license-GPL--2.0-105060" alt="License: GPL-2.0">
  <img src="https://img.shields.io/github/v/release/ferino75/plg_system_fgofflineipwhitelist?color=FF6B4A&label=release" alt="Latest release">
  <img src="https://img.shields.io/github/downloads/ferino75/plg_system_fgofflineipwhitelist/total?color=FF6B4A" alt="Downloads">
</p>

A Joomla system plugin that grants **frontend access during Site Offline mode** to a
configurable list of IP addresses — without needing a Super User login or lowering the
site's security posture for everyone else.

## Why

Joomla's built-in Offline mode only lets in users with the `core.login.offline` permission
(normally Super Users), which means logging in through the offline screen every time you
want to check the live site during maintenance. This plugin adds a simple, self-contained
exception list instead: whitelist your own IP (or your office/VPN range) and the offline
screen is skipped for you, while every other visitor still sees it.

## Features

- Bypasses the Site Offline screen for a configurable list of IP addresses
- Accepts **exact IPv4/IPv6 addresses** and **CIDR ranges** (e.g. `192.168.1.0/24`, `2001:db8::/32`)
- Optional, opt-in trust of a proxy/CDN-supplied client-IP header for sites behind a
  reverse proxy or CDN — choose between `X-Forwarded-For` (chain-parsed),
  `CF-Connecting-IP` (Cloudflare), or `True-Client-IP` (Cloudflare Enterprise / Akamai
  and some other CDNs) — gated by a **trusted proxy IP list** so the header can't
  be spoofed by a visitor to bypass the whitelist
- Optional logging of whitelist bypasses to the Joomla log
- Native Joomla 4/5/6 architecture (PSR-4, `SubscriberInterface`, DI service provider)
- English (en-GB) and Slovak (sk-SK) language files
- One-click updates via the bundled Joomla update server

## Requirements

- Joomla 4, 5, or 6
- PHP 7.4+ (matching your Joomla version's own requirement)

## Installation

1. Download the latest release ZIP from the [Releases](https://github.com/ferino75/plg_system_fgofflineipwhitelist/releases) page.
2. In the Joomla administrator, go to **System → Manage → Install** and upload the ZIP.
3. Enable the plugin under **System → Manage → Plugins → System - FG Offline IP Whitelist**.

## Configuration

Open the plugin's options and set:

| Field | Description |
|---|---|
| **Allowed IP addresses** | One entry per line (or comma-separated). Exact IPs or CIDR ranges. |
| **Trust X-Forwarded-For** | Enable only behind a reverse proxy/CDN. |
| **Trusted proxy IPs** | Required when the above is enabled — IPs allowed to set the client-IP header. |
| **Client IP header** | Which header to trust: X-Forwarded-For, CF-Connecting-IP, or True-Client-IP. |
| **Log whitelist bypasses** | Writes matches to the Joomla log for auditing. |

Turn on **Site → Global Configuration → Site Offline**, and any IP in your whitelist will
continue to see the live site as normal.

## Updating

This repository ships a Joomla-compatible `updates.xml`. After the first manual install,
Joomla's **Update** view will offer new versions automatically as they're released here.

## License

GPL-2.0-or-later — see [LICENSE.txt](LICENSE.txt).
