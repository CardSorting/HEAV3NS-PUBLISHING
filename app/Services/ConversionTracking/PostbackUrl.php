<?php

namespace App\Services\ConversionTracking;

use InvalidArgumentException;
use RuntimeException;

/**
 * Validates templates without making network requests and pins delivery to a checked address.
 */
class PostbackUrl
{
    public const TOKENS = ['click_id', 'event_id', 'transaction_id', 'value', 'currency', 'purchase_type', 'is_recurring'];

    public static function validateTemplate(string $template): ?string
    {
        if ($template === '' || strlen($template) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $template)) {
            return 'Enter an HTTPS postback URL without spaces or control characters (maximum 4096 characters).';
        }

        $parts = parse_url($template);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return 'The postback URL must use HTTPS and include a hostname.';
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443) {
            return 'Use HTTPS port 443 without URL credentials or a fragment.';
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if (!self::isValidHost($host)) {
            return 'The postback URL must have a valid public hostname or IP address.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && !self::isPublicAddress($host)) {
            return 'Private, reserved, and local network destinations are not allowed.';
        }

        foreach (['scheme', 'host', 'path'] as $component) {
            if (strpbrk($parts[$component] ?? '', '{}') !== false) {
                return 'Postback placeholders are only allowed in query parameter values.';
            }
        }

        $hasClickId = false;
        foreach (explode('&', $parts['query'] ?? '') as $parameter) {
            [$key, $value] = array_pad(explode('=', $parameter, 2), 2, '');

            if (strpbrk($key, '{}') !== false) {
                return 'Postback placeholders are only allowed in query parameter values.';
            }

            preg_match_all('/\{([^{}]+)\}/', $value, $matches);
            foreach ($matches[1] as $token) {
                if (!in_array($token, self::TOKENS, true)) {
                    return 'Supported placeholders: {click_id}, {event_id}, {transaction_id}, {value}, {currency}, {purchase_type}, and {is_recurring}.';
                }
                $hasClickId = $hasClickId || $token === 'click_id';
            }

            if (strpbrk(preg_replace('/\{[^{}]+\}/', '', $value), '{}') !== false) {
                return 'The postback URL contains an invalid placeholder.';
            }
        }

        if (!$hasClickId) {
            return 'Include {click_id} in a query parameter value to attribute the conversion.';
        }

        return null;
    }

    public function render(string $template, array $values): string
    {
        if (self::validateTemplate($template) !== null) {
            throw new InvalidArgumentException('Invalid postback URL template.');
        }

        $replacements = [];
        foreach (self::TOKENS as $token) {
            if (str_contains($template, '{'.$token.'}') && (!array_key_exists($token, $values) || !is_scalar($values[$token]))) {
                throw new InvalidArgumentException('A required postback value is missing.');
            }
            $value = $values[$token] ?? '';
            if ($token === 'is_recurring' && array_key_exists($token, $values)) {
                $value = $value ? '1' : '0';
            }
            $replacements['{'.$token.'}'] = rawurlencode((string) $value);
        }

        $url = strtr($template, $replacements);
        if (strlen($url) > 16384) {
            throw new InvalidArgumentException('The rendered postback URL is too long.');
        }

        return $url;
    }

    /** @return array{url: string, host: string, address: string, resolve: array} */
    public function resolveTarget(string $template, array $values): array
    {
        $url = $this->render($template, $values);
        $host = strtolower(trim(parse_url($url, PHP_URL_HOST), '[]'));
        $literal = (bool) filter_var($host, FILTER_VALIDATE_IP);
        $addresses = $literal ? [$host] : $this->resolveHostAddresses($host);

        if ($addresses === []) {
            throw new RuntimeException('The postback hostname could not be resolved.');
        }

        // Reject mixed public/private answers instead of choosing a convenient public answer.
        foreach ($addresses as $address) {
            if (!self::isPublicAddress($address)) {
                throw new InvalidArgumentException('The postback hostname resolved to a prohibited network.');
            }
        }

        $address = $addresses[0];
        $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        return [
            'url' => $url,
            'host' => $host,
            'address' => $address,
            // IP literals cannot rebind and do not need a DNS override.
            'resolve' => $literal ? [] : [$host.':443:'.$pinnedAddress],
        ];
    }

    protected function resolveHostAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    public static function isPublicAddress(string $address): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (str_contains($address, ':')) {
            // Only global unicast; also exclude transition, protocol-assignment, and documentation ranges.
            return self::inCidr($address, '2000::/3')
                && !self::inCidr($address, '2001::/23')
                && !self::inCidr($address, '2001:db8::/32')
                && !self::inCidr($address, '2002::/16')
                && !self::inCidr($address, '3fff::/20');
        }

        foreach ([
            '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
            '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
            '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            '168.63.129.16/32', // Azure's host-platform virtual address.
        ] as $range) {
            if (self::inCidr($address, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (strlen($host) > 253 || !str_contains($host, '.') || !preg_match('/^[a-z0-9.-]+$/D', $host)) {
            return false;
        }

        $labels = explode('.', $host);
        if (in_array(end($labels), ['localhost', 'local', 'internal', 'test', 'invalid', 'example', 'onion'], true)
            || ctype_digit(end($labels))) {
            return false;
        }

        foreach ($labels as $label) {
            if (strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $address, string $range): bool
    {
        [$network, $bits] = explode('/', $range);
        $packed = inet_pton($address);
        $base = inet_pton($network);
        if ($packed === false || $base === false || strlen($packed) !== strlen($base)) {
            return false;
        }

        $bytes = intdiv((int) $bits, 8);
        $remaining = (int) $bits % 8;
        if (substr($packed, 0, $bytes) !== substr($base, 0, $bytes)) {
            return false;
        }

        return $remaining === 0 || ((ord($packed[$bytes]) ^ ord($base[$bytes])) & (0xff << (8 - $remaining))) === 0;
    }
}
