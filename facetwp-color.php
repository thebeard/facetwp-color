<?php
/*
Plugin Name: FacetWP - Color
Plugin URI: https://facetwp.com/
Description: A FacetWP facet to filter products by color
Version: 1.0
Author: Matt Gibbs
Author URI: https://facetwp.com/
GitHub Plugin URI: https://github.com/mgibbs189/facetwp-color

Copyright 2014 Matt Gibbs

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


function fwp_color_facet( $facet_types ) {
    $facet_types['color'] = new FacetWP_Facet_Color();
    return $facet_types;
}
add_filter( 'facetwp_facet_types', 'fwp_color_facet' );


/**
 * The Color facet class
 */
class FacetWP_Facet_Color
{

    function __construct() {
        $this->label = __( 'Color', 'fwp' );
    }


    /**
     * Load the available choices
     */
    function load_values( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $where_clause = $params['where_clause'];

        // Orderby
        $orderby = 'counter DESC, f.facet_display_value ASC';

        // Sort by depth just in case
        $orderby = "f.depth, $orderby";

        // Limit
        $limit = ctype_digit( $facet['count'] ) ? $facet['count'] : 10;
        $orderby = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );
        $where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

        $sql = "
        SELECT f.facet_value, f.facet_display_value, f.term_id, f.parent_id, f.depth, COUNT(*) AS counter
        FROM {$wpdb->prefix}facetwp_index f
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby
        LIMIT $limit";

        $output = $wpdb->get_results( $sql, ARRAY_A );

        return $output;
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {

        $facet = $params['facet'];

        $output = '';
        $values = (array) $params['values'];
        $selected_values = (array) $params['selected_values'];

        foreach ( $values as $result ) {
            $selected = in_array( $result['facet_value'], $selected_values ) ? ' checked' : '';
            $selected .= ( 0 == $result['counter'] ) ? ' disabled' : '';
            $output .= '<div class="facetwp-color' . $selected . '" data-value="' . $result['facet_value'] . '" data-color="' . esc_attr( $result['facet_display_value'] ) . '"></div>';
        }

        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts( $params ) {
        global $wpdb;

        $output = array();
        $facet = $params['facet'];
        $selected_values = $params['selected_values'];

        $sql = $wpdb->prepare( "SELECT DISTINCT post_id
            FROM {$wpdb->prefix}facetwp_index
            WHERE facet_name = %s",
            $facet['name']
        );

        foreach ( $selected_values as $key => $value ) {
            $results = $wpdb->get_col( $sql . " AND facet_value IN ('$value')" );
            $output = ( $key > 0 ) ? array_intersect( $output, $results ) : $results;

            if ( empty( $output ) ) {
                break;
            }
        }

        return $output;
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/load/color', function($this, obj) {
        $this.find('.facet-source').val(obj.source);
        $this.find('.type-color .facet-count').val(obj.count);
    });

    wp.hooks.addFilter('facetwp/save/color', function($this, obj) {
        obj['source'] = $this.find('.facet-source').val();
        obj['count'] = $this.find('.type-color .facet-count').val();
        return obj;
    });


})(jQuery);
</script>
<?php
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
?>

<link href="<?php echo WP_CONTENT_URL; ?>/plugins/facetwp-color/front.css" rel="stylesheet">

<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/color', function($this, facet_name) {
        var selected_values = [];
        $this.find('.facetwp-color.checked').each(function() {
            selected_values.push($(this).attr('data-value'));
        });
        FWP.facets[facet_name] = selected_values;
    });

    wp.hooks.addAction('facetwp/ready', function() {
        $(document).on('click', '.facetwp-facet .facetwp-color:not(.disabled)', function() {
            $(this).toggleClass('checked');
            var $facet = $(this).closest('.facetwp-facet');
            FWP.autoload();
        });
    });

    $(document).on('facetwp-loaded', function() {
        $('.facetwp-color').each(function() {
            $(this).css('background-color', $(this).attr('data-color'));
        });
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output admin settings HTML
     */
    function settings_html() {
?>
        <tr class="facetwp-conditional type-color">
            <td>
                <?php _e('Count', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'The maximum number of facet choices to show', 'fwp' ); ?></div>
                </div>
            </td>
            <td><input type="text" class="facet-count" value="10" /></td>
        </tr>
<?php
    }
}
