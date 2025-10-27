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
    $product_id     = (int) get_post_meta( $post->ID, 'esc_event_product_id', true );

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
    <p>
        <label for="esc_event_product_id"><strong><?php esc_html_e( 'WooCommerce Product', 'event-seating-clash' ); ?></strong></label><br />
        <select id="esc_event_product_id" name="esc_event_product_id" class="widefat">
            <option value="0"><?php esc_html_e( 'Select a product to sell seats', 'event-seating-clash' ); ?></option>
            <?php
            if ( function_exists( 'wc_get_products' ) ) {
                $products = wc_get_products(
                    [
                        'status'  => [ 'publish', 'private' ],
                        'limit'   => -1,
                        'orderby' => 'title',
                        'order'   => 'ASC',
                        'return'  => 'ids',
                    ]
                );

                foreach ( $products as $id ) {
                    $product      = wc_get_product( $id );
                    $product_name = $product ? $product->get_formatted_name() : '';

                    if ( empty( $product_name ) ) {
                        continue;
                    }

                    printf(
                        '<option value="%1$d" %2$s>%3$s</option>',
                        absint( $id ),
                        selected( $product_id, $id, false ),
                        esc_html( wp_strip_all_tags( $product_name ) )
                    );
                }
            }
            ?>
        </select>
        <span class="description"><?php esc_html_e( 'The selected product will be added to the cart for each booked seat.', 'event-seating-clash' ); ?></span>
    </p>
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
    </div>
    <div id="esc-seat-map-grid"></div>
    <script>
        ( function( document ) {
            const seatTypes = <?php echo wp_json_encode( $seat_types ); ?>;
            const defaultType = Object.keys( seatTypes )[0];
            const rowsInput = document.getElementById( 'esc-seat-rows' );
            const colsInput = document.getElementById( 'esc-seat-cols' );
            const grid = document.getElementById( 'esc-seat-map-grid' );
            let seatMap = <?php echo wp_json_encode( $seat_map ); ?> || [];

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
                    rowHeader.textContent = 'Row ' + ( row + 1 );
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
            };

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
        $event_date     = isset( $_POST['esc_event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_date'] ) ) : '';
        $event_time     = isset( $_POST['esc_event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_time'] ) ) : '';
        $event_location = isset( $_POST['esc_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['esc_event_location'] ) ) : '';
        $product_id     = isset( $_POST['esc_event_product_id'] ) ? absint( $_POST['esc_event_product_id'] ) : 0;

        update_post_meta( $post_id, 'esc_event_date', $event_date );
        update_post_meta( $post_id, 'esc_event_time', $event_time );
        update_post_meta( $post_id, 'esc_event_location', $event_location );
        update_post_meta( $post_id, 'esc_event_product_id', $product_id );
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
    $rows           = (int) get_post_meta( $post_id, 'esc_seat_rows', true );
    $cols           = (int) get_post_meta( $post_id, 'esc_seat_cols', true );
    $seat_map       = get_post_meta( $post_id, 'esc_seat_map', true );
    $product_id     = (int) get_post_meta( $post_id, 'esc_event_product_id', true );

    if ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) || $product_id <= 0 ) {
        return $content;
    }

    $seat_types = esc_get_seat_types();
    $booked     = esc_get_booked_seats( $post_id );

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
    <section class="esc-seat-selection" id="esc-seat-selection" data-seat-types="<?php echo esc_attr( wp_json_encode( $seat_types ) ); ?>">
        <h2><?php esc_html_e( 'Pick Your Seat', 'event-seating-clash' ); ?></h2>
        <div class="esc-seat-selection__legend">
            <strong><?php esc_html_e( 'Legend:', 'event-seating-clash' ); ?></strong>
            <ul>
                <?php foreach ( $seat_types as $type => $label ) : ?>
                    <li><span class="esc-seat esc-seat--legend esc-seat--<?php echo esc_attr( $type ); ?>"></span> <?php echo esc_html( $label ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="esc-seat-selection__grid">
            <?php for ( $row = 0; $row < $rows; $row++ ) : ?>
                <div class="esc-seat-row">
                    <span class="esc-seat-row__label"><?php echo esc_html( sprintf( __( 'Row %d', 'event-seating-clash' ), $row + 1 ) ); ?></span>
                    <div class="esc-seat-row__seats">
                        <?php for ( $col = 0; $col < $cols; $col++ ) :
                            $seat_type = isset( $seat_map[ $row ][ $col ] ) && isset( $seat_types[ $seat_map[ $row ][ $col ] ] ) ? $seat_map[ $row ][ $col ] : array_key_first( $seat_types );
                            $seat_id   = sprintf( 'R%dS%d', $row + 1, $col + 1 );
                            $is_booked = in_array( $seat_id, $booked, true );
                            ?>
                            <button
                                type="button"
                                class="esc-seat esc-seat--<?php echo esc_attr( $seat_type ); ?><?php echo $is_booked ? ' is-booked' : ''; ?>"
                                data-seat="<?php echo esc_attr( $seat_id ); ?>"
                                data-seat-type="<?php echo esc_attr( $seat_type ); ?>"
                                <?php echo $is_booked ? 'disabled aria-disabled="true"' : 'aria-disabled="false"'; ?>
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
            <input type="hidden" name="esc_event_id" value="<?php echo esc_attr( $post_id ); ?>" />
            <input type="hidden" name="esc_selected_seats" value="" data-selected-seats-input />
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>" />
            <div class="esc-seat-selection__summary">
                <strong><?php esc_html_e( 'Selected Seats:', 'event-seating-clash' ); ?></strong>
                <span class="esc-seat-selection__summary-label" data-selected-seat><?php esc_html_e( 'None', 'event-seating-clash' ); ?></span>
            </div>
            <button type="submit" class="button esc-seat-selection__submit" disabled><?php esc_html_e( 'Book Selected Seats', 'event-seating-clash' ); ?></button>
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
            const summaryLabel = container.querySelector( '[data-selected-seat]' );
            const seats = Array.from( container.querySelectorAll( '.esc-seat' ) );
            const form = container.querySelector( '.esc-seat-booking' );
            const hiddenInput = form ? form.querySelector( '[data-selected-seats-input]' ) : null;
            const submitButton = form ? form.querySelector( '.esc-seat-selection__submit' ) : null;
            const selectedSeats = new Map();

            seats.forEach( ( seat ) => {
                seat.addEventListener( 'click', () => {
                    if ( seat.classList.contains( 'is-booked' ) ) {
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
                        submitButton.disabled = selections.length === 0;
                    }
                } );
            } );

            if ( form ) {
                form.addEventListener( 'submit', ( event ) => {
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

    return array_values( array_unique( array_map( 'sanitize_text_field', $booked ) ) );
}

/**
 * Persist booked seats for an event.
 *
 * @param int   $event_id Event post ID.
 * @param array $seats    Seat IDs.
 */
function esc_add_booked_seats( $event_id, $seats ) {
    $existing = esc_get_booked_seats( $event_id );
    $merged   = array_unique( array_merge( $existing, array_map( 'sanitize_text_field', $seats ) ) );

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
    $seats    = array_map( 'sanitize_text_field', $seats );

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
function esc_validate_seat_selection( $passed, $product_id, $quantity, $variation_id, $variations, $cart_item_data ) {
    if ( ! function_exists( 'wc_add_notice' ) ) {
        return $passed;
    }

    if ( empty( $_POST['esc_event_id'] ) || empty( $_POST['esc_selected_seats'] ) ) {
        return $passed;
    }

    $event_id = absint( $_POST['esc_event_id'] );
    $selected = json_decode( wp_unslash( $_POST['esc_selected_seats'] ), true );

    if ( ! $event_id || ! is_array( $selected ) ) {
        wc_add_notice( __( 'The seat selection is invalid.', 'event-seating-clash' ), 'error' );
        return false;
    }

    $rows     = (int) get_post_meta( $event_id, 'esc_seat_rows', true );
    $cols     = (int) get_post_meta( $event_id, 'esc_seat_cols', true );
    $seat_map = get_post_meta( $event_id, 'esc_seat_map', true );

    if ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        wc_add_notice( __( 'Seats are not available for this event.', 'event-seating-clash' ), 'error' );
        return false;
    }

    $booked = esc_get_booked_seats( $event_id );

    foreach ( $selected as $entry ) {
        if ( ! is_array( $entry ) || count( $entry ) < 2 ) {
            wc_add_notice( __( 'Invalid seat selection provided.', 'event-seating-clash' ), 'error' );
            return false;
        }

        list( $seat_id, $seat_type ) = $entry;
        $seat_id   = sanitize_text_field( $seat_id );
        $seat_type = sanitize_key( $seat_type );

        if ( in_array( $seat_id, $booked, true ) ) {
            wc_add_notice( sprintf( __( 'Seat %s has already been booked. Please choose a different seat.', 'event-seating-clash' ), $seat_id ), 'error' );
            return false;
        }

        if ( ! esc_validate_seat_exists( $seat_id, $seat_type, $seat_map ) ) {
            wc_add_notice( __( 'One or more selected seats do not exist.', 'event-seating-clash' ), 'error' );
            return false;
        }
    }

    $seat_count = count( $selected );

    if ( $seat_count > 0 ) {
        $_POST['quantity']    = $seat_count;
        $_REQUEST['quantity'] = $seat_count;
    }

    return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'esc_validate_seat_selection', 10, 6 );

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
    if ( ! preg_match( '/^R(\d+)S(\d+)$/', $seat_id, $matches ) ) {
        return false;
    }

    $row = (int) $matches[1] - 1;
    $col = (int) $matches[2] - 1;

    if ( $row < 0 || $col < 0 ) {
        return false;
    }

    if ( ! isset( $seat_map[ $row ][ $col ] ) ) {
        return false;
    }

    $stored_type = sanitize_key( $seat_map[ $row ][ $col ] );

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
    if ( empty( $_POST['esc_event_id'] ) || empty( $_POST['esc_selected_seats'] ) ) {
        return $cart_item_data;
    }

    $event_id = absint( $_POST['esc_event_id'] );
    $selected = json_decode( wp_unslash( $_POST['esc_selected_seats'] ), true );

    if ( ! $event_id || ! is_array( $selected ) || empty( $selected ) ) {
        return $cart_item_data;
    }

    $cart_item_data['esc_event_id'] = $event_id;
    $cart_item_data['esc_selected'] = array_values(
        array_filter(
            array_map(
                static function ( $entry ) {
                    if ( ! is_array( $entry ) || count( $entry ) < 2 ) {
                        return null;
                    }

                    return [ sanitize_text_field( $entry[0] ), sanitize_key( $entry[1] ) ];
                },
                $selected
            )
        )
    );

    if ( empty( $cart_item_data['esc_selected'] ) ) {
        return $cart_item_data;
    }

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

                    return sanitize_text_field( $entry[0] );
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

                    return sanitize_text_field( $entry[0] );
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
