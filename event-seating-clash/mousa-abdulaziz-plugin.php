<?php
/**
 * Plugin Name:       Event Seating Clash
 * Plugin URI:        https://example.com/plugins/event-seating-clash
 * Description:       Manage events with seat maps and allow visitors to pick their seats.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Mousa Abdulaziz
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       event-seating-clash
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the custom post type used for events.
 */
function esc_register_event_post_type() {
    $labels = [
        'name'                  => __( 'Events', 'event-seating-clash' ),
        'singular_name'         => __( 'Event', 'event-seating-clash' ),
        'menu_name'             => __( 'Events', 'event-seating-clash' ),
        'name_admin_bar'        => __( 'Event', 'event-seating-clash' ),
        'add_new'               => __( 'Add New', 'event-seating-clash' ),
        'add_new_item'          => __( 'Add New Event', 'event-seating-clash' ),
        'new_item'              => __( 'New Event', 'event-seating-clash' ),
        'edit_item'             => __( 'Edit Event', 'event-seating-clash' ),
        'view_item'             => __( 'View Event', 'event-seating-clash' ),
        'all_items'             => __( 'All Events', 'event-seating-clash' ),
        'search_items'          => __( 'Search Events', 'event-seating-clash' ),
        'parent_item_colon'     => __( 'Parent Events:', 'event-seating-clash' ),
        'not_found'             => __( 'No events found.', 'event-seating-clash' ),
        'not_found_in_trash'    => __( 'No events found in Trash.', 'event-seating-clash' ),
        'featured_image'        => __( 'Event Image', 'event-seating-clash' ),
        'set_featured_image'    => __( 'Set event image', 'event-seating-clash' ),
        'remove_featured_image' => __( 'Remove event image', 'event-seating-clash' ),
        'use_featured_image'    => __( 'Use as event image', 'event-seating-clash' ),
        'archives'              => __( 'Event archives', 'event-seating-clash' ),
        'insert_into_item'      => __( 'Insert into event', 'event-seating-clash' ),
        'uploaded_to_this_item' => __( 'Uploaded to this event', 'event-seating-clash' ),
        'filter_items_list'     => __( 'Filter events list', 'event-seating-clash' ),
        'items_list_navigation' => __( 'Events list navigation', 'event-seating-clash' ),
        'items_list'            => __( 'Events list', 'event-seating-clash' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-tickets-alt',
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
        'rewrite'            => [ 'slug' => 'events' ],
        'show_in_rest'       => true,
    ];

    register_post_type( 'esc_event', $args );
}
add_action( 'init', 'esc_register_event_post_type' );

/**
 * Provide the seat types available for events.
 *
 * @return array<string, string>
 */
function esc_get_seat_types() {
    return [
        'regular' => __( 'Regular', 'event-seating-clash' ),
        'premium' => __( 'Premium', 'event-seating-clash' ),
        'vip'     => __( 'VIP', 'event-seating-clash' ),
    ];
}

/**
 * Build a default seat map for the provided dimensions.
 *
 * @param int $rows Number of rows.
 * @param int $cols Seats per row.
 *
 * @return array<int, array<int, string>>
 */
function esc_build_default_seat_map( $rows, $cols ) {
    $seat_types = array_keys( esc_get_seat_types() );
    $default    = reset( $seat_types );
    $map        = [];

    for ( $row = 0; $row < $rows; $row++ ) {
        $map[ $row ] = [];
        for ( $col = 0; $col < $cols; $col++ ) {
            $map[ $row ][ $col ] = $default;
        }
    }

    return $map;
}

/**
 * Generate a human-friendly seat row label (A, B, ..., AA, AB, ...).
 *
 * @param int $index Zero-based row index.
 *
 * @return string
 */
function esc_get_row_label( $index ) {
    $index = (int) $index;

    if ( $index < 0 ) {
        return '';
    }

    $label = '';

    do {
        $remainder = $index % 26;
        $label     = chr( 65 + $remainder ) . $label;
        $index     = intdiv( $index, 26 ) - 1;
    } while ( $index >= 0 );

    return $label;
}

/**
 * Convert a seat row label back to its zero-based index.
 *
 * @param string $label Row label (e.g. A, B, AA).
 *
 * @return int Zero-based index or -1 if invalid.
 */
function esc_get_row_index_from_label( $label ) {
    $label = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $label ) );

    if ( '' === $label ) {
        return -1;
    }

    $value = 0;
    $length = strlen( $label );

    for ( $i = 0; $i < $length; $i++ ) {
        $code = ord( $label[ $i ] );

        if ( $code < 65 || $code > 90 ) {
            return -1;
        }

        $value = ( $value * 26 ) + ( $code - 64 );
    }

    return $value - 1;
}

/**
 * Format a seat identifier from its zero-based indices.
 *
 * @param int $row Row index.
 * @param int $column Column index.
 *
 * @return string
 */
function esc_format_seat_identifier( $row, $column ) {
    return esc_get_row_label( (int) $row ) . ( (int) $column + 1 );
}

/**
 * Convert legacy seat identifiers (R1S1) into the new format (A1).
 *
 * @param string $seat_id Raw seat identifier.
 *
 * @return string
 */
function esc_convert_legacy_seat_id( $seat_id ) {
    $seat_id = sanitize_text_field( (string) $seat_id );

    if ( preg_match( '/^R(\d+)S(\d+)$/i', $seat_id, $matches ) ) {
        $row = (int) $matches[1] - 1;
        $col = (int) $matches[2] - 1;

        if ( $row >= 0 && $col >= 0 ) {
            return esc_format_seat_identifier( $row, $col );
        }
    }

    return strtoupper( $seat_id );
}

/**
 * Parse a seat identifier into zero-based row/column positions.
 *
 * @param string $seat_id Seat identifier (e.g. A1, AB12).
 *
 * @return array<string, int>|null
 */
function esc_parse_seat_identifier( $seat_id ) {
    $seat_id = strtoupper( trim( (string) $seat_id ) );

    if ( ! preg_match( '/^([A-Z]+)(\d+)$/', $seat_id, $matches ) ) {
        return null;
    }

    $row_index = esc_get_row_index_from_label( $matches[1] );
    $column    = (int) $matches[2] - 1;

    if ( $row_index < 0 || $column < 0 ) {
        return null;
    }

    return [
        'seat_id' => $seat_id,
        'row'     => $row_index,
        'column'  => $column,
    ];
}

/**
 * Retrieve the product assignments for each seat type on an event.
 *
 * @param int $event_id Event ID.
 *
 * @return array<string, int>
 */
