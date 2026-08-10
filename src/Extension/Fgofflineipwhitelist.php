<?php

/**
 * @package     FG.Plugin.System.Fgofflineipwhitelist
 * @copyright   Copyright (C) FG. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace FG\Plugin\System\Fgofflineipwhitelist\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Input\Input;

/**
 * System plugin that grants access to a site in "Offline" mode
 * to a configurable list of IP addresses / CIDR ranges.
 */
final class Fgofflineipwhitelist extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
        ];
    }

    /**
     * Checked before Joomla dispatches to the offline template, so the
     * offline flag can be temporarily cleared for whitelisted visitors.
     */
    public function onAfterInitialise(Event $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site') || !$app->get('offline')) {
            return;
        }

        $clientIp = $this->getClientIp();

        if ($clientIp === '' || !$this->isAllowed($clientIp)) {
            return;
        }

        $app->set('offline', false);

        if ((bool) $this->params->get('log_access', 0)) {
            $remoteAddr = trim((string) $this->getApplication()->input->server->getString('REMOTE_ADDR', ''));

            $message = $clientIp === $remoteAddr
                ? sprintf('Offline mode bypassed for whitelisted IP %s', $clientIp)
                : sprintf('Offline mode bypassed for whitelisted IP %s via proxy %s', $clientIp, $remoteAddr);

            Log::add($message, Log::INFO, 'fgofflineipwhitelist');
        }
    }

    /**
     * Resolves the visitor's IP, optionally trusting a proxy/CDN-supplied
     * header when the immediate connecting peer is a configured trusted proxy.
     */
    private function getClientIp(): string
    {
        $input      = $this->getApplication()->input;
        $remoteAddr = trim((string) $input->server->getString('REMOTE_ADDR', ''));

        if (!(bool) $this->params->get('trust_xff', 0)) {
            return $remoteAddr;
        }

        $trustedProxies = $this->parseList((string) $this->params->get('trusted_proxies', ''));

        if ($trustedProxies === []) {
            // Fail closed: with no trusted proxies configured, never trust a
            // client-suppliable header - it could be spoofed to bypass the whitelist.
            return $remoteAddr;
        }

        if (!$this->ipMatchesList($remoteAddr, $trustedProxies)) {
            // Connecting peer is not a known proxy - never trust its headers.
            return $remoteAddr;
        }

        return match ((string) $this->params->get('ip_header', 'x_forwarded_for')) {
            'cf_connecting_ip' => $this->resolveSingleValueHeader($input, 'HTTP_CF_CONNECTING_IP', $remoteAddr),
            'true_client_ip'   => $this->resolveSingleValueHeader($input, 'HTTP_TRUE_CLIENT_IP', $remoteAddr),
            default            => $this->resolveForwardedFor($input, $trustedProxies, $remoteAddr),
        };
    }

    /**
     * Reads a single-value client-IP header (e.g. Cloudflare's CF-Connecting-IP,
     * or Akamai/Cloudflare Enterprise's True-Client-IP). Unlike X-Forwarded-For,
     * these are set once by the edge network itself and are not a hop chain.
     */
    private function resolveSingleValueHeader(Input $input, string $serverKey, string $fallback): string
    {
        $value = trim((string) $input->server->getString($serverKey, ''));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<int, string> $trustedProxies
     */
    private function resolveForwardedFor(Input $input, array $trustedProxies, string $fallback): string
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
            if (!$this->ipMatchesList($chain[$i], $trustedProxies)) {
                return $chain[$i];
            }
        }

        // Every entry in the chain is itself a trusted proxy (unusual) - fall back
        // to the left-most (originating) entry.
        return $chain[0];
    }

    /**
     * @return bool True if $ip matches any configured allowed IP/CIDR entry.
     */
    private function isAllowed(string $ip): bool
    {
        return $this->ipMatchesList($ip, $this->parseList((string) $this->params->get('allowed_ips', '')));
    }

    /**
     * @param array<int, string> $list
     */
    private function ipMatchesList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($this->ipMatchesEntry($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a single IP against a single entry, which may be an exact
     * IPv4/IPv6 address or a CIDR range (e.g. 192.168.1.0/24, 2001:db8::/32).
     */
    private function ipMatchesEntry(string $ip, string $entry): bool
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

            return hash_equals($entryBin, $ipBin);
        }

        [$subnet, $maskBits] = explode('/', $entry, 2);

        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maskBits  = (int) $maskBits;
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
     * Splits the textarea param into a clean list of entries, accepting
     * both newline- and comma-separated input.
     *
     * @return array<int, string>
     */
    private function parseList(string $raw): array
    {
        $normalised = str_replace(["\r\n", "\r"], "\n", $raw);
        $pieces     = preg_split('/[\n,]+/', $normalised) ?: [];

        return array_values(array_filter(array_map('trim', $pieces), static fn ($v) => $v !== ''));
    }
}
