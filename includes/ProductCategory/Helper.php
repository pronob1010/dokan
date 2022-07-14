<?php

namespace WeDevs\Dokan\ProductCategory;

/**
 * Product category helper class.
 *
 * @since DOKAN_SINCE
 */
class Helper {

    /**
     * Returns 'true' if category type selection for Products is single, 'false' if type is multiple
     *
     * @since DOKAN_SINCE
     *
     * @return boolean
     */
    public static function product_category_selection_is_single() {
        return 'single' === dokan_get_option( 'product_category_style', 'dokan_selling', 'single' );
    }

    /**
     * Returns products category.
     *
     * @since DOKAN_SINCE
     *
     * @param integer $post_id
     *
     * @return array
     */
    public static function get_saved_products_category( $post_id = 0 ) {
        $is_single           = self::product_category_selection_is_single();
        $chosen_cat          = get_post_meta( $post_id, 'chosen_product_cat', true );
        $default_product_cat = get_term( get_option( 'default_product_cat' ) );
        $data                = [
            'chosen_cat'          => [],
            'is_single'           => $is_single,
            'default_product_cat' => $default_product_cat,
        ];

        // if post id is empty return default data
        if ( ! $post_id ) {
            return $data;
        }

        // if chosen cat is already exists in database return existing chosen cat
        if ( is_array( $chosen_cat ) && ! empty( $chosen_cat ) ) {
            $data['chosen_cat'] = $is_single ? [ reset( $chosen_cat ) ] : $chosen_cat;
            return $data;
        }

        // no store chosen cat was found on database, in that case we need to generate chosen cat from existing categories
        $all_parents = [];

        // get product terms
        $terms = wp_get_post_terms( $post_id, 'product_cat', [ 'fields' => 'all' ] );

        // If multiple category is selected, choose the common parents child as chosen category.
        foreach ( $terms as $term ) {
            $all_ancestors = get_ancestors( $term->term_id, 'product_cat' );
            $old_parent    = empty( $all_ancestors ) ? $term->term_id : end( $all_ancestors );

            if ( ! array_key_exists( $old_parent, $all_parents ) || $all_parents[ $old_parent ]['size'] < count( $all_ancestors ) ) {
                $all_parents[ $old_parent ]['size']   = count( $all_ancestors );
                $all_parents[ $old_parent ]['child']  = $term->term_id;
            }
        }

        // get chosen cat from $all_parennts
        $chosen_cat = array_map(
            function ( $value ) {
                return $value['child'];
            },
            $all_parents
        );

        $chosen_cat = array_values( $chosen_cat );

        // check if single category is selected, in that case get the first item
        $data['chosen_cat'] = $is_single ? [ reset( $chosen_cat ) ] : $chosen_cat;
        if ( ! empty( $data['chosen_cat'] ) ) {
            self::set_object_terms_from_chosen_categories( $post_id, $data['chosen_cat'] );
        }

        return $data;
    }

    /**
     * Set all ancestors to a product from chosen product categories
     *
     * @since DOKAN_SINCE
     *
     * @param int $post_id
     * @param array $chosen_categories
     *
     * @return void
     */
    public static function set_object_terms_from_chosen_categories( $post_id, $chosen_categories = [] ) {
        if ( empty( $chosen_categories ) || ! is_array( $chosen_categories ) ) {
            return;
        }

        // we need to assign all ancestor of chosen category to add to the given product
        $all_ancestors = [];
        foreach ( $chosen_categories as $term_id ) {
            $all_ancestors = array_merge( $all_ancestors, get_ancestors( $term_id, 'product_cat' ), [ $term_id ] );
        }

        // save chosen cat to database
        update_post_meta( $post_id, 'chosen_product_cat', $chosen_categories );
        // add all ancestor and chosen cat as product category

        // We have to convert all the categories into integer because if an category is string ex: '23' not int ex: 23
        // wp_set_object_terms will create a new term named 23. we don't want that.
        $all_ancestors = array_map( 'absint', $all_ancestors );

        wp_set_object_terms( $post_id, array_unique( $all_ancestors ), 'product_cat' );
    }

    /**
     * Get category ancestors HTML;
     *
     * @since DOKAN_SINCE
     *
     * @param integer $term
     *
     * @return string
     */
    public static function get_ancestors_html( $term ) {
        $all_parents   = get_ancestors( $term, 'product_cat' );
        $all_parents   = array_reverse( $all_parents );
        $parents_count = count( $all_parents );
        $html          = '';

        foreach ( $all_parents as $index => $value ) {
            $label = '<span class="dokan-selected-category-product">' . get_term_field( 'name', $value, 'product_cat' ) . '</span><span class="dokan-selected-category-icon"><i class="fas fa-chevron-right"></i></span>';
            $html .= $label;
        }
        $html .= '<span class="dokan-selected-category-product dokan-cat-selected">' . get_term_field( 'name', $term, 'product_cat' ) . '</span>';

        return $html;
    }
}