function esc_get_event_seat_products( $event_id ) {
    $seat_types = esc_get_seat_types();
    $products   = [];
    $stored     = get_post_meta( $event_id, 'esc_event_seat_products', true );

    if ( is_array( $stored ) ) {
        foreach ( $seat_types as $type => $label ) {
            if ( empty( $stored[ $type ] ) ) {
                continue;
            }

            $products[ $type ] = absint( $stored[ $type ] );
        }
    }

    if ( empty( $products ) ) {
        $legacy = (int) get_post_meta( $event_id, 'esc_event_product_id', true );

        if ( $legacy > 0 ) {
            foreach ( array_keys( $seat_types ) as $type ) {
                $products[ $type ] = $legacy;
            }
        }
    }

    return $products;
}

/**
 * Collect the seat types that are actually used within a seat map.
 *
 * @param array $seat_map Stored seat map.
 *
 * @return array<string, bool>
 */
function esc_get_seat_types_in_map( $seat_map ) {
    $types = [];

    if ( ! is_array( $seat_map ) ) {
        return $types;
    }

    foreach ( $seat_map as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        foreach ( $row as $type ) {
            $key = sanitize_key( $type );

            if ( '' === $key ) {
                continue;
            }

            $types[ $key ] = true;
        }
    }

    return $types;
}

/**
 * Validate and normalize a selection of seats for an event.
 *
 * @param int   $event_id Event ID.
 * @param array $selected Raw selection data.
 *
 * @return array<int, array{0:string,1:string}>|WP_Error
 */
