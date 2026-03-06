# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress plugin that connects a WordPress site to the RebelBoost page caching/optimization proxy. It handles automatic cache invalidation, surrogate key injection for granular CDN purging, and an optional proxy mode that routes requests through RebelBoost without DNS changes.

**Version** is tracked in two places that must stay in sync: `rebelboost.php` (header comment + `REBELBOOST_VERSION` constant) and `readme.txt` (Stable tag).

## Architecture

Entry point: `rebelboost.php` → `RebelBoost::init()` (singleton in `class-rebelboost.php`).

### Core Classes (all in `includes/`)

- **RebelBoost** (`class-rebelboost.php`) — Singleton orchestrator. Loads all dependencies, registers hooks, provides `get_host_url()` (auto-detects local dev via `wordpress.local` hostname) and `is_connected()`.
- **RebelBoost_API_Client** (`class-api-client.php`) — HTTP client for the RebelBoost External API (`/_rebelboost/extapi/v1/`). All requests include `Host` and `X-Forwarded-Host` headers set to the site's domain. Methods: `purge_page()`, `purge_category()`, `purge_all()`, `register_origin()`, `test_connection()`. Retries once on connection failure.
- **RebelBoost_Cache_Invalidation** (`class-cache-invalidation.php`) — Hooks into WordPress lifecycle events (post transitions, comments, theme/plugin changes, WooCommerce, menus, terms). Queues purges and executes them at shutdown via `register_shutdown_function`. Purge types: path, category, all.
- **RebelBoost_Surrogate_Keys** (`class-surrogate-keys.php`) — Injects `Surrogate-Key` and category headers on frontend responses. Tags include `single:<id>`, `post:<id>`, `author:<id>`, `tax:<id>`, `pageType:<type>`.
- **RebelBoost_Settings** (`class-settings.php`) — WordPress Settings API registration under Settings > RebelBoost. Manages options: `rebelboost_mode`, `rebelboost_api_key`, `rebelboost_auto_purge`, `rebelboost_purge_on_comment`, `rebelboost_surrogate_keys`, `rebelboost_category_header`.
- **RebelBoost_Admin** (`class-admin.php`) — Admin bar purge buttons, post editor meta box, AJAX handlers for purge actions. Assets in `assets/js/admin.js` and `assets/css/admin.css`.
- **RebelBoost_Proxy_Mode** (`class-proxy-mode.php`) — Intercepts frontend requests at `plugins_loaded` (priority 0), fetches optimized page from RebelBoost, serves it and exits. Uses loop token (MD5 of API key) for origin fetch detection. Falls back to normal WordPress if proxy is unreachable.
- **RebelBoost_CLI** (`class-cli.php`) — WP-CLI commands under `wp rebelboost`.

### Two Operating Modes

1. **Integration** (default) — Site DNS points to RebelBoost. Plugin handles cache invalidation and surrogate keys.
2. **Proxy** — No DNS changes needed. Plugin intercepts requests and proxies through RebelBoost. Asset URLs rewritten.

### Key Conventions

- `origin_scheme` values: `0` = HTTPS, `1` = HTTP (matches rebelboost-config convention)
- Local dev detection: hostname `wordpress.local` → API base URL `http://rebelboost:4001`
- Production API base: `https://ingressv2.rebelboost.com`
- All wp_options prefixed with `rebelboost_`
- Post meta: `_rebelboost_last_purge`

## WP-CLI Commands

```
wp rebelboost status              # Show connection status
wp rebelboost test                # Test API connection
wp rebelboost mode [proxy|integration]  # Get/set operating mode
wp rebelboost purge all           # Purge entire cache
wp rebelboost purge page /path/   # Purge specific page
wp rebelboost purge category slug # Purge by category
```

## Requirements

- WordPress 5.6+
- PHP 7.4+
