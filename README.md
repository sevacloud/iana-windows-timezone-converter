# IANA Windows Timezone Converter (WordPress Plugin)

A lightweight WordPress plugin that provides a frontend tool to convert an IANA timezone to a Windows timezone.

## Features

- Shortcode-driven UI: `[iana_windows_tz_converter]`
- Dropdown of all available IANA timezones from PHP
- AJAX lookup for details:
  - IANA timezone
  - Windows timezone (via `IntlTimeZone::getWindowsID()` when available)
  - UTC offset (current)
  - timezone abbreviation
  - DST status
  - current local time
- Includes a small fallback mapping for common zones when `ext-intl` is unavailable

## Installation

1. Copy this plugin folder into `wp-content/plugins/`.
2. Activate **IANA Windows Timezone Converter** in WordPress admin.
3. Add shortcode `[iana_windows_tz_converter]` to any page/post.

## Notes

- Best accuracy for Windows mappings requires PHP `ext-intl` (`IntlTimeZone`).
- Fallback mapping includes common zones and `Europe/Istanbul` => `Turkey Standard Time`.
