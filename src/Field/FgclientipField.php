<?php

/**
 * @package     FG.Plugin.System.Fgofflineipwhitelist
 * @copyright   Copyright (C) FG. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace FG\Plugin\System\Fgofflineipwhitelist\Field;

defined('_JEXEC') or die;

use FG\Plugin\System\Fgofflineipwhitelist\Support\IpResolver;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Read-only info box showing the IP the plugin currently detects for this
 * request, resolved with the exact same logic (and the form's in-progress
 * Trust X-Forwarded-For / Trusted proxy IPs / Client IP header values) that
 * onAfterInitialise() uses at runtime - so the preview can never mislead.
 */
class FgclientipField extends FormField
{
    protected $type = 'Fgclientip';

    protected function getInput(): string
    {
        $input      = Factory::getApplication()->input;
        $remoteAddr = trim((string) $input->server->getString('REMOTE_ADDR', ''));

        $trustXff = (bool) ((int) $this->form->getValue('trust_xff', null, 0));
        $detected = $remoteAddr;
        $viaProxy = null;

        if ($trustXff) {
            $trustedProxies = IpResolver::parseList((string) $this->form->getValue('trusted_proxies', null, ''));

            if ($trustedProxies !== [] && IpResolver::ipMatchesList($remoteAddr, $trustedProxies)) {
                $ipHeader = (string) $this->form->getValue('ip_header', null, 'x_forwarded_for');
                $resolved = IpResolver::resolveHeader($input, $ipHeader, $trustedProxies, $remoteAddr);

                if ($resolved !== $remoteAddr) {
                    $detected = $resolved;
                    $viaProxy = $remoteAddr;
                }
            }
        }

        $html = '<div class="alert alert-info fgofflineipwhitelist-detected-ip" role="status">'
            . '<strong>' . htmlspecialchars(Text::_('PLG_SYSTEM_FGOFFLINEIPWHITELIST_FIELD_DETECTED_IP_TEXT'), ENT_QUOTES, 'UTF-8') . '</strong> '
            . '<code>' . htmlspecialchars($detected, ENT_QUOTES, 'UTF-8') . '</code>';

        if ($viaProxy !== null) {
            $html .= '<br><small class="text-muted">'
                . htmlspecialchars(Text::sprintf('PLG_SYSTEM_FGOFFLINEIPWHITELIST_FIELD_DETECTED_IP_VIA_PROXY', $viaProxy), ENT_QUOTES, 'UTF-8')
                . '</small>';
        } else {
            $html .= '<br><small class="text-muted">'
                . htmlspecialchars(Text::_('PLG_SYSTEM_FGOFFLINEIPWHITELIST_FIELD_DETECTED_IP_DIRECT'), ENT_QUOTES, 'UTF-8')
                . '</small>';
        }

        $html .= '</div>';

        return $html;
    }
}
