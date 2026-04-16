# Feature: Ultimate Order Shield

> **Since:** v1.0.2  
> **Files:** `includes/class-dropproduct-fraud-shield.php`, `includes/class-dropproduct-fraud-logger.php`, `admin/views/fraud-shield-page.php`, `assets/css/admin-fraud-shield.css`, `assets/js/admin-fraud-shield.js`

---

## Architecture Overview

```
WC Checkout
    │
    ├── woocommerce_checkout_process          → risk_engine() → BLOCK (wc_add_notice)
    ├── woocommerce_checkout_create_order     → risk_engine() → ON_HOLD (order status + note)
    └── woocommerce_available_payment_gateways → filter COD if score ≥ cod_threshold
```

The shield runs twice for blocked orders:
1. `woocommerce_checkout_process` — aborts before the order is created
2. `woocommerce_checkout_create_order` — runs for on-hold cases where an order needs to exist

---

## Class: `DropProduct_Fraud_Shield`

**File:** `includes/class-dropproduct-fraud-shield.php`  
**Instantiated by:** `DropProduct::define_fraud_shield_hooks()`  
**Global reference:** `$GLOBALS['dropproduct_fraud_shield_instance']`

### Constructor

```php
public function __construct( DropProduct_Fraud_Logger $logger )
```

Reads settings from `get_option( 'dropproduct_fraud_shield_settings', [] )` and merges with defaults.

### Default Settings

```php
[
    'enabled'                  => false,
    'block_threshold'          => 70,
    'review_threshold'         => 40,
    'action_mode'              => 'block',      // 'block' | 'hold'
    'max_orders_per_ip'        => 3,
    'failed_payment_threshold' => 5,
    'checkout_time_threshold'  => 5,            // seconds
    'enable_ip_country_check'  => true,
    'enable_cod_restriction'   => false,
    'cod_restriction_threshold'=> 30,
    'disposable_domains'       => "mailinator.com\ntrashmail.com\n...",
    'blacklist'                => '',
]
```

### Public Methods

| Method | Description |
|--------|-------------|
| `register_hooks()` | Registers all WC action/filter hooks. Called via `woocommerce_loaded`. |
| `inject_honeypot()` | Adds hidden `<input>` + timestamp field to checkout form. |
| `get_settings()` | Returns current config array (used by admin page). |

### Private Methods

| Method | Description |
|--------|-------------|
| `run_shield( $data )` | Core engine: runs pre-checks then scoring, returns `['action', 'score', 'rules']`. |
| `collect_checkout_data( $posted )` | Builds the `$data` array from `$_POST`. |
| `pre_checks( $data )` | Honeypot + blacklist — returns `'BLOCK'` or `null`. |
| `score( $data )` | Returns `['score' => int, 'rules' => string[]]`. |
| `exec_decision( $action, $score, $rules, $order )` | Applies block/hold logic and logs. |
| `is_disposable_email( $email )` | Checks domain against the configured list. |
| `get_ip_orders_last_hour( $ip )` | Counts orders from same IP in last 60 min via `$wpdb`. |
| `is_repeated_contact( $email, $phone )` | Looks for matching contact in existing orders. |
| `get_ip_country( $ip )` | Returns country code using `WC_Geolocation`. |
| `restrict_cod( $gateways )` | Filters `woocommerce_available_payment_gateways`. |

---

## Risk Scoring Rules

| Rule constant / description | Points | Trigger |
|-----------------------------|--------|---------|
| `disposable_email` | +40 | Extracted domain in disposable list |
| `ip_velocity` | +30 | Orders from IP in last hour ≥ `max_orders_per_ip` |
| `repeated_contact` | +25 | Same email or phone in ≥2 past orders |
| `ip_country_mismatch` | +20 | `WC_Geolocation` country ≠ billing country |
| `failed_payments` | +25 + **instant BLOCK** | `_wc_failed_payment_count` meta ≥ threshold |
| `checkout_speed` | +20 | Elapsed since `_dropproduct_checkout_start` < threshold |

**Pre-checks (bypass scoring):**

| Check | Result |
|-------|--------|
| Honeypot field filled | `BLOCK` immediately |
| Name / phone / email in blacklist | `BLOCK` immediately |

**Decision logic:**

```
score >= block_threshold  →  action_mode == 'block' ? BLOCK : ON_HOLD
score >= review_threshold →  ON_HOLD
score <  review_threshold →  ALLOW
```

---

## Class: `DropProduct_Fraud_Logger`

**File:** `includes/class-dropproduct-fraud-logger.php`  
**Global reference:** `$GLOBALS['dropproduct_fraud_logger_instance']`

### Database Table

