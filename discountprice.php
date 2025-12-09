<?php
/**
 * Plugin Name: XYZ Supplier Discount
 * Description: Apply supplier-specific percentage discounts for WooCommerce products.
 * Version: 1.1.0
 * Author: Arash
 * Text Domain: xyz-supplier-discount
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class XYZSP_Supplier_Discount_Plugin {

    const META_KEY = '_xyz_supplier_discount_percent';

    public function __construct() {
        // Simple product: admin field + save
        add_action( 'woocommerce_product_options_pricing', array( $this, 'xyzsp_AddSupplierDiscountFieldSimple' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'xyzsp_save_supplier_discount_field_simple' ) );

        // Variable product: variation field + save
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'xyzsp_add_supplier_discount_field_variation' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'xyzsp_save_supplier_discount_field_variation' ), 10, 2 );

        // Base price logic (backend prices)
        add_filter( 'woocommerce_product_get_price', array( $this, 'xyzsp_apply_supplier_discount_price' ), 20, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'xyzsp_apply_supplier_discount_price' ), 20, 2 );

        // Remove these to avoid double-discount:
        // add_filter( 'woocommerce_get_price_html', array($this ,  'xyzsp_modify_price_html_for_supplier'), 20, 2 );
        // add_filter( 'woocommerce_variable_price_html', array($this ,  'xyzsp_modify_price_html_for_supplier'), 20, 2 );

        // AJAX variation data (when user selects a variation)
        add_filter( 'woocommerce_available_variation', array( $this, 'xyzsp_modify_variation_ajax_data' ), 20, 3 );
    }

    /* ===== Role check ===== */

    public function xyzsp_CurrentUserHasRole( $role ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();
        if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return false;
        }

        return in_array( $role, $user->roles, true );
    }

    public function xyzsp_current_user_is_supplier() {
        return $this->xyzsp_CurrentUserHasRole( 'supplier' );
    }

    /* ===== Admin fields: simple product ===== */

    public function xyzsp_AddSupplierDiscountFieldSimple() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        echo '<div class="options_group">';
        woocommerce_wp_text_input(
            array(
                'id'                => self::META_KEY,
                'label'             => __( 'درصد تخفیف تامین‌کننده (۰ تا ۱۰۰)', 'xyz-supplier-discount' ),
                'desc_tip'          => true,
                'description'       => __( 'این درصد فقط برای کاربرانی با نقش supplier اعمال می‌شود.', 'xyz-supplier-discount' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                    'max'  => '100',
                ),
            )
        );
        echo '</div>';
    }

    public function xyzsp_save_supplier_discount_field_simple( $product ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_POST[ self::META_KEY ] ) ) {
            $raw_value = wp_unslash( $_POST[ self::META_KEY ] );
            $value     = $this->xyzsp_validate_discount_value( $raw_value );

            if ( $value !== null ) {
                $product->update_meta_data( self::META_KEY, $value );
            } else {
                $product->delete_meta_data( self::META_KEY );
            }
        }
    }

    /* ===== Admin fields: variations ===== */

    public function xyzsp_add_supplier_discount_field_variation( $loop, $variation_data, $variation ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $meta_value = get_post_meta( $variation->ID, self::META_KEY, true );
        ?>
        <div class="options_group form-row form-row-full">
            <p class="form-field">
                <label for="<?php echo esc_attr( self::META_KEY . '_' . $loop ); ?>">
                    <?php esc_html_e( 'درصد تخفیف تامین‌کننده (۰ تا ۱۰۰)', 'xyz-supplier-discount' ); ?>
                </label>
                <input
                        type="number"
                        class="short"
                        name="<?php echo esc_attr( self::META_KEY . '[' . $loop . ']' ); ?>"
                        id="<?php echo esc_attr( self::META_KEY . '_' . $loop ); ?>"
                        value="<?php echo esc_attr( $meta_value ); ?>"
                        step="0.01"
                        min="0"
                        max="100"
                />
                <span class="description">
                    <?php esc_html_e( 'این درصد فقط برای نقش supplier اعمال می‌شود.', 'xyz-supplier-discount' ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    public function xyzsp_save_supplier_discount_field_variation( $variation_id, $i ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_POST[ self::META_KEY ][ $i ] ) ) {
            $raw_value = wp_unslash( $_POST[ self::META_KEY ][ $i ] );
            $value     = $this->xyzsp_validate_discount_value( $raw_value );

            if ( $value !== null ) {
                update_post_meta( $variation_id, self::META_KEY, $value );
            } else {
                delete_post_meta( $variation_id, self::META_KEY );
            }
        }
    }

    /* ===== Discount validation ===== */

    public function xyzsp_validate_discount_value( $value ) {
        $value = str_replace( ',', '.', $value );
        if ( $value === '' ) {
            return null;
        }

        if ( ! is_numeric( $value ) ) {
            return null;
        }

        $float = floatval( $value );
        if ( $float < 0 || $float > 100 ) {
            return null;
        }

        return $float;
    }

    /* ===== Get effective discount (product or variation) ===== */

    public function xyzsp_get_effective_discount( $product ) {
        $discount = 0;

        if ( $product->is_type( 'variation' ) ) {
            $discount_meta = $product->get_meta( self::META_KEY, true );
            if ( $discount_meta !== '' ) {
                $discount = floatval( $discount_meta );
            } else {
                $parent = wc_get_product( $product->get_parent_id() );
                if ( $parent ) {
                    $parent_meta = $parent->get_meta( self::META_KEY, true );
                    if ( $parent_meta !== '' ) {
                        $discount = floatval( $parent_meta );
                    }
                }
            }
        } else {
            $meta = $product->get_meta( self::META_KEY, true );
            if ( $meta !== '' ) {
                $discount = floatval( $meta );
            }
        }

        if ( $discount < 0 || $discount > 100 ) {
            return 0;
        }

        return $discount;
    }

    /* ===== Core discount calculation ===== */

    public function xyzsp_get_discounted_price( $product, $base_price = null ) {
        $discount = $this->xyzsp_get_effective_discount( $product );

        if ( $base_price === null ) {
            $base_price = $product->get_price();
        }

        $base_price = floatval( $base_price );

        if ( $discount <= 0 || $base_price <= 0 ) {
            return $base_price;
        }

        return $base_price * ( 1 - ( $discount / 100 ) );
    }

    /* ===== Filter backend price (get_price) ===== */

    public function xyzsp_apply_supplier_discount_price( $price, $product ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $price;
        }

        if ( ! $this->xyzsp_current_user_is_supplier() ) {
            return $price;
        }

        if ( $price === '' ) {
            return $price;
        }

        $price          = floatval( $price );
        $discounted     = $this->xyzsp_get_discounted_price( $product, $price );

        return wc_format_decimal( $discounted, wc_get_price_decimals() );
    }

    /* ===== AJAX variation data (front-end change after selecting variation) ===== */

    public function xyzsp_modify_variation_ajax_data( $variation_data, $product, $variation ) {

        if ( ! $this->xyzsp_CurrentUserHasRole( 'supplier' ) ) {
            return $variation_data;
        }

        // Get the already-discounted price (because get_price() is filtered)
        $price = $variation->get_price();

        if ( $price === '' ) {
            $price = $variation->get_regular_price();
        }

        if ( $price === '' ) {
            return $variation_data;
        }

        // DO NOT apply discount again — price is already discounted
        $discounted = floatval( $price );

        $variation_data['display_price']         = $discounted;
        $variation_data['display_regular_price'] = $discounted;
        $variation_data['price_html']            = wc_price( $discounted );

        return $variation_data;
    }


}

new XYZSP_Supplier_Discount_Plugin();
