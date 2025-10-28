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
function esc_get_default_seat_types() {
    return [
        'regular' => __( 'Regular', 'event-seating-clash' ),
        'premium' => __( 'Premium', 'event-seating-clash' ),
        'vip'     => __( 'VIP', 'event-seating-clash' ),
    ];
}

/**
 * Normalize a collection of seat types ensuring they are keyed by slug.
 *
 * @param array $seat_types Raw seat type data.
 *
 * @return array<string, string>
 */
function esc_normalize_seat_types( $seat_types ) {
    $normalized = [];

    foreach ( (array) $seat_types as $key => $value ) {
        if ( is_array( $value ) ) {
            $label = isset( $value['label'] ) ? sanitize_text_field( $value['label'] ) : '';
            $slug  = isset( $value['id'] ) ? sanitize_title( $value['id'] ) : sanitize_title( $key );
        } else {
            $label = sanitize_text_field( $value );
            $slug  = sanitize_title( is_numeric( $key ) ? $label : $key );
        }

        if ( '' === $slug || '' === $label ) {
            continue;
        }

        $normalized[ $slug ] = $label;
    }

    if ( empty( $normalized ) ) {
        $normalized = esc_get_default_seat_types();
    }

    return $normalized;
}

/**
 * Retrieve the available seat types for an event or fall back to defaults.
 *
 * @param int   $event_id Event identifier.
 * @param array $seat_map Optional seat map context.
 *
 * @return array<string, string>
 */
function esc_get_seat_types( $event_id = 0, $seat_map = null ) {
    if ( is_array( $seat_map ) && isset( $seat_map['seat_types'] ) ) {
        return esc_normalize_seat_types( $seat_map['seat_types'] );
    }

    if ( $event_id > 0 ) {
        $stored = get_post_meta( $event_id, 'esc_seat_types', true );

        if ( ! empty( $stored ) ) {
            return esc_normalize_seat_types( $stored );
        }
    }

    return esc_get_default_seat_types();
}

/**
 * Provide the default seat designer settings.
 *
 * @return array<string, int>
 */
function esc_get_default_seat_settings() {
    return [
        'seat_size' => 60,
    ];
}

/**
 * Persist seat type definitions for an event.
 *
 * @param int   $event_id   Event identifier.
 * @param array $seat_types Normalized seat types.
 */
function esc_update_event_seat_types( $event_id, $seat_types ) {
    if ( $event_id <= 0 ) {
        return;
    }

    $normalized = esc_normalize_seat_types( $seat_types );

    update_post_meta( $event_id, 'esc_seat_types', $normalized );
}

/**
 * Normalize seat designer settings.
 *
 * @param array $settings Raw settings.
 *
 * @return array<string, int>
 */
