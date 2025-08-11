<?php

namespace WP_PluginSafetyValidator\Helpers;

if (!defined('ABSPATH')) die('Access denied.');

class VersionChecker
{
    /**
     * Returns true if $version is within ANY of the affected ranges.
     *
     * @param string $version                 The version to test (e.g., "3.1.4")
     * @param array  $response                The decoded API array that contains 'affected_versions'
     * @return bool
     */
    public static function isVersionVulnerable(string $version, array $response): bool
    {
        if (!isset($response['affected_versions'])) {
            return false;
        }

        // Normalize to an array of ranges
        $ranges = $response['affected_versions'];
        if (!is_array($ranges)) {
            return false;
        }
        // If it's a single associative range (has max_version/min_version keys), wrap it
        if (array_key_exists('min_version', $ranges) || array_key_exists('max_version', $ranges)) {
            $ranges = [$ranges];
        }

        foreach ($ranges as $range) {
            if (!is_array($range)) continue;
            if (self::isVersionInRange($version, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks a single range item shaped like:
     * [
     *   'min_version'  => ?string,
     *   'max_version'  => ?string,
     *   'min_operator' => ?string, // "<", "<=", ">", ">=", "=="
     *   'max_operator' => ?string,
     * ]
     */
    private static function isVersionInRange(string $version, $range): bool
    {
        $minVersion  = $range['min_version']  ?? null;
        $maxVersion  = $range['max_version']  ?? null;

        $minOperator = self::normalizeOp($range['min_operator'] ?? null, 'min'); // default >=
        $maxOperator = self::normalizeOp($range['max_operator'] ?? null, 'max'); // default <=

        // If both bounds are missing, treat as "all versions"
        if ($minVersion === null && $maxVersion === null) {
            return true;
        }

        if ($minVersion !== null) {
            if (!version_compare($version, $minVersion, $minOperator)) {
                return false;
            }
        }

        if ($maxVersion !== null) {
            if (!version_compare($version, $maxVersion, $maxOperator)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize/validate operator; provide sensible defaults:
     * - For min bound: default to ">="
     * - For max bound: default to "<="
     */
    private static function normalizeOp(?string $op, string $bound): string
    {
        if ($op === null || $op === '') {
            return $bound === 'min' ? '>=' : '<=';
        }

        $map = [
            'lt'  => '<',
            'lte' => '<=',
            'gt'  => '>',
            'gte' => '>=',
            'eq'  => '==',
            '='   => '==',
            '<'   => '<',
            '<='  => '<=',
            '>'   => '>',
            '>='  => '>=',
            '=='  => '==',
        ];

        $key = strtolower(trim($op));

        return $map[$key] ?? ($bound === 'min' ? '>=' : '<=');
    }
}