# Ultimate Order Shield — User Guide

> **Available in:** DropProduct Free (v1.0.2+)

The Ultimate Order Shield protects your WooCommerce store from fake orders, fraud, and bot checkouts. It works in the background during checkout and either blocks suspicious orders outright or places them on hold for your manual review.

---

## Enabling Order Shield

1. Go to **DropProduct → ⚙️ Settings**
2. Toggle **Enable Ultimate Order Shield** to ON
3. Save settings

Once enabled, all WooCommerce checkouts pass through the fraud engine automatically.

---

## The Risk Score Explained

Every checkout attempt receives a **risk score** (0–200). Points are added for suspicious signals:

| Signal | Points |
|--------|--------|
| Email domain is in the disposable list | +40 |
| More than N orders from same IP in 1 hour | +30 |
| Same phone or email seen in ≥2 past orders | +25 |
| Excessive failed payment attempts | +25 (also triggers instant block) |
| IP country ≠ billing country | +20 |
| Checkout completed faster than speed threshold | +20 |

**Instant blocks** (regardless of score):
- Honeypot field was filled (almost certainly a bot)
- Name, phone, or email matches the blacklist

---

## Settings

Go to **DropProduct → 🛡️ Order Shield** to configure:

### General

| Setting | Description |
|---------|-------------|
| **Enable Order Shield** | Master on/off switch |
| **Action Mode** | Block completely (abort checkout) or Force On-Hold (mark for review) |
| **Block Threshold** | Risk score at which the action mode kicks in (default: 70) |
| **Review Threshold** | Orders above this score but below block are set On-Hold (default: 40) |

### Detection Rules

| Setting | Description |
|---------|-------------|
| **Max Orders Per IP / Hour** | Exceeding this triggers the velocity rule (+30 pts) |
| **Failed Payment Threshold** | Exceeding this triggers an instant block |
| **Checkout Speed Threshold** | Orders faster than this (in seconds) score +20 pts |
| **IP / Country Mismatch** | Toggle the geolocation check on/off |

### Payment Protection

| Setting | Description |
|---------|-------------|
| **Restrict COD for High-Risk** | Hide Cash on Delivery for customers whose risk score exceeds the COD threshold |
| **COD Block Threshold** | Risk score at which COD is hidden |

### Disposable Email Domains

A text area where you can manage the list of disposable email domains (one per line). Orders using these domains score +40 risk points. The default list includes common throwaway domains.

### Blacklist

One entry per line — can be a name, phone number, or email address. Any checkout matching an entry in the blacklist is blocked immediately regardless of risk score.

---

## What Happens When an Order is Blocked?

The customer sees an error message at checkout and cannot complete the order. No order is created.

## What Happens When an Order is On-Hold?

The order is created in WooCommerce with status **On-Hold**. A private note is added to the order explaining which rules triggered and what the final risk score was. You can then review the order in WooCommerce → Orders and choose to accept or cancel it.

---

## Activity Logs

Go to **DropProduct → 🛡️ Order Shield → Activity Logs** to see every fraud check with:

- IP address, email, risk score
- Which rules triggered
- Final action (ALLOW / ON_HOLD / BLOCK)
- Timestamp and linked order (if created)

Use the filter pills to view only blocked, on-hold, or allowed events. Individual log entries can be deleted, or you can clear all logs at once.

---

## Privacy Note

No data is sent to external servers. All processing happens locally using WooCommerce's built-in Geolocation database. Logs are stored in your own WordPress database.