function esc_normalize_seat_settings( $settings ) {
    $defaults   = esc_get_default_seat_settings();
    $normalized = $defaults;

    if ( isset( $settings['seat_size'] ) ) {
        $normalized['seat_size'] = max( 24, min( 160, (int) $settings['seat_size'] ) );
    }

    return $normalized;
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
 * Determine whether the stored seat map uses the custom layout builder.
 *
 * @param mixed $seat_map Stored seat map value.
 *
 * @return bool
 */
function esc_is_custom_seat_map( $seat_map ) {
    return is_array( $seat_map ) && isset( $seat_map['layout'] ) && 'custom' === $seat_map['layout'];
}

/**
 * Convert a legacy grid seat map into the custom seat map structure.
 *
 * @param array $seat_map Legacy two-dimensional seat map.
 *
 * @return array
 */
function esc_convert_grid_to_custom_map( $seat_map ) {
    if ( empty( $seat_map ) || ! is_array( $seat_map ) ) {
        return [
            'layout'   => 'custom',
            'version'  => 1,
            'sections' => [],
        ];
    }

    $sections = [
        [
            'id'   => 'section-1',
            'name' => __( 'Main Floor', 'event-seating-clash' ),
            'rows' => [],
        ],
    ];

    foreach ( $seat_map as $row_index => $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $row_label = esc_get_row_label( $row_index );
        $row_data  = [
            'id'        => 'section-1-row-' . ( $row_index + 1 ),
            'label'     => $row_label,
            'offset'    => 0,
            'seats'     => [],
        ];

        foreach ( $row as $col_index => $seat_type ) {
            $seat_type = sanitize_key( $seat_type );
            $seat_code = sprintf( 'section-1-row-%d-seat-%d', $row_index + 1, $col_index + 1 );

            $row_data['seats'][] = [
                'code'       => $seat_code,
                'type'       => $seat_type,
                'seat_label' => (string) ( $col_index + 1 ),
            ];
        }

        $sections[0]['rows'][] = $row_data;
    }

    return [
        'layout'     => 'custom',
        'version'    => 2,
        'sections'   => $sections,
        'seat_types' => esc_get_default_seat_types(),
        'groups'     => [],
        'settings'   => esc_get_default_seat_settings(),
    ];
}

/**
 * Normalize a custom seat map ensuring the expected array structure.
 *
 * @param mixed $seat_map Stored seat map value.
 *
 * @return array
 */
function esc_normalize_custom_seat_map( $seat_map, $event_id = 0 ) {
    if ( ! esc_is_custom_seat_map( $seat_map ) ) {
        return [
            'layout'     => 'custom',
            'version'    => 2,
            'sections'   => [],
            'seat_types' => esc_get_default_seat_types(),
            'groups'     => [],
            'settings'   => esc_get_default_seat_settings(),
        ];
    }

    $seat_types = esc_get_seat_types( $event_id, $seat_map );
    $sections   = [];
    $groups     = [];
    $group_map  = [];

    foreach ( (array) ( $seat_map['groups'] ?? [] ) as $group_index => $group ) {
        $group_id = isset( $group['id'] ) ? sanitize_title( $group['id'] ) : '';

        if ( '' === $group_id ) {
            $group_id = 'group-' . ( $group_index + 1 );
        }

        $name   = isset( $group['name'] ) ? sanitize_text_field( $group['name'] ) : '';
        $name   = '' === $name ? sprintf( __( 'Group %d', 'event-seating-clash' ), $group_index + 1 ) : $name;
        $prefix = isset( $group['prefix'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $group['prefix'] ) : '';

        if ( '' === $prefix ) {
            $fallback = preg_replace( '/[^A-Za-z0-9]/', '', $name );
            $prefix   = '' === $fallback ? strtoupper( str_replace( '-', '', $group_id ) ) : $fallback;
        }

        $group_data = [
            'id'     => sanitize_title( $group_id ),
            'name'   => $name,
            'prefix' => strtoupper( $prefix ),
        ];

        $groups[]               = $group_data;
        $group_map[ $group_data['id'] ] = $group_data;
    }

    $settings = esc_normalize_seat_settings( isset( $seat_map['settings'] ) ? (array) $seat_map['settings'] : [] );

    foreach ( (array) $seat_map['sections'] as $section_index => $section ) {
        if ( empty( $section['rows'] ) || ! is_array( $section['rows'] ) ) {
            continue;
        }

        $section_id   = isset( $section['id'] ) ? sanitize_title( $section['id'] ) : 'section-' . ( $section_index + 1 );
        $section_name = isset( $section['name'] ) ? sanitize_text_field( $section['name'] ) : sprintf( __( 'Section %d', 'event-seating-clash' ), $section_index + 1 );
        $rows         = [];

        foreach ( (array) $section['rows'] as $row_index => $row ) {
            $row_label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : esc_get_row_label( $row_index );
            $offset    = isset( $row['offset'] ) ? max( 0, (int) $row['offset'] ) : 0;
            $row_seats = [];

            if ( ! empty( $row['seats'] ) && is_array( $row['seats'] ) ) {
                foreach ( $row['seats'] as $seat_index => $seat ) {
                    $code      = isset( $seat['code'] ) ? sanitize_title( $seat['code'] ) : '';
                    $seat_type = isset( $seat['type'] ) ? sanitize_key( $seat['type'] ) : '';

                    if ( '' === $seat_type || ! isset( $seat_types[ $seat_type ] ) ) {
                        continue;
                    }

                    if ( '' === $code ) {
                        $code = sanitize_title( sprintf( '%s-row-%d-seat-%d', $section_id, $row_index + 1, $seat_index + 1 ) );
                    }

                    $seat_label = isset( $seat['seat_label'] ) ? sanitize_text_field( $seat['seat_label'] ) : (string) ( $seat_index + 1 );
                    $group_id   = isset( $seat['group'] ) ? sanitize_title( $seat['group'] ) : '';

                    if ( '' !== $group_id && isset( $group_map[ $group_id ] ) ) {
                        $group_label = strtoupper( $group_map[ $group_id ]['prefix'] );
                        if ( '' !== $group_label && false === strpos( $seat_label, $group_label . '-' ) ) {
                            $seat_label = $group_label . '-' . $seat_label;
                        }
                    }

                    $row_seats[] = [
                        'code'       => $code,
                        'type'       => $seat_type,
                        'seat_label' => $seat_label,
                        'group'      => $group_id,
                    ];
                }
            }

            $rows[] = [
                'id'        => $section_id . '-row-' . ( $row_index + 1 ),
                'label'     => $row_label,
                'offset'    => $offset,
                'seats'     => $row_seats,
            ];
        }

        if ( empty( $rows ) ) {
            continue;
        }

        $sections[] = [
            'id'   => $section_id,
            'name' => $section_name,
            'rows' => $rows,
        ];
    }

    return [
        'layout'     => 'custom',
        'version'    => max( 2, isset( $seat_map['version'] ) ? (int) $seat_map['version'] : 2 ),
        'sections'   => $sections,
        'seat_types' => $seat_types,
        'groups'     => $groups,
        'settings'   => $settings,
    ];
}

/**
 * Build a lookup table of seats keyed by their unique code.
 *
 * @param array $seat_map Normalized custom seat map.
 *
 * @return array<string, array<string, mixed>>
 */
function esc_build_custom_seat_lookup( $seat_map ) {
    $lookup = [];

    if ( ! esc_is_custom_seat_map( $seat_map ) ) {
        return $lookup;
    }

    foreach ( (array) $seat_map['sections'] as $section ) {
        $section_name = isset( $section['name'] ) ? $section['name'] : '';

        foreach ( (array) $section['rows'] as $row ) {
            $row_label = isset( $row['label'] ) ? $row['label'] : '';

            foreach ( (array) $row['seats'] as $seat ) {
                if ( empty( $seat['code'] ) ) {
                    continue;
                }

                $lookup[ $seat['code'] ] = [
                    'type'        => isset( $seat['type'] ) ? $seat['type'] : '',
                    'section'     => $section_name,
                    'row_label'   => $row_label,
                    'seat_label'  => isset( $seat['seat_label'] ) ? $seat['seat_label'] : '',
                    'group'       => isset( $seat['group'] ) ? $seat['group'] : '',
                ];
            }
        }
    }

    return $lookup;
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

    if ( false !== strpos( $seat_id, '-' ) ) {
        return sanitize_title( $seat_id );
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
    $seat_types = esc_get_seat_types( $event_id );
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

    if ( esc_is_custom_seat_map( $seat_map ) ) {
        $normalized = esc_normalize_custom_seat_map( $seat_map );

        foreach ( (array) $normalized['sections'] as $section ) {
            foreach ( (array) $section['rows'] as $row ) {
                foreach ( (array) $row['seats'] as $seat ) {
                    $key = isset( $seat['type'] ) ? sanitize_key( $seat['type'] ) : '';

                    if ( '' === $key ) {
                        continue;
                    }

                    $types[ $key ] = true;
                }
            }
        }

        return $types;
    }

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

    $rows       = (int) get_post_meta( $event_id, 'esc_seat_rows', true );
    $cols       = (int) get_post_meta( $event_id, 'esc_seat_cols', true );
    $seat_map   = get_post_meta( $event_id, 'esc_seat_map', true );
    $is_custom  = esc_is_custom_seat_map( $seat_map );
    $seat_lookup = [];

    if ( $is_custom ) {
        $seat_map   = esc_normalize_custom_seat_map( $seat_map, $event_id );
        $seat_lookup = esc_build_custom_seat_lookup( $seat_map );

        if ( empty( $seat_lookup ) ) {
            return new WP_Error( 'esc_no_seats', __( 'Seats are not available for this event.', 'event-seating-clash' ) );
        }
    } elseif ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        return new WP_Error( 'esc_no_seats', __( 'Seats are not available for this event.', 'event-seating-clash' ) );
    }

    if ( ! is_array( $selected ) || empty( $selected ) ) {
        return new WP_Error( 'esc_empty_selection', __( 'Please choose at least one seat before booking.', 'event-seating-clash' ) );
    }

    $seat_types = esc_get_seat_types( $event_id, $seat_map );
    $booked     = esc_get_booked_seats( $event_id );
    $booked_map = array_fill_keys( $booked, true );
    $normalized = [];
    $seen       = [];

    foreach ( $selected as $entry ) {
        if ( ! is_array( $entry ) || count( $entry ) < 2 ) {
            return new WP_Error( 'esc_invalid_selection', __( 'Invalid seat selection provided.', 'event-seating-clash' ) );
        }

        $seat_type = sanitize_key( $entry[1] );

        if ( $is_custom ) {
            $seat_id = sanitize_title( $entry[0] );
        } else {
            $seat_id = esc_convert_legacy_seat_id( $entry[0] );
        }

        if ( ! isset( $seat_types[ $seat_type ] ) ) {
            return new WP_Error( 'esc_invalid_type', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
        }

        if ( $is_custom ) {
            if ( '' === $seat_id || ! isset( $seat_lookup[ $seat_id ] ) ) {
                return new WP_Error( 'esc_invalid_id', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
            }

            $stored_type = sanitize_key( $seat_lookup[ $seat_id ]['type'] );
        } else {
            $parsed = esc_parse_seat_identifier( $seat_id );

            if ( ! $parsed ) {
                return new WP_Error( 'esc_invalid_id', __( 'Invalid seat selection provided.', 'event-seating-clash' ) );
            }

            if ( ! isset( $seat_map[ $parsed['row'] ][ $parsed['column'] ] ) ) {
                return new WP_Error( 'esc_out_of_bounds', __( 'One or more selected seats do not exist.', 'event-seating-clash' ) );
            }

            $stored_type = sanitize_key( $seat_map[ $parsed['row'] ][ $parsed['column'] ] );
        }

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
 * Register the Seat Designer admin page under the Events menu.
 */
function esc_register_seat_designer_page() {
    add_submenu_page(
        'edit.php?post_type=esc_event',
        __( 'Seat Designer', 'event-seating-clash' ),
        __( 'Seat Designer', 'event-seating-clash' ),
        'edit_posts',
        'esc-seat-designer',
        'esc_render_seat_designer_page'
    );
}
add_action( 'admin_menu', 'esc_register_seat_designer_page' );

/**
 * Prepare context for the Seat Designer page.
 */
function esc_prepare_seat_designer_context() {
    global $esc_seat_designer_context;

    $event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
    $event    = $event_id ? get_post( $event_id ) : null;

    $esc_seat_designer_context = [
        'event_id' => $event_id,
        'event'    => $event,
        'error'    => '',
        'seat_map' => [
            'layout'     => 'custom',
            'version'    => 2,
            'sections'   => [],
            'seat_types' => esc_get_default_seat_types(),
            'groups'     => [],
            'settings'   => esc_get_default_seat_settings(),
        ],
        'seat_types' => esc_get_default_seat_types(),
    ];

    if ( ! $event || 'esc_event' !== $event->post_type ) {
        $esc_seat_designer_context['error'] = __( 'The requested event could not be found.', 'event-seating-clash' );
        return;
    }

    if ( ! current_user_can( 'edit_post', $event_id ) ) {
        $esc_seat_designer_context['error'] = __( 'You do not have permission to edit this event.', 'event-seating-clash' );
        return;
    }

    $seat_map = get_post_meta( $event_id, 'esc_seat_map', true );

    if ( esc_is_custom_seat_map( $seat_map ) ) {
        $seat_map = esc_normalize_custom_seat_map( $seat_map, $event_id );
    } else {
        $seat_map = esc_convert_grid_to_custom_map( is_array( $seat_map ) ? $seat_map : [] );
    }

    $esc_seat_designer_context['seat_map']    = $seat_map;
    $esc_seat_designer_context['seat_types']  = esc_get_seat_types( $event_id, $seat_map );
    $esc_seat_designer_context['seat_groups'] = isset( $seat_map['groups'] ) ? $seat_map['groups'] : [];
    $esc_seat_designer_context['settings']    = isset( $seat_map['settings'] ) ? $seat_map['settings'] : esc_get_default_seat_settings();
}
add_action( 'load-esc_event_page_esc-seat-designer', 'esc_prepare_seat_designer_context' );

/**
 * Enqueue assets for the Seat Designer screen.
 *
 * @param string $hook Current admin page hook.
 */
function esc_enqueue_admin_assets( $hook ) {
    global $esc_seat_designer_context;

    if ( 'esc_event_page_esc-seat-designer' !== $hook ) {
        return;
    }

    $plugin_dir = plugin_dir_path( __FILE__ );
    $plugin_url = plugin_dir_url( __FILE__ );

    $style_path  = $plugin_dir . 'assets/css/seat-designer.css';
    $script_path = $plugin_dir . 'assets/js/seat-designer.js';

    $style_version  = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';
    $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0';

    wp_enqueue_style(
        'esc-seat-designer',
        $plugin_url . 'assets/css/seat-designer.css',
        [],
        $style_version
    );

    wp_enqueue_script(
        'esc-seat-designer',
        $plugin_url . 'assets/js/seat-designer.js',
        [],
        $script_version,
        true
    );

    $context = is_array( $esc_seat_designer_context ) ? $esc_seat_designer_context : [];

    $seat_map = isset( $context['seat_map'] ) ? $context['seat_map'] : [
        'layout'     => 'custom',
        'version'    => 2,
        'sections'   => [],
        'seat_types' => esc_get_default_seat_types(),
        'groups'     => [],
        'settings'   => esc_get_default_seat_settings(),
    ];

    $data = [
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'esc_save_seat_map' ),
        'eventId'    => isset( $context['event_id'] ) ? (int) $context['event_id'] : 0,
        'seatMap'    => $seat_map,
        'seatTypes'  => isset( $context['seat_types'] ) ? $context['seat_types'] : esc_get_seat_types(),
        'seatGroups' => isset( $context['seat_groups'] ) ? $context['seat_groups'] : ( isset( $seat_map['groups'] ) ? $seat_map['groups'] : [] ),
        'settings'   => isset( $context['settings'] ) ? $context['settings'] : ( isset( $seat_map['settings'] ) ? $seat_map['settings'] : esc_get_default_seat_settings() ),
        'error'      => isset( $context['error'] ) ? $context['error'] : '',
        'strings'    => [
            'addSection'      => __( 'Add Section', 'event-seating-clash' ),
            'addRow'          => __( 'Add Row', 'event-seating-clash' ),
            'addSeat'         => __( 'Add Seat', 'event-seating-clash' ),
            'remove'          => __( 'Remove', 'event-seating-clash' ),
            'sectionName'     => __( 'Section Name', 'event-seating-clash' ),
            'rowLabel'        => __( 'Row Label', 'event-seating-clash' ),
            'rowOffset'       => __( 'Row Offset', 'event-seating-clash' ),
            'seatType'        => __( 'Seat Type', 'event-seating-clash' ),
            'saveChanges'     => __( 'Save Seating Layout', 'event-seating-clash' ),
            'saving'          => __( 'Saving…', 'event-seating-clash' ),
            'saveSuccess'     => __( 'Seat map saved successfully.', 'event-seating-clash' ),
            'saveFailure'     => __( 'Unable to save the seat map. Please try again.', 'event-seating-clash' ),
            'emptyNotice'     => __( 'Add sections, rows, and seats to build your layout.', 'event-seating-clash' ),
            'walkwayHelp'     => __( 'Use row offsets or empty rows to create aisles and walkways.', 'event-seating-clash' ),
            'ticketTypesHeading' => __( 'Ticket Types', 'event-seating-clash' ),
            'ticketTypeLabel'    => __( 'Label', 'event-seating-clash' ),
            'ticketTypeKey'      => __( 'Key', 'event-seating-clash' ),
            'addTicketType'      => __( 'Add Ticket Type', 'event-seating-clash' ),
            'groupsHeading'      => __( 'Seat Groups', 'event-seating-clash' ),
            'groupName'          => __( 'Name', 'event-seating-clash' ),
            'groupPrefix'        => __( 'Prefix', 'event-seating-clash' ),
            'addGroup'           => __( 'Add Group', 'event-seating-clash' ),
            'assignGroup'        => __( 'Assign to Group', 'event-seating-clash' ),
            'applyGroup'         => __( 'Apply', 'event-seating-clash' ),
            'removeGroup'        => __( 'Remove Group', 'event-seating-clash' ),
            'clearSelection'     => __( 'Clear Selection', 'event-seating-clash' ),
            'selectedSeats'      => __( 'Selected Seats', 'event-seating-clash' ),
            'noGroup'            => __( 'No group', 'event-seating-clash' ),
            'seatGroup'          => __( 'Seat Group', 'event-seating-clash' ),
            'seatSize'           => __( 'Seat Size', 'event-seating-clash' ),
            'seatSizeHelp'       => __( 'Adjust the seat size to better fit your layout.', 'event-seating-clash' ),
            'settingsHeading'    => __( 'Seat Settings', 'event-seating-clash' ),
            'selectSeat'         => __( 'Select', 'event-seating-clash' ),
            'newTypeLabel'       => __( 'Ticket Type', 'event-seating-clash' ),
            'newGroupLabel'      => __( 'Group', 'event-seating-clash' ),
            'defaultTypeRegular' => __( 'Regular', 'event-seating-clash' ),
        ],
    ];

    wp_localize_script( 'esc-seat-designer', 'escSeatDesigner', $data );
}
add_action( 'admin_enqueue_scripts', 'esc_enqueue_admin_assets' );

/**
 * Render the Seat Designer admin page.
 */
function esc_render_seat_designer_page() {
    global $esc_seat_designer_context;

    $context = is_array( $esc_seat_designer_context ) ? $esc_seat_designer_context : [];
    $event   = isset( $context['event'] ) ? $context['event'] : null;
    $error   = isset( $context['error'] ) ? $context['error'] : '';

    echo '<div class="wrap esc-seat-designer">';

    if ( $event ) {
        $title = sprintf(
            /* translators: %s: event title. */
            __( 'Seat Designer: %s', 'event-seating-clash' ),
            esc_html( get_the_title( $event ) )
        );
        echo '<h1>' . $title . '</h1>';
    } else {
        echo '<h1>' . esc_html__( 'Seat Designer', 'event-seating-clash' ) . '</h1>';
    }

    if ( ! empty( $error ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        echo '</div>';
        return;
    }

    $edit_link = $event ? get_edit_post_link( $event->ID ) : ''; 

    if ( $edit_link ) {
        printf(
            '<p><a class="button" href="%s">%s</a></p>',
            esc_url( $edit_link ),
            esc_html__( 'Back to Event', 'event-seating-clash' )
        );
    }

    echo '<p class="esc-seat-designer__intro">' . esc_html__( 'Create bespoke seating arrangements by combining sections, rows, offsets, and seat types. Drag-style editing is not required—everything updates instantly as you edit the form.', 'event-seating-clash' ) . '</p>';

    echo '<div id="esc-seat-designer-app" class="esc-seat-designer__app">';
    echo '<p>' . esc_html__( 'Loading seat designer…', 'event-seating-clash' ) . '</p>';
    echo '</div>';

    echo '<p class="esc-seat-designer__tips">' . esc_html__( 'Tip: combine multiple sections to create left/right seating banks or raised levels, and use row offsets to add aisles or walking space.', 'event-seating-clash' ) . '</p>';

    echo '</div>';
}

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
    $seat_types     = esc_get_seat_types( $post->ID );

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
    $seat_map      = get_post_meta( $post->ID, 'esc_seat_map', true );
    $is_custom     = esc_is_custom_seat_map( $seat_map );
    $legacy_rows   = (int) get_post_meta( $post->ID, 'esc_seat_rows', true );
    $legacy_cols   = (int) get_post_meta( $post->ID, 'esc_seat_cols', true );
    $manage_url    = add_query_arg(
        [
            'post_type' => 'esc_event',
            'page'      => 'esc-seat-designer',
            'event_id'  => $post->ID,
        ],
        admin_url( 'edit.php' )
    );

    if ( $is_custom ) {
        $seat_map = esc_normalize_custom_seat_map( $seat_map, $post->ID );
    }

    ?>
    <p class="description">
        <?php esc_html_e( 'Design complex seating layouts on the dedicated Seat Designer screen. Create multiple sections, add walkways, and freely place seats without the constraints of a simple grid.', 'event-seating-clash' ); ?>
    </p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url( $manage_url ); ?>">
            <?php esc_html_e( 'Open Seat Designer', 'event-seating-clash' ); ?>
        </a>
    </p>
    <?php if ( $is_custom && ! empty( $seat_map['sections'] ) ) : ?>
        <div class="esc-seat-map-summary">
            <h4><?php esc_html_e( 'Current Layout', 'event-seating-clash' ); ?></h4>
            <ul>
                <?php foreach ( $seat_map['sections'] as $section ) :
                    $section_name = isset( $section['name'] ) ? $section['name'] : '';
                    $row_count    = isset( $section['rows'] ) ? count( $section['rows'] ) : 0;
                    $seat_count   = 0;

                    foreach ( (array) $section['rows'] as $row ) {
                        $seat_count += count( (array) $row['seats'] );
                    }
                    ?>
                    <li>
                        <strong><?php echo esc_html( $section_name ); ?></strong>
                        —
                        <?php
                        printf(
                            esc_html__( '%1$d rows, %2$d seats', 'event-seating-clash' ),
                            $row_count,
                            $seat_count
                        );
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else : ?>
        <div class="notice notice-info inline">
            <p>
                <?php
                if ( $legacy_rows > 0 && $legacy_cols > 0 && is_array( $seat_map ) ) {
                    esc_html_e( 'This event currently uses the legacy grid layout. Launch the Seat Designer to convert it into a fully customizable seating plan.', 'event-seating-clash' );
                } else {
                    esc_html_e( 'No seats have been configured yet. Use the Seat Designer to start building your seating map.', 'event-seating-clash' );
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
    <style>
        .esc-seat-map-summary {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #ccd0d4;
            background: #fff;
            border-radius: 4px;
        }

        .esc-seat-map-summary ul {
            margin: 0.5rem 0 0;
            padding-left: 1.25rem;
        }

        .esc-seat-map-summary li {
            margin-bottom: 0.25rem;
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
        $seat_types        = esc_get_seat_types( $post_id );
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
            $seat_types = esc_get_seat_types( $post_id );

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
 * Sanitize a custom seat map payload submitted from the Seat Designer.
 *
 * @param array $payload Raw payload from the client.
 *
 * @return array|WP_Error
 */

function esc_sanitize_custom_seat_map_submission( $payload ) {
    if ( empty( $payload ) || ! is_array( $payload ) ) {
        return new WP_Error( 'esc_invalid_map', __( 'Invalid seat map data received.', 'event-seating-clash' ) );
    }

    $sections_input = isset( $payload['sections'] ) ? (array) $payload['sections'] : [];

    if ( empty( $sections_input ) ) {
        return new WP_Error( 'esc_empty_map', __( 'Add at least one section with seats before saving.', 'event-seating-clash' ) );
    }

    $seat_types = esc_normalize_seat_types( isset( $payload['seat_types'] ) ? (array) $payload['seat_types'] : [] );

    if ( empty( $seat_types ) ) {
        return new WP_Error( 'esc_empty_types', __( 'Create at least one ticket type before saving.', 'event-seating-clash' ) );
    }

    $groups       = [];
    $group_map    = [];
    $group_counts = [];

    foreach ( (array) ( $payload['groups'] ?? [] ) as $group_index => $group ) {
        $group_id = isset( $group['id'] ) ? sanitize_title( $group['id'] ) : '';

        if ( '' === $group_id ) {
            $group_id = 'group-' . ( $group_index + 1 );
        }

        $name   = isset( $group['name'] ) ? sanitize_text_field( $group['name'] ) : '';
        $name   = '' === $name ? sprintf( __( 'Group %d', 'event-seating-clash' ), $group_index + 1 ) : $name;
        $prefix = isset( $group['prefix'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $group['prefix'] ) : '';

        if ( '' === $prefix ) {
            $fallback = preg_replace( '/[^A-Za-z0-9]/', '', $name );
            $prefix   = '' === $fallback ? strtoupper( str_replace( '-', '', $group_id ) ) : $fallback;
        }

        $group_data = [
            'id'     => sanitize_title( $group_id ),
            'name'   => $name,
            'prefix' => strtoupper( $prefix ),
        ];

        $groups[]                         = $group_data;
        $group_map[ $group_data['id'] ]   = $group_data;
        $group_counts[ $group_data['id'] ] = 0;
    }

    $settings = esc_normalize_seat_settings( isset( $payload['settings'] ) ? (array) $payload['settings'] : [] );

    $sections    = [];
    $seat_codes  = [];
    $total_seats = 0;

    foreach ( $sections_input as $section_index => $section ) {
        $section_id   = isset( $section['id'] ) ? sanitize_title( $section['id'] ) : '';
        $section_name = isset( $section['name'] ) ? sanitize_text_field( $section['name'] ) : '';

        if ( '' === $section_id ) {
            $section_id = sprintf( 'section-%d', $section_index + 1 );
        }

        if ( '' === $section_name ) {
            $section_name = sprintf( __( 'Section %d', 'event-seating-clash' ), $section_index + 1 );
        }

        $rows_input = isset( $section['rows'] ) ? (array) $section['rows'] : [];
        $rows       = [];

        foreach ( $rows_input as $row_index => $row ) {
            $row_label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
            $row_label = '' === $row_label ? esc_get_row_label( $row_index ) : $row_label;
            $offset    = isset( $row['offset'] ) ? max( 0, (int) $row['offset'] ) : 0;

            $seats_input = isset( $row['seats'] ) ? (array) $row['seats'] : [];
            $seats       = [];

            foreach ( $seats_input as $seat_index => $seat ) {
                $seat_type = isset( $seat['type'] ) ? sanitize_key( $seat['type'] ) : '';

                if ( '' === $seat_type || ! isset( $seat_types[ $seat_type ] ) ) {
                    continue;
                }

                $group_id = isset( $seat['group'] ) ? sanitize_title( $seat['group'] ) : '';

                if ( '' !== $group_id && isset( $group_map[ $group_id ] ) ) {
                    $group_counts[ $group_id ]++;
                    $count       = $group_counts[ $group_id ];
                    $group_label = $group_map[ $group_id ]['prefix'];
                    $seat_label  = sprintf( '%s-%d', strtoupper( $group_label ), $count );
                    $code_base   = sanitize_title( $group_id . '-' . $count );
                } else {
                    $group_id  = '';
                    $seat_label = isset( $seat['seat_label'] ) ? sanitize_text_field( $seat['seat_label'] ) : '';
                    $seat_label = '' === $seat_label ? (string) ( $seat_index + 1 ) : $seat_label;
                    $code_base  = isset( $seat['code'] ) ? sanitize_title( $seat['code'] ) : '';

                    if ( '' === $code_base ) {
                        $code_base = sanitize_title( sprintf( '%s-row-%d-seat-%d', $section_id, $row_index + 1, $seat_index + 1 ) );
                    }
                }

                $code   = $code_base;
                $suffix = 1;

                while ( '' === $code || isset( $seat_codes[ $code ] ) ) {
                    $suffix++;
                    $code = sanitize_title( $code_base . '-' . $suffix );
                }

                $seat_codes[ $code ] = true;
                $total_seats++;

                $seats[] = [
                    'code'       => $code,
                    'type'       => $seat_type,
                    'seat_label' => $seat_label,
                    'group'      => $group_id,
                ];
            }

            if ( empty( $seats ) ) {
                continue;
            }

            $rows[] = [
                'id'        => $section_id . '-row-' . ( $row_index + 1 ),
                'label'     => $row_label,
                'offset'    => $offset,
                'seats'     => $seats,
            ];
        }

        if ( empty( $rows ) ) {
            continue;
        }

        $sections[] = [
            'id'   => $section_id,
            'name' => $section_name,
            'rows' => $rows,
        ];
    }

    if ( empty( $sections ) || $total_seats === 0 ) {
        return new WP_Error( 'esc_empty_map', __( 'Add at least one section with seats before saving.', 'event-seating-clash' ) );
    }

    return [
        'layout'     => 'custom',
        'version'    => 2,
        'sections'   => $sections,
        'seat_types' => $seat_types,
        'groups'     => $groups,
        'settings'   => $settings,
    ];
}


/**
 * Handle AJAX requests to persist a custom seat map.
 */
function esc_handle_save_seat_map_ajax() {
    check_ajax_referer( 'esc_save_seat_map', 'nonce' );

    $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

    if ( ! $event_id || 'esc_event' !== get_post_type( $event_id ) ) {
        wp_send_json_error( [ 'message' => __( 'The selected event no longer exists.', 'event-seating-clash' ) ] );
    }

    if ( ! current_user_can( 'edit_post', $event_id ) ) {
        wp_send_json_error( [ 'message' => __( 'You are not allowed to modify this event.', 'event-seating-clash' ) ] );
    }

    $raw_map = isset( $_POST['seat_map'] ) ? wp_unslash( $_POST['seat_map'] ) : '';
    $data    = json_decode( $raw_map, true );

    $sanitized = esc_sanitize_custom_seat_map_submission( $data );

    if ( is_wp_error( $sanitized ) ) {
        wp_send_json_error( [ 'message' => $sanitized->get_error_message() ] );
    }

    update_post_meta( $event_id, 'esc_seat_map', $sanitized );
    esc_update_event_seat_types( $event_id, isset( $sanitized['seat_types'] ) ? $sanitized['seat_types'] : [] );
    update_post_meta( $event_id, 'esc_seat_rows', 0 );
    update_post_meta( $event_id, 'esc_seat_cols', 0 );

    $summary = [];

    foreach ( (array) $sanitized['sections'] as $section ) {
        $rows      = isset( $section['rows'] ) ? (array) $section['rows'] : [];
        $row_count = count( $rows );
        $seat_sum  = 0;

        foreach ( $rows as $row ) {
            $seat_sum += count( (array) $row['seats'] );
        }

        $summary[] = [
            'name'  => isset( $section['name'] ) ? $section['name'] : '',
            'rows'  => $row_count,
            'seats' => $seat_sum,
        ];
    }

    wp_send_json_success(
        [
            'seatMap' => $sanitized,
            'summary' => $summary,
        ]
    );
}
add_action( 'wp_ajax_esc_save_seat_map', 'esc_handle_save_seat_map_ajax' );

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
    $is_custom_map = esc_is_custom_seat_map( $seat_map );

    if ( $is_custom_map ) {
        $seat_map = esc_normalize_custom_seat_map( $seat_map, $post_id );
    }
    $seat_products = esc_get_event_seat_products( $post_id );

    if ( $is_custom_map ) {
        if ( empty( $seat_map['sections'] ) ) {
            return $content;
        }
    } elseif ( $rows <= 0 || $cols <= 0 || ! is_array( $seat_map ) ) {
        return $content;
    }

    $seat_products = array_filter(
        (array) $seat_products,
        static function ( $product_id ) {
            return absint( $product_id ) > 0;
        }
    );

    $seat_types     = esc_get_seat_types( $post_id, $seat_map );
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

    $custom_lookup   = $is_custom_map ? esc_build_custom_seat_lookup( $seat_map ) : [];
    $seat_settings   = $is_custom_map ? esc_normalize_seat_settings( isset( $seat_map['settings'] ) ? (array) $seat_map['settings'] : [] ) : esc_get_default_seat_settings();
    $seat_size_attr  = $is_custom_map ? sprintf( ' style="--esc-seat-size: %dpx;"', (int) $seat_settings['seat_size'] ) : '';
    $seat_types_json = wp_json_encode( $seat_types );
    $seat_types_attr = $seat_types_json ? ' data-seat-types="' . esc_attr( $seat_types_json ) . '"' : '';
    $booking_attr    = sprintf( ' data-booking-locked="%d"', $booking_locked ? 1 : 0 );
    $message_attr    = '' !== $lock_message ? ' data-booking-message="' . esc_attr( $lock_message ) . '"' : '';

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
        <?php echo $seat_size_attr . $seat_types_attr . $booking_attr . $message_attr; ?>
    >
        <div class="esc-seat-selection__grid<?php echo $is_custom_map ? ' esc-seat-selection__grid--custom' : ''; ?>">
            <?php if ( $is_custom_map ) : ?>
                <?php foreach ( $seat_map['sections'] as $section ) :
                    $section_name = isset( $section['name'] ) ? $section['name'] : '';
                    $rows         = isset( $section['rows'] ) ? (array) $section['rows'] : [];

                    if ( empty( $rows ) ) {
                        continue;
                    }
                    ?>
                    <div class="esc-seat-zone">
                        <?php if ( '' !== $section_name ) : ?>
                            <h3 class="esc-seat-zone__title"><?php echo esc_html( $section_name ); ?></h3>
                        <?php endif; ?>
                        <div class="esc-seat-zone__rows">
                            <?php foreach ( $rows as $row ) :
                                $row_label = isset( $row['label'] ) ? $row['label'] : '';
                                $offset    = isset( $row['offset'] ) ? max( 0, (int) $row['offset'] ) : 0;
                                $seats     = isset( $row['seats'] ) ? (array) $row['seats'] : [];
                                $indent    = $offset > 0 ? ' style="margin-left: calc((var(--esc-seat-size, 60px) + var(--esc-seat-gap, 8px)) * ' . $offset . ');"' : '';
                                $row_classes = 'esc-seat-row';

                                if ( empty( $seats ) ) {
                                    $row_classes .= ' esc-seat-row--spacer';
                                }
                                ?>
                                <div class="<?php echo esc_attr( $row_classes ); ?>">
                                    <span class="esc-seat-row__label">
                                        <?php if ( '' !== $row_label ) : ?>
                                            <?php echo esc_html( $row_label ); ?>
                                        <?php else : ?>
                                            &nbsp;
                                        <?php endif; ?>
                                    </span>
                                    <div class="esc-seat-row__seats"<?php echo $indent; ?>>
                                        <?php if ( empty( $seats ) ) : ?>
                                            <span class="esc-seat-row__spacer-text">
                                                <?php echo '' !== $row_label ? esc_html( $row_label ) : esc_html__( 'Walkway', 'event-seating-clash' ); ?>
                                            </span>
                                        <?php else : ?>
                                            <?php foreach ( $seats as $seat ) :
                                                $seat_code = isset( $seat['code'] ) ? sanitize_title( $seat['code'] ) : '';

                                                if ( '' === $seat_code ) {
                                                    continue;
                                                }

                                                $seat_type = isset( $seat['type'] ) ? sanitize_key( $seat['type'] ) : '';

                                                if ( ! isset( $seat_types[ $seat_type ] ) ) {
                                                    continue;
                                                }

                                                $seat_label   = isset( $seat['seat_label'] ) ? $seat['seat_label'] : '';
                                                $display_label = '' !== $seat_label ? $seat_label : ( '' !== $row_label ? $row_label : strtoupper( $seat_code ) );

                                                $has_product  = ! empty( $seat_products[ $seat_type ] );
                                                $is_booked    = in_array( $seat_code, $booked, true );
                                                $is_disabled  = $is_booked || ! $has_product || $booking_locked;
                                                $button_class = 'esc-seat esc-seat--' . sanitize_html_class( $seat_type );

                                                if ( $is_booked ) {
                                                    $button_class .= ' is-booked';
                                                }

                                                if ( ! $has_product || $booking_locked ) {
                                                    $button_class .= ' is-unavailable';
                                                }

                                                $aria_label_parts = [];

                                                if ( '' !== $section_name ) {
                                                    $aria_label_parts[] = $section_name;
                                                }

                                                if ( '' !== $display_label ) {
                                                    $aria_label_parts[] = $display_label;
                                                }

                                                $aria_label = implode( ' – ', $aria_label_parts );
                                                ?>
                                                <button
                                                    type="button"
                                                    class="<?php echo esc_attr( $button_class ); ?>"
                                                    data-seat="<?php echo esc_attr( $seat_code ); ?>"
                                                    data-seat-type="<?php echo esc_attr( $seat_type ); ?>"
                                                    <?php echo $is_disabled ? 'disabled aria-disabled="true"' : 'aria-disabled="false"'; ?>
                                                    aria-pressed="false"
                                                    <?php if ( '' !== $aria_label ) : ?>
                                                        aria-label="<?php echo esc_attr( $aria_label ); ?>"
                                                    <?php endif; ?>
                                                >
                                                    <span class="esc-seat__label"><?php echo esc_html( $display_label ); ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
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
            <?php endif; ?>
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
            --esc-seat-size: 60px;
            --esc-seat-gap: 0.5rem;
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
        .esc-seat-selection__grid--custom {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }
        .esc-seat-zone {
            flex: 1 1 280px;
            min-width: 260px;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .esc-seat-zone__title {
            margin: 0;
            font-size: 1.1rem;
        }
        .esc-seat-zone__rows {
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
            gap: var( --esc-seat-gap, 0.5rem );
            flex-wrap: wrap;
        }
        .esc-seat-row--spacer .esc-seat-row__label {
            color: #6d6d6d;
        }
        .esc-seat-row__spacer-text {
            display: inline-block;
            padding: 0.45rem 1rem;
            background: #f0f0f0;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .esc-seat {
            position: relative;
            width: var( --esc-seat-size, 60px );
            height: var( --esc-seat-size, 60px );
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

    $seat_types = esc_get_seat_types( $event_id );
    $missing_types = [];

    foreach ( $normalized as $entry ) {
        if ( empty( $seat_products[ $entry[1] ] ) ) {
            $missing_types[ $entry[1] ] = true;
        }
    }

    if ( ! empty( $missing_types ) ) {
        $seat_types = esc_get_seat_types( $event_id );
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
    if ( esc_is_custom_seat_map( $seat_map ) ) {
        $normalized = esc_normalize_custom_seat_map( $seat_map );
        $lookup     = esc_build_custom_seat_lookup( $normalized );
        $seat_key   = sanitize_title( $seat_id );
        $seat_type  = sanitize_key( $seat_type );

        if ( '' === $seat_key || '' === $seat_type ) {
            return false;
        }

        if ( ! isset( $lookup[ $seat_key ] ) ) {
            return false;
        }

        return sanitize_key( $lookup[ $seat_key ]['type'] ) === $seat_type;
    }

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

    $event_id   = isset( $cart_item['esc_event_id'] ) ? (int) $cart_item['esc_event_id'] : 0;
    $seat_types = esc_get_seat_types( $event_id );
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
    $seat_types = esc_get_seat_types( (int) $values['esc_event_id'] );
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
