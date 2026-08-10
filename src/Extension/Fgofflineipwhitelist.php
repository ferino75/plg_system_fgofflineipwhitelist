<?php

declare(strict_types=1);

/**
 * @package     FG.Plugin.System.Fgofflineipwhitelist
 * @copyright   Copyright (C) FG. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace FG\Plugin\System\Fgofflineipwhitelist\Extension;

defined('_JEXEC') or die;

use FG\Plugin\System\Fgofflineipwhitelist\Support\IpResolver;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin that grants access to a site in "Offline" mode
 * to a configurable list of IP addresses / CIDR ranges.
 */
final class Fgofflineipwhitelist extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        // High priority so this runs before other onAfterInitialise listeners -
        // notably the System - Cache plugin, which could otherwise serve a
        // cached "Offline" page response before this plugin gets a chance to
        // clear the offline flag for a whitelisted visitor. In Joomla's event
        // system, a HIGHER priority value runs EARLIER (opposite of e.g.
        // WordPress hook priorities).
        return [
            'onAfterInitialise' => ['onAfterInitialise', Priority::HIGH],
        ];
    }

    /**
     * Checked before Joomla dispatches to the offline template, so the
     * offline flag can be temporarily cleared for whitelisted visitors.
     */
    public function onAfterInitialise(EventInterface $event): void
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

        $trustedProxies = IpResolver::parseList((string) $this->params->get('trusted_proxies', ''));

        if ($trustedProxies === []) {
            // Fail closed: with no trusted proxies configured, never trust a
            // client-suppliable header - it could be spoofed to bypass the whitelist.
            return $remoteAddr;
        }

        if (!IpResolver::ipMatchesList($remoteAddr, $trustedProxies)) {
            // Connecting peer is not a known proxy - never trust its headers.
            return $remoteAddr;
        }

        $ipHeader = (string) $this->params->get('ip_header', 'x_forwarded_for');

        return IpResolver::resolveHeader($input, $ipHeader, $trustedProxies, $remoteAddr);
    }

    /**
     * @return bool True if $ip matches any configured allowed IP/CIDR entry.
     */
    private function isAllowed(string $ip): bool
    {
        return IpResolver::ipMatchesList($ip, IpResolver::parseList((string) $this->params->get('allowed_ips', '')));
    }
}
