<?php

/**
 * @package     FG.Plugin.System.Fgofflineipwhitelist
 * @copyright   Copyright (C) FG. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace FG\Plugin\System\Fgofflineipwhitelist\Support;

defined('_JEXEC') or die;

use Joomla\Input\Input;

/**
 * IP/CIDR matching and client-IP header resolution, shared between the
 * runtime plugin (Extension\Fgofflineipwhitelist) and the admin "detected
 * IP" preview field (Field\FgclientipField), so the preview can never
 * diverge from what the plugin actually enforces.
 */
final class IpResolver
{
    /**
     * Splits a textarea param into a clean list of entries, accepting
     * both newline- and comma-separated input.
     *
     * @return array<int, string>
     */
    public static function parseList(string $raw): array
    {
        $normalised = str_replace(["\r\n", "\r"], "\n", $raw);
        $pieces     = preg_split('/[\n,]+/', $normalised) ?: [];

        return array_values(array_filter(array_map('trim', $pieces), static fn ($v) => $v !== ''));
    }

    /**
     * @param array<int, string> $list
     */
    public static function ipMatchesList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (self::ipMatchesEntry($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a single IP against a single entry, which may be an exact
     * IPv4/IPv6 address or a CIDR range (e.g. 192.168.1.0/24, 2001:db8::/32).
     */
    public static function ipMatchesEntry(string $ip, string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            // Compare binary form, not text: IPv6 has multiple valid notations for
            // the same address (e.g. "2001:db8::1" vs the fully expanded form),
            // which a plain string comparison would treat as different addresses.
            $entryBin = inet_pton($entry);
            $ipBin    = inet_pton($ip);

            if ($entryBin === false || $ipBin === false) {
                return false;
            }

            // Normalize IPv4-mapped IPv6 ("::ffff:192.0.2.1") to plain IPv4 on
            // both sides independently, so it compares equal to the same
            // address written as plain IPv4 - common for dual-stack visitors.
            [$entryBin] = self::normalizeMappedIpv4($entryBin);
            [$ipBin]    = self::normalizeMappedIpv4($ipBin);

            return hash_equals($entryBin, $ipBin);
        }

        [$subnet, $maskBits] = explode('/', $entry, 2);
        $maskBits             = (int) $maskBits;

        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // As above: normalize either side's IPv4-mapped IPv6 form to plain
        // IPv4. If the *subnet* entry itself was written in mapped form, its
        // mask was expressed against the 128-bit address, so scale it down
        // to the 32-bit equivalent to match the now-4-byte representation.
        [$ipBin]                    = self::normalizeMappedIpv4($ipBin);
        [$subnetBin, $subnetMapped] = self::normalizeMappedIpv4($subnetBin);

        if ($subnetMapped) {
            $maskBits = max(0, $maskBits - 96);
        }

        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $totalBits = strlen($ipBin) * 8;

        if ($maskBits < 0 || $maskBits > $totalBits) {
            return false;
        }

        $fullBytes    = intdiv($maskBits, 8);
        $remainderBit = $maskBits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBit === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainderBit)) & 0xFF);

        return (substr($ipBin, $fullBytes, 1) & $mask) === (substr($subnetBin, $fullBytes, 1) & $mask);
    }

    /**
     * If $binary is a 16-byte IPv4-mapped IPv6 address (the ::ffff:0:0/96
     * range - i.e. 10 zero bytes, then 0xff 0xff, then a 4-byte IPv4
     * address), returns its plain 4-byte IPv4 form. Otherwise returns the
     * input unchanged. The second tuple element reports whether a mapped
     * address was actually found, so callers with an associated CIDR mask
     * (expressed against the original 128-bit form) can rescale it.
     *
     * @return array{0: string, 1: bool}
     */
    private static function normalizeMappedIpv4(string $binary): array
    {
        if (strlen($binary) === 16 && substr($binary, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            return [substr($binary, 12, 4), true];
        }

        return [$binary, false];
    }

    /**
     * Resolves the configured client-IP header, given that the connecting
     * peer has ALREADY been confirmed to be a trusted proxy by the caller.
     *
     * @param array<int, string> $trustedProxies
     */
    public static function resolveHeader(Input $input, string $ipHeader, array $trustedProxies, string $remoteAddr): string
    {
        return match ($ipHeader) {
            'cf_connecting_ip' => self::resolveSingleValueHeader($input, 'HTTP_CF_CONNECTING_IP', $remoteAddr),
            'true_client_ip'   => self::resolveSingleValueHeader($input, 'HTTP_TRUE_CLIENT_IP', $remoteAddr),
            default            => self::resolveForwardedFor($input, $trustedProxies, $remoteAddr),
        };
    }

    /**
     * Reads a single-value client-IP header (e.g. Cloudflare's CF-Connecting-IP,
     * or Akamai/Cloudflare Enterprise's True-Client-IP). Unlike X-Forwarded-For,
     * these are set once by the edge network itself and are not a hop chain.
     */
    private static function resolveSingleValueHeader(Input $input, string $serverKey, string $fallback): string
    {
        $value = trim((string) $input->server->getString($serverKey, ''));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<int, string> $trustedProxies
     */
    private static function resolveForwardedFor(Input $input, array $trustedProxies, string $fallback): string
    {
        $forwardedFor = (string) $input->server->getString('HTTP_X_FORWARDED_FOR', '');

        if ($forwardedFor === '') {
            return $fallback;
        }

        $chain = array_map('trim', explode(',', $forwardedFor));

        // Walk the chain right-to-left. Each entry's IP is only trustworthy if the
        // hop that relayed it (the entry to its right, or REMOTE_ADDR for the
        // rightmost entry) is itself a known trusted proxy - otherwise a spoofed
        // leading entry from the real client would be taken at face value. Return
        // the first (rightmost) entry that is not itself a trusted proxy.
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!self::ipMatchesList($chain[$i], $trustedProxies)) {
                return $chain[$i];
            }
        }

        // Every entry in the chain is itself a trusted proxy (unusual) - fall back
        // to the left-most (originating) entry.
        return $chain[0];
    }
}
