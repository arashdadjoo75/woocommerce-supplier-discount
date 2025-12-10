=== XYZ Supplier Discount ===
Contributors: Arash
Tags: woocommerce, discount, supplier, pricing, product-meta
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Supplier Discount Percentage" field to WooCommerce products and applies the discount only for users with the `supplier` role.

== Description ==

XYZ Supplier Discount allows store admins and shop managers to define a discount percentage (0–100) on simple and variable products.
The discount will be applied *only* when the logged-in user has the `supplier` role.

### Features

- Adds a discount percentage field to simple products
- Adds a discount percentage field to each variation of variable products
- Variations fallback to parent product discount if left empty
- Discount applies only for users with the `supplier` role
- Prices update automatically in variation selection (AJAX)
- No theme file editing required
- Safe data handling with WooCommerce hooks
- Fully isolated from other user roles

== Installation ==

1. Upload the `xyz-supplier-discount` folder to `/wp-content/plugins/`.
2. Ensure the main plugin file `xyz-supplier-discount.php` is inside the folder.
3. Go to **Plugins → Installed Plugins** and activate **XYZ Supplier Discount**.
4. Make sure WooCommerce is active.
5. Create or confirm the presence of the user role `supplier`.

== Usage ==

### Simple Products:
- Edit a product → **General** tab.
- Enter a value between **0 and 100** in the "Supplier Discount Percentage" field.

### Variable Products:
- Edit a product → **Variations** tab.
- Each variation includes a "Supplier Discount Percentage" field.
- If left empty, the parent product discount is used.

== Front-End Behavior ==

- Logged-in users with the `supplier` role:
  - See prices **after the discount is applied**.
- All other users (customer, admin, guest):
  - See the **original price**, unchanged.

== Hooks Used and Purpose ==

- `woocommerce_product_options_pricing`
  Adds the discount field to simple product pricing.

- `woocommerce_admin_process_product_object`
  Saves product meta safely when the product is saved.

- `woocommerce_product_after_variable_attributes`
  Displays the discount field for each variation.

- `woocommerce_save_product_variation`
  Saves variation-level discount metadata.

- `woocommerce_product_get_price`
  Applies supplier discount to simple products in front-end.

- `woocommerce_product_variation_get_price`
  Applies supplier discount to variations.

- `woocommerce_available_variation`
  Updates variation price HTML dynamically during selection (AJAX).

- Role check using `wp_get_current_user()`
  Ensures the discount only affects users with the `supplier` role.

== Example Test Scenario ==

1. Create a simple product priced at **100000**.
2. Set the supplier discount to **20**.
3. Log in as a user with the `supplier` role:
   - The product price should show **80000**.
4. Log in as a normal customer:
   - The price remains **100000**.

== Changelog ==

= 1.0.0 =
* Initial release
* Added simple and variation discount fields
* Added supplier-only price filtering
* Integrated variation AJAX price update
* Added validation and fallback logic

