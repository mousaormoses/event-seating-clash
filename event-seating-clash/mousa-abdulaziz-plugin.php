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

        update_post_meta( $post_id, 'esc_event_date', $event_date );
        update_post_meta( $post_id, 'esc_event_time', $event_time );
        update_post_meta( $post_id, 'esc_event_location', $event_location );
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

    if ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        return $content;
    }

    $seat_types = esc_get_seat_types();

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
                            ?>
                            <button
                                type="button"
                                class="esc-seat esc-seat--<?php echo esc_attr( $seat_type ); ?>"
                                data-seat="<?php echo esc_attr( $seat_id ); ?>"
                                data-seat-type="<?php echo esc_attr( $seat_type ); ?>"
                                aria-pressed="false"
                            >
                                <span class="esc-seat__label"><?php echo esc_html( $seat_id ); ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="esc-seat-selection__summary">
            <strong><?php esc_html_e( 'Selected Seat:', 'event-seating-clash' ); ?></strong>
            <span class="esc-seat-selection__summary-label" data-selected-seat><?php esc_html_e( 'None', 'event-seating-clash' ); ?></span>
        </div>
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

            seats.forEach( ( seat ) => {
                seat.addEventListener( 'click', () => {
                    seats.forEach( ( otherSeat ) => {
                        otherSeat.classList.remove( 'is-selected' );
                        otherSeat.setAttribute( 'aria-pressed', 'false' );
                    } );

                    seat.classList.add( 'is-selected' );
                    seat.setAttribute( 'aria-pressed', 'true' );

                    const seatId = seat.getAttribute( 'data-seat' );
                    const seatType = seat.getAttribute( 'data-seat-type' );
                    const seatTypeLabel = seatTypes[ seatType ] || seatType;

                    summaryLabel.textContent = seatId + ' – ' + seatTypeLabel;
                } );
            } );
        }( document ) );
    </script>
    <?php

    $content .= ob_get_clean();

    return $content;
}
add_filter( 'the_content', 'esc_append_event_content' );

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
