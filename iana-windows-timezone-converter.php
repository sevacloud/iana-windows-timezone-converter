<?php
/**
 * Plugin Name: IANA Windows Timezone Converter
 * Description: Adds a shortcode that lets visitors select an IANA timezone and view the corresponding Windows timezone plus related properties.
 * Version: 1.0.0
 * Author: Codex
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert an IANA timezone ID (e.g. Europe/Istanbul) to a Windows timezone name.
 */
function iwtzc_iana_to_windows_tz(string $iana_tz): ?string
{
    $iana_tz = trim($iana_tz);

    if ($iana_tz === '' || !in_array($iana_tz, timezone_identifiers_list(), true)) {
        return null;
    }

    if (class_exists('IntlTimeZone') && method_exists('IntlTimeZone', 'getWindowsID')) {
        $win = \IntlTimeZone::getWindowsID($iana_tz);

        if ($win !== false && is_string($win) && $win !== '') {
            return $win;
        }
    }

    $fallback_map = array(
        'Europe/Istanbul' => 'Turkey Standard Time',
        'Europe/London' => 'GMT Standard Time',
        'Europe/Paris' => 'Romance Standard Time',
        'Europe/Berlin' => 'W. Europe Standard Time',
        'America/New_York' => 'Eastern Standard Time',
        'America/Chicago' => 'Central Standard Time',
        'America/Denver' => 'Mountain Standard Time',
        'America/Los_Angeles' => 'Pacific Standard Time',
        'Asia/Tokyo' => 'Tokyo Standard Time',
        'Asia/Kolkata' => 'India Standard Time',
        'Australia/Sydney' => 'AUS Eastern Standard Time',
        'UTC' => 'UTC',
    );

    return $fallback_map[$iana_tz] ?? null;
}

/**
 * Build rich timezone details for a given IANA timezone ID.
 */
function iwtzc_get_timezone_details(string $iana_tz): array
{
    if (!in_array($iana_tz, timezone_identifiers_list(), true)) {
        return array(
            'success' => false,
            'message' => __('Invalid timezone provided.', 'iana-windows-timezone-converter'),
        );
    }

    $tz = new DateTimeZone($iana_tz);
    $now = new DateTimeImmutable('now', $tz);
    $offset_seconds = $tz->getOffset($now);

    $hours = intdiv(abs($offset_seconds), 3600);
    $minutes = intdiv(abs($offset_seconds) % 3600, 60);
    $sign = $offset_seconds >= 0 ? '+' : '-';
    $formatted_offset = sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);

    $dst = (bool) $now->format('I');
    $windows_tz = iwtzc_iana_to_windows_tz($iana_tz);

    return array(
        'success' => true,
        'iana_timezone' => $iana_tz,
        'windows_timezone' => $windows_tz,
        'utc_offset' => $formatted_offset,
        'offset_seconds' => $offset_seconds,
        'abbreviation' => $now->format('T'),
        'is_dst' => $dst,
        'current_local_time' => $now->format('Y-m-d H:i:s'),
    );
}

/**
 * Shortcode output.
 */
function iwtzc_shortcode(): string
{
    $timezones = timezone_identifiers_list();

    wp_enqueue_style(
        'iwtzc-styles',
        plugins_url('assets/iwtzc.css', __FILE__),
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'iwtzc-script',
        plugins_url('assets/iwtzc.js', __FILE__),
        array(),
        '1.0.0',
        true
    );

    wp_localize_script('iwtzc-script', 'iwtzcData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iwtzc_nonce'),
        'messages' => array(
            'choose' => __('Choose a timezone to view conversion details.', 'iana-windows-timezone-converter'),
            'error' => __('Unable to load timezone details. Please try again.', 'iana-windows-timezone-converter'),
            'notMapped' => __('No Windows timezone mapping found in current environment.', 'iana-windows-timezone-converter'),
            'yes' => __('Yes', 'iana-windows-timezone-converter'),
            'no' => __('No', 'iana-windows-timezone-converter'),
        ),
    ));

    ob_start();
    ?>
    <div class="iwtzc-wrap">
        <label for="iwtzc-timezone"><strong><?php esc_html_e('Select IANA Timezone', 'iana-windows-timezone-converter'); ?></strong></label>
        <select id="iwtzc-timezone" class="iwtzc-select">
            <option value=""><?php esc_html_e('-- Choose timezone --', 'iana-windows-timezone-converter'); ?></option>
            <?php foreach ($timezones as $timezone) : ?>
                <option value="<?php echo esc_attr($timezone); ?>"><?php echo esc_html($timezone); ?></option>
            <?php endforeach; ?>
        </select>

        <div id="iwtzc-status" class="iwtzc-status" aria-live="polite">
            <?php esc_html_e('Choose a timezone to view conversion details.', 'iana-windows-timezone-converter'); ?>
        </div>

        <table id="iwtzc-results" class="iwtzc-results" hidden>
            <tbody>
                <tr><th><?php esc_html_e('IANA Timezone', 'iana-windows-timezone-converter'); ?></th><td data-key="iana_timezone">-</td></tr>
                <tr><th><?php esc_html_e('Windows Timezone', 'iana-windows-timezone-converter'); ?></th><td data-key="windows_timezone">-</td></tr>
                <tr><th><?php esc_html_e('UTC Offset (Now)', 'iana-windows-timezone-converter'); ?></th><td data-key="utc_offset">-</td></tr>
                <tr><th><?php esc_html_e('Abbreviation', 'iana-windows-timezone-converter'); ?></th><td data-key="abbreviation">-</td></tr>
                <tr><th><?php esc_html_e('DST Active', 'iana-windows-timezone-converter'); ?></th><td data-key="is_dst">-</td></tr>
                <tr><th><?php esc_html_e('Current Local Time', 'iana-windows-timezone-converter'); ?></th><td data-key="current_local_time">-</td></tr>
            </tbody>
        </table>
    </div>
    <?php

    return (string) ob_get_clean();
}
add_shortcode('iana_windows_tz_converter', 'iwtzc_shortcode');

/**
 * AJAX handler for timezone details lookup.
 */
function iwtzc_ajax_lookup(): void
{
    check_ajax_referer('iwtzc_nonce', 'nonce');

    $timezone = isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : '';

    if ($timezone === '') {
        wp_send_json_error(array(
            'message' => __('Timezone is required.', 'iana-windows-timezone-converter'),
        ), 400);
    }

    $details = iwtzc_get_timezone_details($timezone);

    if (empty($details['success'])) {
        wp_send_json_error(array(
            'message' => $details['message'] ?? __('Unknown error.', 'iana-windows-timezone-converter'),
        ), 400);
    }

    wp_send_json_success($details);
}

add_action('wp_ajax_iwtzc_lookup_timezone', 'iwtzc_ajax_lookup');
add_action('wp_ajax_nopriv_iwtzc_lookup_timezone', 'iwtzc_ajax_lookup');
