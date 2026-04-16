# Cost-to-Profit Tracker

> **Available in:** DropProduct Free (v1.0.2+)

The Cost-to-Profit Tracker adds three new columns to the product grid so you can track your purchase cost, calculated profit, and margin percentage for every product — all in real time.

---

## How to Use

### 1. Enter a Cost Price

In the product grid, locate the **Cost Price** column (after the Actions column). Click the input and type your purchase cost for that product.

The field uses the same `$` currency prefix styling as the Regular Price and Sale Price columns. It does **not** appear anywhere on your live store — it is internal only.

### 2. Watch Profit & Margin Update Instantly

As you type, the **Profit** and **Margin %** columns update immediately in the browser — no save button, no page reload.

| Column | Formula |
|--------|---------|
| **Profit** | Selling Price − Cost Price |
| **Margin %** | (Profit ÷ Selling Price) × 100 |

**Selling Price used:** If the product has a Sale Price set, that is used. Otherwise the Regular Price is used.

### 3. Automatic Save

The cost price is saved to the database automatically:

- **While typing:** saved 600 ms after you stop typing (debounced)
- **On blur:** saved immediately when you click away from the field

A brief colour change on the input confirms the save:
- **Amber** → saving in progress
- **Green** → saved successfully
- **Red** → save failed (check your connection)

---

## Colour Coding

| Colour | Meaning |
|--------|---------|
| 🟢 **Green** | Profit / Margin is positive |
| 🔴 **Red** | Negative margin (selling below cost) |
| **—** (grey dash) | Cost price or selling price not set yet |

---

## When Does It Recalculate?

The Profit and Margin figures recalculate automatically whenever you change:

- The **Cost Price** (instant, as you type)
- The **Regular Price** (recalculates on blur / save)
- The **Sale Price** (recalculates on blur / save)

---

## Where Is the Data Stored?

Cost prices are saved in WordPress's `wp_postmeta` table under the key `_dropproduct_cost_price`. This is a **private** meta key — it is never exposed to customers or shown on the front end.

---

## Tips

- Set the cost price **before** publishing so your records are complete from day one.
- If you sell at different prices to different customers, enter your average wholesale cost.
- A negative margin shown in red is a clear signal you need to adjust your pricing before publishing.
