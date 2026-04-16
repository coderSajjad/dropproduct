# Quick Bulk Price Adjuster — Price Slasher

> **Available in:** DropProduct Free (v1.0.1+)

The Price Slasher lets you change the price of multiple products at once — by a percentage or a fixed amount — without leaving the grid.

---

## How to Use

### Step 1 — Select Products

Tick the checkbox in the left-most column of each product row you want to adjust. You can also use the header checkbox to select all visible products at once.

The **Price Slasher** button in the toolbar shows a badge with the number of selected products.

### Step 2 — Open the Price Slasher Bar

Click the **⚡ Price Slasher** button in the toolbar (above the grid). The adjustment bar slides into view.

### Step 3 — Configure the Adjustment

| Control | Options |
|---------|---------|
| **Price field** | Regular Price / Sale Price / Both |
| **Operation** | Increase / Decrease |
| **Amount** | Any positive number |
| **Type** | % (percentage) or $ (fixed amount) |

**Examples:**

- Increase all selected Regular Prices by 10% → select "Regular Price", "Increase", enter `10`, choose `%`
- Decrease Sale Price by $5 → select "Sale Price", "Decrease", enter `5`, choose `$`

### Step 4 — Apply

Click **Apply**. Prices update via AJAX immediately — no page reload. A green flash on each updated price input confirms the save.

### Step 5 — Adjust Again (Optional)

The bar stays open after applying so you can make further adjustments. Click the Price Slasher button again to close it, or use the ✕ clear button to deselect all products.

---

## Safety Rules

- Final prices are **always rounded to 2 decimal places**
- Prices **cannot go below $0** — they are clamped automatically
- If a Sale Price ends up ≥ Regular Price after adjustment, the Sale Price is **automatically cleared**

---

## Tips

- Use **Both** to maintain the gap between regular and sale pricing when doing a sitewide price increase.
- Select all products with the header checkbox for a global price change.
- Check the Profit and Margin columns (available in v1.0.2+) after adjusting to confirm your margins remain healthy.