Table name: `{wpdb->prefix}dropproduct_fraud_log`

```sql
CREATE TABLE {prefix}dropproduct_fraud_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NULL,
    ip_address      VARCHAR(45)     NOT NULL DEFAULT '',
    email           VARCHAR(200)    NOT NULL DEFAULT '',
    risk_score      SMALLINT        NOT NULL DEFAULT 0,
    triggered_rules LONGTEXT        NOT NULL,  -- JSON array of rule strings
    final_action    VARCHAR(10)     NOT NULL DEFAULT 'ALLOW',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip       (ip_address),
    KEY idx_action   (final_action),
    KEY idx_created  (created_at)
);
```

Table is created on-demand via `dbDelta()` in `DropProduct_Fraud_Logger::create_table()` (called from `DropProduct::define_fraud_shield_hooks()`).

### Public Methods

```php
// Create/migrate the table (safe to call repeatedly — uses dbDelta).
public static function create_table(): void

// Insert a log row.
public function log( int $order_id, string $ip, string $email,
                     int $score, array $rules, string $action ): void

// Retrieve paginated rows with optional action filter.
public function get_logs( int $limit, int $offset, string $action_filter = '' ): array
// Returns: [ 'rows' => stdClass[], 'total' => int ]

// Aggregate counts per action for the stats bar.
public function get_summary(): array
// Returns: [ 'total' => int, 'BLOCK' => int, 'ON_HOLD' => int, 'ALLOW' => int ]

// Delete a single log entry.
public function delete_log( int $id ): bool

// Truncate the entire table.
public function clear_logs(): void
```

---

## AJAX Endpoints

All endpoints use nonce `dpshield_admin` (created in `wp_localize_script`).

| Action | Handler | Capability |
|--------|---------|------------|
| `dropproduct_save_fraud_settings` | `DropProduct_Fraud_Shield::ajax_save_settings()` | `manage_woocommerce` |
| `dropproduct_fraud_delete_log` | `DropProduct_Fraud_Logger::ajax_delete_log()` | `manage_woocommerce` |
| `dropproduct_fraud_clear_logs` | `DropProduct_Fraud_Logger::ajax_clear_logs()` | `manage_woocommerce` |

### Settings payload (POST fields)

```
enabled, action_mode, block_threshold, review_threshold,
max_orders_per_ip, failed_payment_threshold, checkout_time_threshold,
enable_ip_country_check, enable_cod_restriction, cod_restriction_threshold,
disposable_domains, blacklist, nonce
```

---

## Admin Page

**File:** `admin/views/fraud-shield-page.php`  
**Route:** `admin.php?page=dropproduct-fraud-shield`  
**Registered by:** `DropProduct_Admin::register_menus()`

The page uses two globals set in `DropProduct::define_fraud_shield_hooks()`:

```php
$GLOBALS['dropproduct_fraud_shield_instance'] // DropProduct_Fraud_Shield
$GLOBALS['dropproduct_fraud_logger_instance'] // DropProduct_Fraud_Logger
```

**Tabs:**
- `?tab=settings` — Settings form (rendered from `$cfg = $shield->get_settings()`)
- `?tab=logs` — Paginated log table with filter pills (ALLOW / ON_HOLD / BLOCK)

---

## Frontend Assets

| File | Loaded on |
|------|----------|
| `assets/css/admin-fraud-shield.css` | Order Shield admin page only |
| `assets/js/admin-fraud-shield.js` | Order Shield admin page only |

**JS localization object:** `dpShield`

```js
{
    ajaxUrl, nonce,
    saving, saved, saveBtnLabel, networkError,
    confirmDelete, confirmClear, logsCleared
}
```

---

## Honeypot Implementation

Two hidden fields are injected into the WooCommerce checkout form via `woocommerce_checkout_fields`:

```html
<!-- Honeypot — should remain empty if human -->
<input type="text" name="_dropproduct_hp" id="_dropproduct_hp"
       autocomplete="off" tabindex="-1" aria-hidden="true"
       style="position:absolute;left:-9999px;opacity:0;" />

<!-- Timestamp — used to calculate checkout duration -->
<input type="hidden" name="_dropproduct_checkout_start" value="[unix_timestamp]" />
```

The timestamp is floored to the nearest second to avoid fingerprinting.

---

## Adding Custom Scoring Rules

Use the `dropproduct_fraud_score` filter (planned for v1.0.3):

```php
// Not yet available — reserved hook name for future Pro extension.
add_filter( 'dropproduct_fraud_score', function( $result, $data ) {
    if ( some_custom_check( $data ) ) {
        $result['score']  += 15;
        $result['rules'][] = 'custom_rule';
    }
    return $result;
}, 10, 2 );
```
