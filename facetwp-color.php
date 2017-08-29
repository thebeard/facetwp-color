<?php
/*
Plugin Name: FacetWP - Color
Plugin URI: https://facetwp.com/
Description: A FacetWP facet to filter products by color
Version: 1.3.1
Author: FacetWP, LLC
GitHub URI: facetwp/facetwp-color
*/

defined( 'ABSPATH' ) or exit;

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
	private $yith_color_label_supported = false;

    function __construct() {
		$this->label = __( 'Color', 'fwp' );
		$this->init_yith_color_label_support();
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

        // Properly handle "OR" facets
        if ( 'or' == $facet['operator'] ) {

            // Apply filtering (ignore the facet's current selections)
            if ( isset( FWP()->or_values ) && ( 1 < count( FWP()->or_values ) || ! isset( FWP()->or_values[ $facet['name'] ] ) ) ) {
                $post_ids = array();
                $or_values = FWP()->or_values; // Preserve the original
                unset( $or_values[ $facet['name'] ] );

                $counter = 0;
                foreach ( $or_values as $name => $vals ) {
                    $post_ids = ( 0 == $counter ) ? $vals : array_intersect( $post_ids, $vals );
                    $counter++;
                }

                // Return only applicable results
                $post_ids = array_intersect( $post_ids, FWP()->unfiltered_post_ids );
            }
            else {
                $post_ids = FWP()->unfiltered_post_ids;
            }

            $post_ids = empty( $post_ids ) ? array( 0 ) : $post_ids;
            $where_clause = ' AND post_id IN (' . implode( ',', $post_ids ) . ')';
        }

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

        return apply_filters( 'facetwp_color_values', $output, $this->get_data_source( $params ) );
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
			
			$attributes = array(
				'class' => 'facetwp-color' . $selected,
				'data_value' => $result['facet_value'],
				'data-color' => esc_attr( $result['facet_display_value'] )
			);

			$output .= '<div';
			foreach( apply_filters( 'facetwp_selector_html_attributes', $attributes, $result['term_id'], $this->get_data_source( $params ) ) as $key => $value ) {
				if ( $value ) $output .= sprintf( ' %s="%s"', $key, $value );
			}
			$output .= "></div>";			
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

        // Match ALL values
        if ( 'and' == $facet['operator'] ) {
            foreach ( $selected_values as $key => $value ) {
                $results = facetwp_sql( $sql . " AND facet_value IN ('$value')", $facet );
                $output = ( $key > 0 ) ? array_intersect( $output, $results ) : $results;

                if ( empty( $output ) ) {
                    break;
                }
            }
        }
        // Match ANY value
        else {
            $selected_values = implode( "','", $selected_values );
            $output = facetwp_sql( $sql . " AND facet_value IN ('$selected_values')", $facet );
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
        $this.find('.facet-count').val(obj.count);
        $this.find('.facet-operator').val(obj.operator);
    });

    wp.hooks.addFilter('facetwp/save/color', function(obj, $this) {
        obj['source'] = $this.find('.facet-source').val();
        obj['count'] = $this.find('.facet-count').val();
        obj['operator'] = $this.find('.facet-operator').val();
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

<style type="text/css">
.facetwp-color {
    display: inline-block;
    margin: 0 12px 12px 0;
    box-shadow: 1px 2px 3px #ccc;
    width: 30px;
    height: 30px;
    cursor: pointer;
}

.facetwp-color.checked::after {
    content: '';
    position: absolute;
    border: 2px solid #fff;
    border-top: 0;
    border-right: 0;
    width: 16px;
    height: 6px;
    transform: rotate(-45deg);
    -webkit-transform: rotate(-45deg);
    margin: 8px 0 0 6px;
}
</style>

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
        $(document).on('click touchstart', '.facetwp-facet .facetwp-color:not(.disabled)', function(e) {
            if (true === e.handled) {
                return false;
            }
            e.handled = true;
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
        <tr>
            <td>
                <?php _e('Behavior', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'How should multiple selections affect the results?', 'fwp' ); ?></div>
                </div>
            </td>
            <td>
                <select class="facet-operator">
                    <option value="and"><?php _e( 'Narrow the result set', 'fwp' ); ?></option>
                    <option value="or"><?php _e( 'Widen the result set', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
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
	
	/**
     * Allow global scope to set initiated support
     */
	private function init_yith_color_label_support() {
		do_action( 'init_facetwp_color_yith_color_label_support', $this );
	}

	/**
     * Set support value
     */
	public function set_yith_color_label_support( $set = true ) {
		$this->yith_color_label_supported = $set;
	}

	/**
     * Get support value
     */
	public function yith_color_label_support() {
		return $this->yith_color_label_supported;
	}

	/**
     * Get data source
     */
	private function get_data_source( $params ) {
		if ( $this->yith_color_label_support() && !empty( $params ) && isset( $params[ 'facet' ] ) && isset( $params[ 'facet' ][ 'source' ] ) ) return substr( $params[ 'facet' ][ 'source' ], 4 );
		else return null;
	}

}

// Set class to include YITH support internally
add_action( 'init_facetwp_color_yith_color_label_support', 'czythfct_set_facetwpcolor_yith_support' );
function czythfct_set_facetwpcolor_yith_support( $facetwp_color ) {
	if ( defined( 'YITH_WCCL' ) ) $facetwp_color->set_yith_color_label_support();
}

 // Include support for YITH WooCommerce Color and Label Variations
add_filter( 'facetwp_color_values', 'yith_color_label_facetwp_color_support', 99, 2 );
function yith_color_label_facetwp_color_support( $output, $data_source ) {
    if ( defined( 'YITH_WCCL' ) ) {
        if (!empty( $output ) ) {
            $counter = 0;
            foreach( $output as $color_selection ) {
				error_log( $color_selection[ 'term_id'] );
                $value = get_term_meta( $color_selection[ 'term_id' ], $data_source . '_yith_wccl_value', true );
                if ( $value ) $output[ $counter ][ 'facet_display_value' ] = $value;
                $counter++;
            }
        }
        return $output;
    }
}

add_filter( 'facetwp_selector_html_attributes', 'czythfct_color_html_attibutes', 20, 3 );
function czythfct_color_html_attibutes( $attributes, $term_id, $data_source ) {
	$attributes[ 'title' ] = get_term_meta( $term_id, $data_source . '_yith_wccl_tooltip', true );
	$attributes[ 'data-toggle' ] = "tooltip";
	$attributes[ 'data-placement' ] = "top";
	return $attributes;
}