function esc_prepare_seat_selection( $event_id, $selected ) {
    if ( ! $event_id ) {
        return new WP_Error( 'esc_invalid_event', __( 'The selected event could not be found.', 'event-seating-clash' ) );
    }

    $rows     = (int) get_post_meta( $event_id, 'esc_seat_rows', true );
    $cols     = (int) get_post_meta( $event_id, 'esc_seat_cols', true );
    $seat_map = get_post_meta( $event_id, 'esc_seat_map', true );

    if ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        return new WP_Error( 'esc_no_seats', __( 'Seats are not available for this event.', 'event-seating-clash' ) );
    }

    if ( ! is_array( $selected ) || empty( $selected ) ) {
        return new WP_Error( 'esc_empty_selection', __( 'Please choose at least one seat before booking.', 'event-seating-clash' ) );
    }

    $seat_types = esc_get_seat_types();
    $booked     = esc_get_booked_seats( $event_id );
    $booked_map = array_fill_keys( $booked, true );
    $normalized = [];
    $seen       = [];

    foreach ( $selected as $entry ) {
        if ( ! is_array( $entry ) || count( $entry ) < 2 ) {
            return new WP_Error( 'esc_invalid_selection', __( 'Invalid seat selection provided.', 'event-seating-clash' ) );
        }

        $seat_id   = esc_convert_legacy_seat_id( $entry[0] );
        $seat_type = sanitize_key( $entry[1] );

        if ( ! isset( $seat_types[ $seat_type ] ) ) {
            return new WP_Error( 'esc_invalid_type', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
        }

        $parsed = esc_parse_seat_identifier( $seat_id );

        if ( ! $parsed ) {
            return new WP_Error( 'esc_invalid_id', __( 'Invalid seat selection provided.', 'event-seating-clash' ) );
        }

        if ( ! isset( $seat_map[ $parsed['row'] ][ $parsed['column'] ] ) ) {
            return new WP_Error( 'esc_out_of_bounds', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
        }

        $stored_type = sanitize_key( $seat_map[ $parsed['row'] ][ $parsed['column'] ] );

        if ( $stored_type !== $seat_type ) {
            return new WP_Error( 'esc_type_mismatch', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
        }

        if ( isset( $booked_map[ $seat_id ] ) ) {
            return new WP_Error(
                'esc_seat_booked',
                sprintf( __( 'Seat %s has already been booked. Please choose a different seat.', 'event-seating-clash' ), $seat_id )
            );
        }

        if ( isset( $seen[ $seat_id ] ) ) {
            continue;
        }

        $normalized[]   = [ $seat_id, $seat_type ];
        $seen[ $seat_id ] = true;
    }

    if ( empty( $normalized ) ) {
        return new WP_Error( 'esc_empty_selection', __( 'Please choose at least one seat before booking.', 'event-seating-clash' ) );
    }

    return $normalized;
}

/**
 * Register meta boxes for event details and seat maps.
 */
function esc_add_event_metaboxes() {
    add_meta_box(
        'esc_event_details',
        __( 'Event Details', 'event-seating-clash' ),
        'esc_render_event_details_metabox',
        'esc_event',
        'normal',
        'high'
    );

    add_meta_box(
        'esc_event_seat_map',
        __( 'Seat Map', 'event-seating-clash' ),
        'esc_render_event_seat_map_metabox',
        'esc_event',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'esc_add_event_metaboxes' );

/**
 * Render the meta box that handles event details.
 *
 * @param WP_Post $post The current post object.
 */
function esc_render_event_details_metabox( $post ) {
    wp_nonce_field( 'esc_save_event_details', 'esc_event_details_nonce' );

    $event_date     = get_post_meta( $post->ID, 'esc_event_date', true );
    $event_time     = get_post_meta( $post->ID, 'esc_event_time', true );
    $event_location = get_post_meta( $post->ID, 'esc_event_location', true );
    $seat_products  = get_post_meta( $post->ID, 'esc_event_seat_products', true );
    $seat_products  = is_array( $seat_products ) ? array_map( 'absint', $seat_products ) : [];
    $legacy_product = (int) get_post_meta( $post->ID, 'esc_event_product_id', true );
    $seat_types     = esc_get_seat_types();

    $product_options = [];

    if ( function_exists( 'wc_get_products' ) ) {
        $product_ids = wc_get_products(
            [
                'status'  => [ 'publish', 'private' ],
                'limit'   => -1,
                'orderby' => 'title',
                'order'   => 'ASC',
                'return'  => 'ids',
            ]
        );

        foreach ( $product_ids as $id ) {
            $product      = wc_get_product( $id );
            $product_name = $product ? $product->get_formatted_name() : '';

            if ( empty( $product_name ) ) {
                continue;
            }

            $product_options[ $id ] = wp_strip_all_tags( $product_name );
        }
    }

    ?>
    <p>
        <label for="esc_event_date"><strong><?php esc_html_e( 'Event Date', 'event-seating-clash' ); ?></strong></label><br />
        <input type="date" id="esc_event_date" name="esc_event_date" value="<?php echo esc_attr( $event_date ); ?>" class="widefat" />
    </p>
    <p>
        <label for="esc_event_time"><strong><?php esc_html_e( 'Event Time', 'event-seating-clash' ); ?></strong></label><br />
        <input type="time" id="esc_event_time" name="esc_event_time" value="<?php echo esc_attr( $event_time ); ?>" class="widefat" />
    </p>
    <p>
        <label for="esc_event_location"><strong><?php esc_html_e( 'Location', 'event-seating-clash' ); ?></strong></label><br />
        <input type="text" id="esc_event_location" name="esc_event_location" value="<?php echo esc_attr( $event_location ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Venue or address', 'event-seating-clash' ); ?>" />
    </p>
    <div class="esc-event-seat-products">
        <strong><?php esc_html_e( 'Seat Type Products', 'event-seating-clash' ); ?></strong>
        <p class="description"><?php esc_html_e( 'Assign a WooCommerce product to each seat type so pricing reflects the chosen seats.', 'event-seating-clash' ); ?></p>
        <?php foreach ( $seat_types as $type => $label ) :
            $selected_product = isset( $seat_products[ $type ] ) ? $seat_products[ $type ] : $legacy_product;
            ?>
            <p>
                <label for="esc_event_seat_products_<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></label><br />
                <select id="esc_event_seat_products_<?php echo esc_attr( $type ); ?>" name="esc_event_seat_products[<?php echo esc_attr( $type ); ?>]" class="widefat">
                    <option value="0"><?php esc_html_e( '— Select product —', 'event-seating-clash' ); ?></option>
                    <?php foreach ( $product_options as $id => $product_name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected_product, $id ); ?>><?php echo esc_html( $product_name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render the meta box that displays and edits the seat map.
 *
 * @param WP_Post $post The current post object.
 */
function esc_render_event_seat_map_metabox( $post ) {
    wp_nonce_field( 'esc_save_seat_map', 'esc_event_seat_map_nonce' );

    $stored_rows = (int) get_post_meta( $post->ID, 'esc_seat_rows', true );
    $stored_cols = (int) get_post_meta( $post->ID, 'esc_seat_cols', true );

    $rows = $stored_rows > 0 ? $stored_rows : 5;
    $cols = $stored_cols > 0 ? $stored_cols : 8;

    $seat_map = get_post_meta( $post->ID, 'esc_seat_map', true );
    if ( ! is_array( $seat_map ) || empty( $seat_map ) ) {
        $seat_map = esc_build_default_seat_map( $rows, $cols );
    }

    $seat_types = esc_get_seat_types();
    ?>
    <p class="description">
        <?php esc_html_e( 'Adjust the number of rows and seats to generate a seating chart. You can assign a seat type to every chair.', 'event-seating-clash' ); ?>
    </p>
    <div class="esc-seat-map-controls">
        <label>
            <?php esc_html_e( 'Rows', 'event-seating-clash' ); ?>
            <input type="number" min="1" id="esc-seat-rows" name="esc_seat_rows" value="<?php echo esc_attr( $rows ); ?>" />
        </label>
        <label>
            <?php esc_html_e( 'Seats per row', 'event-seating-clash' ); ?>
            <input type="number" min="1" id="esc-seat-cols" name="esc_seat_cols" value="<?php echo esc_attr( $cols ); ?>" />
        </label>
        <div class="esc-seat-map-bulk-controls">
            <label>
                <?php esc_html_e( 'Align selected to', 'event-seating-clash' ); ?>
                <select id="esc-seat-align-type">
                    <?php foreach ( $seat_types as $type => $label ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="button" id="esc-seat-align-button"><?php esc_html_e( 'Align', 'event-seating-clash' ); ?></button>
        </div>
    </div>
    <div id="esc-seat-map-grid"></div>
    <script>
        ( function( document ) {
            const seatTypes = <?php echo wp_json_encode( $seat_types ); ?>;
            const defaultType = Object.keys( seatTypes )[0];
            const rowsInput = document.getElementById( 'esc-seat-rows' );
            const colsInput = document.getElementById( 'esc-seat-cols' );
            const grid = document.getElementById( 'esc-seat-map-grid' );
            const alignButton = document.getElementById( 'esc-seat-align-button' );
            const alignType = document.getElementById( 'esc-seat-align-type' );
            const selectedSeats = new Set();
            let seatMap = <?php echo wp_json_encode( $seat_map ); ?> || [];

            const rowLabel = ( index ) => {
                let value = Math.max( parseInt( index, 10 ) || 0, 0 );
                let label = '';

                do {
                    const remainder = value % 26;
                    label = String.fromCharCode( 65 + remainder ) + label;
                    value = Math.floor( value / 26 ) - 1;
                } while ( value >= 0 );

                return label;
            };

            const ensureMapDimensions = () => {
                const rows = Math.max( parseInt( rowsInput.value, 10 ) || 0, 1 );
                const cols = Math.max( parseInt( colsInput.value, 10 ) || 0, 1 );

                seatMap = seatMap.slice( 0, rows );
                for ( let row = 0; row < rows; row++ ) {
                    if ( ! Array.isArray( seatMap[ row ] ) ) {
                        seatMap[ row ] = [];
                    }

                    seatMap[ row ] = seatMap[ row ].slice( 0, cols );

                    for ( let col = 0; col < cols; col++ ) {
                        if ( ! seatMap[ row ][ col ] || ! seatTypes[ seatMap[ row ][ col ] ] ) {
                            seatMap[ row ][ col ] = defaultType;
                        }
                    }
                }

                return { rows, cols };
            };

            const renderSeatMap = () => {
                const dimensions = ensureMapDimensions();
                const table = document.createElement( 'table' );
                table.className = 'esc-seat-map-table';

                for ( let row = 0; row < dimensions.rows; row++ ) {
                    const tr = document.createElement( 'tr' );
                    const rowHeader = document.createElement( 'th' );
                    rowHeader.textContent = 'Row ' + rowLabel( row );
                    tr.appendChild( rowHeader );

                    for ( let col = 0; col < dimensions.cols; col++ ) {
                        const td = document.createElement( 'td' );
                        const select = document.createElement( 'select' );
                        select.name = 'esc_seat_map[' + row + '][' + col + ']';
                        select.className = 'esc-seat-select';

                        for ( const seatType in seatTypes ) {
                            if ( Object.prototype.hasOwnProperty.call( seatTypes, seatType ) ) {
                                const option = document.createElement( 'option' );
                                option.value = seatType;
                                option.textContent = seatTypes[ seatType ];
                                if ( seatMap[ row ][ col ] === seatType ) {
                                    option.selected = true;
                                }
                                select.appendChild( option );
                            }
                        }

                        td.appendChild( select );
                        tr.appendChild( td );
                    }

                    table.appendChild( tr );
                }

                grid.innerHTML = '';
                grid.appendChild( table );
                selectedSeats.clear();

                table.addEventListener( 'click', ( e ) => {
                    if ( e.target.nodeName !== 'TD' ) {
                        return;
                    }

                    const td = e.target;
                    const select = td.querySelector( 'select' );
                    if ( ! select ) {
                        return;
                    }

                    if ( selectedSeats.has( select ) ) {
                        selectedSeats.delete( select );
                        td.classList.remove( 'is-selected' );
                    } else {
                        selectedSeats.add( select );
                        td.classList.add( 'is-selected' );
                    }
                } );
            };

            alignButton.addEventListener( 'click', () => {
                const type = alignType.value;
                selectedSeats.forEach( ( select ) => {
                    select.value = type;
                    select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                    select.closest( 'td' ).classList.remove( 'is-selected' );
                } );
                selectedSeats.clear();
            } );

            rowsInput.addEventListener( 'change', renderSeatMap );
            colsInput.addEventListener( 'change', renderSeatMap );
            renderSeatMap();
        }( document ) );
    </script>
    <style>
        .esc-seat-map-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: flex-end;
        }
        .esc-seat-map-bulk-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-left: auto;
        }
        .esc-seat-map-controls label {
            display: flex;
            flex-direction: column;
            font-weight: 600;
        }
        .esc-seat-map-table {
            border-collapse: collapse;
            width: 100%;
        }
        .esc-seat-map-table th,
        .esc-seat-map-table td {
            border: 1px solid #ccd0d4;
            padding: 0.5rem;
            text-align: center;
        }
        .esc-seat-map-table td {
            cursor: pointer;
        }
        .esc-seat-map-table td.is-selected {
            box-shadow: inset 0 0 0 2px #2271b1;
        }
        .esc-seat-map-table th {
            background-color: #f1f1f1;
            width: 6rem;
        }
        .esc-seat-select {
            width: 100%;
        }
    </style>
    <?php
}

/**
 * Persist event metadata when the post is saved.
 *
 * @param int $post_id The event post ID.
 */
function esc_save_event_metadata( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['esc_event_details_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['esc_event_details_nonce'] ) ), 'esc_save_event_details' ) ) {
        $event_date        = isset( $_POST['esc_event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_date'] ) ) : '';
        $event_time        = isset( $_POST['esc_event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_time'] ) ) : '';
        $event_location    = isset( $_POST['esc_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_location'] ) ) : '';
        $seat_products_raw = isset( $_POST['esc_event_seat_products'] ) ? (array) wp_unslash( $_POST['esc_event_seat_products'] ) : [];
        $seat_types        = esc_get_seat_types();
        $seat_products     = [];

        foreach ( $seat_types as $type => $label ) {
            if ( empty( $seat_products_raw[ $type ] ) ) {
                continue;
            }

            $product_id = absint( $seat_products_raw[ $type ] );

            if ( $product_id > 0 ) {
                $seat_products[ $type ] = $product_id;
            }
        }

        update_post_meta( $post_id, 'esc_event_date', $event_date );
        update_post_meta( $post_id, 'esc_event_time', $event_time );
        update_post_meta( $post_id, 'esc_event_location', $event_location );
        update_post_meta( $post_id, 'esc_event_seat_products', $seat_products );
        update_post_meta( $post_id, 'esc_event_product_id', 0 );
    }

    if ( isset( $_POST['esc_event_seat_map_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['esc_event_seat_map_nonce'] ) ), 'esc_save_seat_map' ) ) {
        $rows = isset( $_POST['esc_seat_rows'] ) ? max( 1, (int) $_POST['esc_seat_rows'] ) : 1;
        $cols = isset( $_POST['esc_seat_cols'] ) ? max( 1, (int) $_POST['esc_seat_cols'] ) : 1;

        $seat_map = [];

        if ( isset( $_POST['esc_seat_map'] ) && is_array( $_POST['esc_seat_map'] ) ) {
            $raw_map    = wp_unslash( $_POST['esc_seat_map'] );
            $seat_types = esc_get_seat_types();

            for ( $row = 0; $row < $rows; $row++ ) {
                $seat_map[ $row ] = [];
                for ( $col = 0; $col < $cols; $col++ ) {
                    $seat_type = $raw_map[ $row ][ $col ] ?? '';
                    $seat_type = sanitize_key( $seat_type );

                    if ( ! isset( $seat_types[ $seat_type ] ) ) {
                        $default_types = array_keys( $seat_types );
                        $seat_type     = reset( $default_types );
                    }

                    $seat_map[ $row ][ $col ] = $seat_type;
                }
            }
        } else {
            $seat_map = esc_build_default_seat_map( $rows, $cols );
        }

        update_post_meta( $post_id, 'esc_seat_rows', $rows );
        update_post_meta( $post_id, 'esc_seat_cols', $cols );
        update_post_meta( $post_id, 'esc_seat_map', $seat_map );
    }
}
add_action( 'save_post_esc_event', 'esc_save_event_metadata' );

/**
 * Append the seating information to the event content when displayed on the front-end.
 *
 * @param string $content Original post content.
 *
 * @return string
 */
function esc_append_event_content( $content ) {
    if ( ! is_singular( 'esc_event' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $post_id = get_the_ID();

    $event_date     = get_post_meta( $post_id, 'esc_event_date', true );
    $event_time     = get_post_meta( $post_id, 'esc_event_time', true );
    $event_location = get_post_meta( $post_id, 'esc_event_location', true );
    $rows          = (int) get_post_meta( $post_id, 'esc_seat_rows', true );
    $cols          = (int) get_post_meta( $post_id, 'esc_seat_cols', true );
    $seat_map      = get_post_meta( $post_id, 'esc_seat_map', true );
    $seat_products = esc_get_event_seat_products( $post_id );

    if ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        return $content;
    }

    $seat_products = array_filter(
        (array) $seat_products,
        static function ( $product_id ) {
            return absint( $product_id ) > 0;
        }
    );

    $seat_types     = esc_get_seat_types();
    $booked         = esc_get_booked_seats( $post_id );
    $used_types_map = esc_get_seat_types_in_map( $seat_map );
    $used_types     = array_keys( $used_types_map );

    if ( empty( $used_types ) ) {
        return $content;
    }

    $active_types   = array_intersect( $used_types, array_keys( $seat_products ) );
    $missing_types  = array_diff( $used_types, array_keys( $seat_products ) );
    $booking_locked = empty( $active_types );
    $missing_labels = array_map(
        static function ( $type ) use ( $seat_types ) {
            return $seat_types[ $type ] ?? $type;
        },
        $missing_types
    );
    $lock_message = '';

    if ( $booking_locked ) {
        $lock_message = __( 'Seat booking is currently unavailable for this event.', 'event-seating-clash' );

        if ( ! empty( $missing_labels ) && current_user_can( 'edit_post', $post_id ) ) {
            $lock_message = sprintf(
                __( 'Assign WooCommerce products to these seat types before booking: %s.', 'event-seating-clash' ),
                implode( ', ', $missing_labels )
            );
        }
    } elseif ( ! empty( $missing_labels ) ) {
        $lock_message = __( 'Some seat types are unavailable until their pricing is configured.', 'event-seating-clash' );

        if ( current_user_can( 'edit_post', $post_id ) ) {
            $lock_message .= ' ' . sprintf(
                __( 'Missing products for: %s.', 'event-seating-clash' ),
                implode( ', ', $missing_labels )
            );
        }
    }

    ob_start();
    ?>
    <section class="esc-event-summary">
        <h2><?php esc_html_e( 'Event Details', 'event-seating-clash' ); ?></h2>
        <ul class="esc-event-summary__list">
            <?php if ( ! empty( $event_date ) ) : ?>
                <li><strong><?php esc_html_e( 'Date:', 'event-seating-clash' ); ?></strong> <?php echo esc_html( $event_date ); ?></li>
            <?php endif; ?>
            <?php if ( ! empty( $event_time ) ) : ?>
                <li><strong><?php esc_html_e( 'Time:', 'event-seating-clash' ); ?></strong> <?php echo esc_html( $event_time ); ?></li>
            <?php endif; ?>
            <?php if ( ! empty( $event_location ) ) : ?>
                <li><strong><?php esc_html_e( 'Location:', 'event-seating-clash' ); ?></strong> <?php echo esc_html( $event_location ); ?></li>
            <?php endif; ?>
        </ul>
    </section>
    <section
        class="esc-seat-selection"
        id="esc-seat-selection"
        data-seat-types="<?php echo esc_attr( wp_json_encode( $seat_types ) ); ?>"
        data-booking-locked="<?php echo $booking_locked ? '1' : '0'; ?>"
        <?php if ( ! empty( $lock_message ) ) : ?>data-booking-message="<?php echo esc_attr( $lock_message ); ?>"<?php endif; ?>
    >
        <h2><?php esc_html_e( 'Pick Your Seat', 'event-seating-clash' ); ?></h2>
        <?php if ( ! empty( $lock_message ) ) : ?>
            <p class="esc-seat-selection__notice"><?php echo esc_html( $lock_message ); ?></p>
        <?php endif; ?>
        <div class="esc-seat-selection__legend">
            <strong><?php esc_html_e( 'Legend:', 'event-seating-clash' ); ?></strong>
            <ul>
                <?php foreach ( $seat_types as $type => $label ) : ?>
                    <li><span class="esc-seat esc-seat--legend esc-seat--<?php echo esc_attr( $type ); ?>"></span> <?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="esc-seat-selection__grid">
            <?php for ( $row = 0; $row < $rows; $row++ ) :
                $row_label = esc_get_row_label( $row );
                ?>
                <div class="esc-seat-row">
                    <span class="esc-seat-row__label"><?php echo esc_html( sprintf( __( 'Row %s', 'event-seating-clash' ), $row_label ) ); ?></span>
                    <div class="esc-seat-row__seats">
                        <?php for ( $col = 0; $col < $cols; $col++ ) :
                            $seat_type = isset( $seat_map[ $row ][ $col ] ) ? sanitize_key( $seat_map[ $row ][ $col ] ) : '';

                            if ( ! isset( $seat_types[ $seat_type ] ) ) {
                                $seat_type = array_key_first( $seat_types );
                            }

                            $seat_id      = esc_format_seat_identifier( $row, $col );
                            $has_product  = ! empty( $seat_products[ $seat_type ] );
                            $is_booked    = in_array( $seat_id, $booked, true );
                            $is_disabled  = $is_booked || ! $has_product || $booking_locked;
                            $button_class = 'esc-seat esc-seat--' . sanitize_html_class( $seat_type );

                            if ( $is_booked ) {
                                $button_class .= ' is-booked';
                            }

                            if ( ! $has_product || $booking_locked ) {
                                $button_class .= ' is-unavailable';
                            }
                            ?>
                            <button
                                type="button"
                                class="<?php echo esc_attr( $button_class ); ?>"
                                data-seat="<?php echo esc_attr( $seat_id ); ?>"
                                data-seat-type="<?php echo esc_attr( $seat_type ); ?>"
                                <?php echo $is_disabled ? 'disabled aria-disabled="true"' : 'aria-disabled="false"'; ?>
                                aria-pressed="false"
                            >
                                <span class="esc-seat__label"><?php echo esc_html( $seat_id ); ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <form class="esc-seat-booking" method="post">
            <?php wp_nonce_field( 'esc_book_seats', 'esc_book_seats_nonce' ); ?>
            <input type="hidden" name="esc_seat_action" value="book" />
            <input type="hidden" name="esc_event_id" value="<?php echo esc_attr( $post_id ); ?>" />
            <input type="hidden" name="esc_selected_seats" value="" data-selected-seats-input />
            <div class="esc-seat-selection__summary">
                <strong><?php esc_html_e( 'Selected Seats:', 'event-seating-clash' ); ?></strong>
                <span class="esc-seat-selection__summary-label" data-selected-seat><?php esc_html_e( 'None', 'event-seating-clash' ); ?></span>
            </div>
            <button type="submit" class="button esc-seat-selection__submit" disabled aria-disabled="true"><?php esc_html_e( 'Book Selected Seats', 'event-seating-clash' ); ?></button>
        </form>
    </section>
    <style>
        .esc-event-summary {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f7f7f7;
            border-radius: 6px;
        }
        .esc-event-summary__list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.5rem;
        }
        .esc-seat-selection {
            margin: 2rem 0;
            padding: 1.5rem;
            border: 2px solid #e2e2e2;
            border-radius: 6px;
        }
        .esc-seat-selection h2 {
            margin-top: 0;
        }
        .esc-seat-selection__notice {
            margin: 1rem 0;
            padding: 0.75rem 1rem;
            background: #fff8e1;
            border: 1px solid #ffe0a3;
            border-radius: 4px;
        }
        .esc-seat-selection__legend ul {
            list-style: none;
            padding: 0;
            display: flex;
            gap: 1rem;
        }
        .esc-seat-selection__legend li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .esc-seat-selection__grid {
            margin-top: 1.5rem;
            display: grid;
            gap: 0.75rem;
        }
        .esc-seat-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .esc-seat-row__label {
            width: 5rem;
            font-weight: 600;
        }
        .esc-seat-row__seats {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .esc-seat {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 8px;
            border: none;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .esc-seat:focus {
            outline: 3px solid #0073aa;
            outline-offset: 2px;
        }
        .esc-seat.is-selected,
        .esc-seat:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.15);
        }
        .esc-seat.is-booked {
            background: #b1b1b1;
            color: #333;
            cursor: not-allowed;
            box-shadow: none;
        }
        .esc-seat.is-unavailable {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
            border: 1px dashed #bcbcbc;
            box-shadow: none;
        }
        .esc-seat--regular {
            background: #3f8efc;
        }
        .esc-seat--premium {
            background: #a550df;
        }
        .esc-seat--vip {
            background: #f4b400;
            color: #000;
        }
        .esc-seat--legend {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-block;
        }
        .esc-seat-selection__summary {
            margin-top: 1.5rem;
            font-size: 1.1rem;
        }
        .esc-seat-selection__submit {
            margin-top: 1rem;
        }
        .esc-seat-quantity {
            font-weight: 600;
            display: inline-block;
            min-width: 2rem;
            text-align: center;
        }
    </style>
    <script>
        ( function( document ) {
            const container = document.getElementById( 'esc-seat-selection' );
            if ( ! container ) {
                return;
            }

            const seatTypes = JSON.parse( container.getAttribute( 'data-seat-types' ) || '{}' );
            const bookingLocked = container.getAttribute( 'data-booking-locked' ) === '1';
            const lockMessage = container.getAttribute( 'data-booking-message' ) || '';
            const summaryLabel = container.querySelector( '[data-selected-seat]' );
            const seats = Array.from( container.querySelectorAll( '.esc-seat' ) );
            const form = container.querySelector( '.esc-seat-booking' );
            const hiddenInput = form ? form.querySelector( '[data-selected-seats-input]' ) : null;
            const submitButton = form ? form.querySelector( '.esc-seat-selection__submit' ) : null;
            const selectedSeats = new Map();

            seats.forEach( ( seat ) => {
                seat.addEventListener( 'click', () => {
                    if ( seat.classList.contains( 'is-booked' ) || seat.classList.contains( 'is-unavailable' ) ) {
                        return;
                    }

                    const seatId = seat.getAttribute( 'data-seat' );
                    const seatType = seat.getAttribute( 'data-seat-type' );

                    if ( seat.classList.contains( 'is-selected' ) ) {
                        seat.classList.remove( 'is-selected' );
                        seat.setAttribute( 'aria-pressed', 'false' );
                        selectedSeats.delete( seatId );
                    } else {
                        seat.classList.add( 'is-selected' );
                        seat.setAttribute( 'aria-pressed', 'true' );
                        selectedSeats.set( seatId, seatType );
                    }

                    const selections = Array.from( selectedSeats.entries() ).map( ( [ id, type ] ) => {
                        const seatTypeLabel = seatTypes[ type ] || type;
                        return id + ' – ' + seatTypeLabel;
                    } );

                    summaryLabel.textContent = selections.length ? selections.join( ', ' ) : '<?php echo esc_js( __( 'None', 'event-seating-clash' ) ); ?>';

                    if ( hiddenInput ) {
                        hiddenInput.value = selections.length ? JSON.stringify( Array.from( selectedSeats.entries() ) ) : '';
                    }

                    if ( submitButton ) {
                        if ( bookingLocked ) {
                            submitButton.disabled = true;
                            submitButton.setAttribute( 'aria-disabled', 'true' );
                        } else {
                            const shouldDisable = selections.length === 0;
                            submitButton.disabled = shouldDisable;
                            submitButton.setAttribute( 'aria-disabled', shouldDisable ? 'true' : 'false' );
                        }
                    }
                } );
            } );

            if ( submitButton && bookingLocked ) {
                submitButton.disabled = true;
                submitButton.setAttribute( 'aria-disabled', 'true' );
            }

            if ( form ) {
                form.addEventListener( 'submit', ( event ) => {
                    if ( bookingLocked ) {
                        event.preventDefault();

                        if ( lockMessage ) {
                            window.alert( lockMessage );
                        }

                        return;
                    }

                    if ( ! hiddenInput || ! hiddenInput.value ) {
                        event.preventDefault();
                        window.alert( '<?php echo esc_js( __( 'Please select at least one seat before booking.', 'event-seating-clash' ) ); ?>' );
                    }
                } );
            }
        }( document ) );
    </script>
    <?php

    $content .= ob_get_clean();

    return $content;
}
add_filter( 'the_content', 'esc_append_event_content' );

/**
 * Get booked seats for an event.
 *
 * @param int $event_id Event post ID.
 *
 * @return array<int, string>
 */
function esc_get_booked_seats( $event_id ) {
    $booked = get_post_meta( $event_id, 'esc_booked_seats', true );

    if ( ! is_array( $booked ) ) {
        return [];
    }

    return array_values(
        array_unique(
            array_map(
                static function ( $seat ) {
                    return esc_convert_legacy_seat_id( $seat );
                },
                $booked
            )
        )
    );
}

/**
 * Persist booked seats for an event.
 *
 * @param int   $event_id Event post ID.
 * @param array $seats    Seat IDs.
 */
function esc_add_booked_seats( $event_id, $seats ) {
    $existing = esc_get_booked_seats( $event_id );
    $normalized = array_filter(
        array_map(
            static function ( $seat ) {
                return esc_convert_legacy_seat_id( $seat );
            },
            (array) $seats
        )
    );

    $merged = array_unique( array_merge( $existing, $normalized ) );

    update_post_meta( $event_id, 'esc_booked_seats', $merged );
}

/**
 * Remove booked seats from an event.
 *
 * @param int   $event_id Event post ID.
 * @param array $seats    Seat IDs.
 */
function esc_remove_booked_seats( $event_id, $seats ) {
    $existing = esc_get_booked_seats( $event_id );
    $seats = array_map(
        static function ( $seat ) {
            return esc_convert_legacy_seat_id( $seat );
        },
        (array) $seats
    );

    $filtered = array_filter(
        $existing,
        static function ( $seat ) use ( $seats ) {
            return ! in_array( $seat, $seats, true );
        }
    );

    update_post_meta( $event_id, 'esc_booked_seats', array_values( $filtered ) );
}

/**
 * Validate seat selections when adding to the cart.
 *
 * @param bool  $passed Validation status.
 * @param int   $product_id Product ID being added.
 * @param int   $quantity Quantity.
 * @param mixed $variation_id Variation ID.
 * @param array $variations Variation attributes.
 * @param array $cart_item_data Additional data.
 *
 * @return bool
 */
function esc_validate_seat_selection( $passed, $product_id, $quantity, $variation_id = null, $variations = [], $cart_item_data = [] ) {
    if ( ! function_exists( 'wc_add_notice' ) ) {
        return $passed;
    }

    if ( ! empty( $cart_item_data['esc_selected'] ) && ! empty( $cart_item_data['esc_event_id'] ) ) {
        return $passed;
    }

    if ( empty( $_POST['esc_event_id'] ) || empty( $_POST['esc_selected_seats'] ) ) {
        return $passed;
    }

    $event_id = absint( $_POST['esc_event_id'] );
    $selected = json_decode( wp_unslash( $_POST['esc_selected_seats'] ), true );

    if ( ! $event_id ) {
        wc_add_notice( __( 'The seat selection is invalid.', 'event-seating-clash' ), 'error' );
        return false;
    }

    $normalized = esc_prepare_seat_selection( $event_id, $selected );

    if ( is_wp_error( $normalized ) ) {
        wc_add_notice( $normalized->get_error_message(), 'error' );
        return false;
    }

    $seat_products = array_filter(
        esc_get_event_seat_products( $event_id ),
        static function ( $id ) {
            return absint( $id ) > 0;
        }
    );
    $missing_types = [];

    foreach ( $normalized as $entry ) {
        if ( empty( $seat_products[ $entry[1] ] ) ) {
            $missing_types[ $entry[1] ] = true;
        }
    }

    if ( ! empty( $missing_types ) ) {
        $seat_types = esc_get_seat_types();
        $labels     = array_map(
            static function ( $type ) use ( $seat_types ) {
                return $seat_types[ $type ] ?? $type;
            },
            array_keys( $missing_types )
        );

        wc_add_notice( sprintf( __( 'Seat types missing products: %s.', 'event-seating-clash' ), implode( ', ', $labels ) ), 'error' );

        return false;
    }

    $seat_count = count( $normalized );

    if ( $seat_count > 0 ) {
        $_POST['quantity']    = $seat_count;
        $_REQUEST['quantity'] = $seat_count;
        $_POST['esc_selected_seats'] = wp_json_encode( $normalized );
    }

    return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'esc_validate_seat_selection', 10, 6 );

/**
 * Process seat booking submissions and add the correct products to the cart.
 */
function esc_handle_seat_booking_submission() {
    if ( is_admin() || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        return;
    }

    if ( empty( $_POST['esc_seat_action'] ) || 'book' !== sanitize_text_field( wp_unslash( $_POST['esc_seat_action'] ) ) ) {
        return;
    }

    if ( ! function_exists( 'wc_add_notice' ) || ! function_exists( 'WC' ) ) {
        return;
    }

    $event_id = isset( $_POST['esc_event_id'] ) ? absint( $_POST['esc_event_id'] ) : 0;
    $redirect = $event_id ? get_permalink( $event_id ) : ( wp_get_referer() ?: home_url() );

    if ( empty( $_POST['esc_book_seats_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['esc_book_seats_nonce'] ) ), 'esc_book_seats' ) ) {
        wc_add_notice( __( 'Your session has expired. Please try booking your seats again.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    if ( ! $event_id ) {
        wc_add_notice( __( 'The seat selection is invalid.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    $selected_raw = [];

    if ( ! empty( $_POST['esc_selected_seats'] ) ) {
        $decoded = json_decode( wp_unslash( $_POST['esc_selected_seats'] ), true );

        if ( is_array( $decoded ) ) {
            $selected_raw = $decoded;
        }
    }

    $normalized = esc_prepare_seat_selection( $event_id, $selected_raw );

    if ( is_wp_error( $normalized ) ) {
        wc_add_notice( $normalized->get_error_message(), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    $seat_products = array_filter(
        esc_get_event_seat_products( $event_id ),
        static function ( $id ) {
            return absint( $id ) > 0;
        }
    );

    $seat_types    = esc_get_seat_types();
    $grouped_seats = [];
    $missing_types = [];

    foreach ( $normalized as $entry ) {
        list( $seat_id, $seat_type ) = $entry;

        if ( empty( $seat_products[ $seat_type ] ) ) {
            $missing_types[ $seat_type ] = true;
            continue;
        }

        $product_id = (int) $seat_products[ $seat_type ];

        if ( $product_id <= 0 ) {
            $missing_types[ $seat_type ] = true;
            continue;
        }

        if ( ! isset( $grouped_seats[ $product_id ] ) ) {
            $grouped_seats[ $product_id ] = [];
        }

        $grouped_seats[ $product_id ][] = [ $seat_id, $seat_type ];
    }

    if ( ! empty( $missing_types ) ) {
        $labels = array_map(
            static function ( $type ) use ( $seat_types ) {
                return $seat_types[ $type ] ?? $type;
            },
            array_keys( $missing_types )
        );

        wc_add_notice( sprintf( __( 'Seat types missing products: %s.', 'event-seating-clash' ), implode( ', ', $labels ) ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    if ( empty( $grouped_seats ) ) {
        wc_add_notice( __( 'Seat booking is unavailable for this event.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
        wc_load_cart();
    }

    if ( null === WC()->cart ) {
        wc_add_notice( __( 'We could not access the cart. Please try again.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    $added_keys = [];
    $errors     = false;

    foreach ( $grouped_seats as $product_id => $seats ) {
        $quantity = count( $seats );

        if ( $quantity <= 0 ) {
            continue;
        }

        $cart_item_data = [
            'esc_event_id'   => $event_id,
            'esc_selected'   => array_values( $seats ),
            'esc_seat_count' => $quantity,
            'unique_key'     => md5( microtime( true ) . $event_id . $product_id . wp_json_encode( $seats ) ),
        ];

        $added_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );

        if ( ! $added_key ) {
            $errors = true;
            continue;
        }

        $added_keys[] = $added_key;
    }

    if ( $errors ) {
        foreach ( $added_keys as $key ) {
            WC()->cart->remove_cart_item( $key );
        }

        wc_add_notice( __( 'We could not add all of your seats to the cart. Please try again.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    if ( empty( $added_keys ) ) {
        wc_add_notice( __( 'No seats were added to the cart.', 'event-seating-clash' ), 'error' );
        wp_safe_redirect( $redirect );
        exit;
    }

    wc_add_notice( __( 'Your seats have been added to the cart.', 'event-seating-clash' ), 'success' );

    $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $redirect;

    wp_safe_redirect( $cart_url );
    exit;
}
add_action( 'template_redirect', 'esc_handle_seat_booking_submission' );

/**
 * Ensure a seat exists within the configured seat map.
 *
 * @param string $seat_id Seat identifier.
 * @param string $seat_type Seat type key.
 * @param array  $seat_map Stored seat map.
 *
 * @return bool
 */
function esc_validate_seat_exists( $seat_id, $seat_type, $seat_map ) {
    $parsed = esc_parse_seat_identifier( $seat_id );

    if ( ! $parsed ) {
        return false;
    }

    $row = (int) $parsed['row'];
    $col = (int) $parsed['column'];

    if ( $row < 0 || $col < 0 ) {
        return false;
    }

    if ( ! isset( $seat_map[ $row ][ $col ] ) ) {
        return false;
    }

    $stored_type = sanitize_key( $seat_map[ $row ][ $col ] );
    $seat_type   = sanitize_key( $seat_type );

    return $stored_type === $seat_type;
}

/**
 * Inject seat data into the WooCommerce cart item.
 *
 * @param array $cart_item_data Cart item data.
 * @param int   $product_id Product ID.
 *
 * @return array
 */
function esc_add_cart_item_seat_data( $cart_item_data, $product_id ) {
    if ( ! empty( $cart_item_data['esc_selected'] ) && ! empty( $cart_item_data['esc_event_id'] ) ) {
        return $cart_item_data;
    }

    if ( empty( $_POST['esc_event_id'] ) || empty( $_POST['esc_selected_seats'] ) ) {
        return $cart_item_data;
    }

    $event_id = absint( $_POST['esc_event_id'] );
    $selected = json_decode( wp_unslash( $_POST['esc_selected_seats'] ), true );

    if ( ! $event_id ) {
        return $cart_item_data;
    }

    $normalized = esc_prepare_seat_selection( $event_id, $selected );

    if ( is_wp_error( $normalized ) || empty( $normalized ) ) {
        return $cart_item_data;
    }

    $cart_item_data['esc_event_id'] = $event_id;
    $cart_item_data['esc_selected'] = array_values( $normalized );
    $cart_item_data['esc_seat_count'] = count( $cart_item_data['esc_selected'] );
    $cart_item_data['unique_key'] = md5( microtime( true ) . wp_json_encode( $cart_item_data['esc_selected'] ) );

    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'esc_add_cart_item_seat_data', 10, 2 );

/**
 * Display seat data in the cart and checkout.
 *
 * @param array $item_data Existing item data.
 * @param array $cart_item Cart item.
 *
 * @return array
 */
function esc_display_cart_item_seats( $item_data, $cart_item ) {
    if ( empty( $cart_item['esc_selected'] ) ) {
        return $item_data;
    }

    $seat_types = esc_get_seat_types();
    $labels     = [];

    foreach ( $cart_item['esc_selected'] as $entry ) {
        list( $seat_id, $seat_type ) = $entry;
        $labels[]                    = sprintf( '%1$s (%2$s)', $seat_id, $seat_types[ $seat_type ] ?? $seat_type );
    }

    $item_data[] = [
        'key'   => __( 'Seats', 'event-seating-clash' ),
        'value' => implode( ', ', $labels ),
    ];

    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'esc_display_cart_item_seats', 10, 2 );

/**
 * Lock the cart quantity to the selected seat count.
 *
 * @param string $product_quantity Quantity markup.
 * @param string $cart_item_key    Cart item key.
 * @param array  $cart_item        Cart item data.
 *
 * @return string
 */
function esc_lock_cart_quantity_for_seats( $product_quantity, $cart_item_key, $cart_item ) {
    if ( empty( $cart_item['esc_selected'] ) ) {
        return $product_quantity;
    }

    $count = count( (array) $cart_item['esc_selected'] );

    return sprintf( '<span class="esc-seat-quantity">%d</span><input type="hidden" name="cart[%s][qty]" value="%d" />', $count, esc_attr( $cart_item_key ), $count );
}
add_filter( 'woocommerce_cart_item_quantity', 'esc_lock_cart_quantity_for_seats', 10, 3 );

/**
 * Persist seat metadata on order items.
 *
 * @param WC_Order_Item_Product $item Order item instance.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values Cart item values.
 * @param WC_Order              $order Order.
 */
function esc_add_order_item_seat_meta( $item, $cart_item_key, $values, $order ) {
    if ( empty( $values['esc_selected'] ) || empty( $values['esc_event_id'] ) ) {
        return;
    }

    $item->add_meta_data( '_esc_event_id', absint( $values['esc_event_id'] ), true );
    $item->add_meta_data( '_esc_seats', wp_json_encode( $values['esc_selected'] ), true );
    $seat_types = esc_get_seat_types();
    $labels     = [];

    foreach ( $values['esc_selected'] as $entry ) {
        list( $seat_id, $seat_type ) = $entry;
        $labels[]                    = sprintf( '%1$s (%2$s)', $seat_id, $seat_types[ $seat_type ] ?? $seat_type );
    }

    if ( ! empty( $labels ) ) {
        $item->add_meta_data( __( 'Seats', 'event-seating-clash' ), implode( ', ', $labels ), true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'esc_add_order_item_seat_meta', 10, 4 );

/**
 * Mark seats as booked when an order is paid.
 *
 * @param int $order_id Order ID.
 */
function esc_mark_seats_as_booked( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        $event_id = (int) $item->get_meta( '_esc_event_id', true );
        $seats    = $item->get_meta( '_esc_seats', true );

        if ( ! $event_id || empty( $seats ) ) {
            continue;
        }

        $decoded = json_decode( $seats, true );

        if ( ! is_array( $decoded ) ) {
            continue;
        }

        $seat_ids = array_filter(
            array_map(
                static function ( $entry ) {
                    if ( ! is_array( $entry ) || empty( $entry[0] ) ) {
                        return null;
                    }

                    return esc_convert_legacy_seat_id( $entry[0] );
                },
                $decoded
            )
        );

        if ( ! empty( $seat_ids ) ) {
            esc_add_booked_seats( $event_id, $seat_ids );
        }
    }
}
add_action( 'woocommerce_order_status_processing', 'esc_mark_seats_as_booked' );
add_action( 'woocommerce_order_status_completed', 'esc_mark_seats_as_booked' );

/**
 * Release seats when an order is cancelled or refunded.
 *
 * @param int $order_id Order ID.
 */
function esc_release_seats_on_cancel( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        $event_id = (int) $item->get_meta( '_esc_event_id', true );
        $seats    = $item->get_meta( '_esc_seats', true );

        if ( ! $event_id || empty( $seats ) ) {
            continue;
        }

        $decoded = json_decode( $seats, true );

        if ( ! is_array( $decoded ) ) {
            continue;
        }

        $seat_ids = array_filter(
            array_map(
                static function ( $entry ) {
                    if ( ! is_array( $entry ) || empty( $entry[0] ) ) {
                        return null;
                    }

                    return esc_convert_legacy_seat_id( $entry[0] );
                },
                $decoded
            )
        );

        if ( ! empty( $seat_ids ) ) {
            esc_remove_booked_seats( $event_id, $seat_ids );
        }
    }
}
add_action( 'woocommerce_order_status_cancelled', 'esc_release_seats_on_cancel' );
add_action( 'woocommerce_order_status_refunded', 'esc_release_seats_on_cancel' );

/**
 * Handle plugin activation tasks.
 */
function esc_plugin_activate() {
    esc_register_event_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'esc_plugin_activate' );

/**
 * Handle plugin deactivation tasks.
 */
function esc_plugin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'esc_plugin_deactivate' );
