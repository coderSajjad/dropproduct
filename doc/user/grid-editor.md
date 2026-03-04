# Grid Editor & Auto-Save

## What Does It Do?

The grid editor lets you edit all product details directly in the table — no need to open each product in a separate page. Changes are saved automatically as you edit.

---

## Editable Fields

| Field | How to Edit | Notes |
|-------|------------ |-------|
| **Title** | Click the title text and type | Required for publishing |
| **Description** | Click the pencil icon (📝) | Opens a popup editor |
| **Regular Price** | Click the price field and type a number | Required for publishing |
| **Sale Price** | Click the sale price field | Must be lower than regular price |
| **SKU** | Click and type | Must be unique — duplicates are rejected |
| **Stock Status** | Select from dropdown | In stock / Out of stock / On backorder |
| **Category** | Select from dropdown | Shows all WooCommerce categories |

---

## Auto-Save

**Every change saves automatically.** When you edit a field:

1. ⏳ The field turns **amber** briefly (saving)
2. ✅ The field turns **green** to confirm it was saved
3. ❌ If something goes wrong, the field turns **red** and an error message appears

You never need to click a "Save" button — it's all handled for you.

---

## Regular Price & Sale Price

You can set both a **Regular Price** and an optional **Sale Price** for each product:

- The **Regular Price** is the standard price shown to customers
- The **Sale Price** is the discounted price (when on sale)
- If you enter a sale price that is **higher than or equal to** the regular price, a **red warning tooltip** will appear: *"Sale price must be lower than regular price."*

---

## Description Editor

Product descriptions are edited in a popup modal (because they can be longer than a single line):

1. Click the **pencil icon** (📝) next to the product
2. The description editor popup opens
3. Type or edit the description
4. Click **"Save"**
5. The popup closes and your changes are saved

When a product has a description, the pencil icon turns **green** with a small **green dot** indicator.

---

## Hover Image Preview

Hover your mouse over any product thumbnail to see a **full-size preview** of the image. The preview follows your cursor. This is great for checking image quality without opening a new tab.

---

## Product Status

Each product shows its current status:

| Status | Meaning |
|--------|---------|
| **Draft** | Product is created but not visible on your store |
| **Publish** | Product is live and visible to customers |

---

## Deleting Products

Click the **trash icon** (🗑️) on the right side of any product row to delete it. You'll be asked to confirm before the product is permanently deleted.

---

## Tips

- Changes are saved to the database immediately — there's no "undo"
- If you see an error on a SKU, it means another product already uses that code
- Newly uploaded products always appear at the **top** of the grid
- The gallery badge (e.g., "+3") on thumbnails shows how many additional images a product has
