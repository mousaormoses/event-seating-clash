<?php
/**
 * Plugin Name:       Event Seat Booking Plugin
 * Description:       A plugin to create events and allow users to book seats.
 * Version:           1.0.0
 * Author:            Mousa Abdulaziz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Activation hook for creating database tables
function esbp_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
    $seats_table_name = $wpdb->prefix . 'esbp_seats';
    $event_seats_table_name = $wpdb->prefix . 'esbp_event_seats';
    $bookings_table_name = $wpdb->prefix . 'esbp_bookings';

    $sql = "CREATE TABLE $seat_maps_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;

    CREATE TABLE $seats_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        seat_map_id mediumint(9) NOT NULL,
        row_number mediumint(9) NOT NULL,
        seat_number mediumint(9) NOT NULL,
        seat_type varchar(55) DEFAULT 'regular' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY seat_map_row_seat (seat_map_id, row_number, seat_number),
        KEY seat_map_id (seat_map_id)
    ) $charset_collate;

    CREATE TABLE $event_seats_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        seat_id mediumint(9) NOT NULL,
        event_id mediumint(9) NOT NULL,
        booking_id mediumint(9) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY seat_id (seat_id),
        KEY event_id (event_id)
    ) $charset_collate;

    CREATE TABLE $bookings_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9) NOT NULL,
        user_name varchar(255) NOT NULL,
        user_email varchar(255) NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'esbp_activate' );

// Register Custom Post Type for Events
function esbp_register_event_post_type() {
    $labels = array(
        'name'                  => _x( 'Events', 'Post Type General Name', 'esbp-text-domain' ),
        'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'esbp-text-domain' ),
        'menu_name'             => __( 'Events', 'esbp-text-domain' ),
        'name_admin_bar'        => __( 'Event', 'esbp-text-domain' ),
        'archives'              => __( 'Event Archives', 'esbp-text-domain' ),
        'attributes'            => __( 'Event Attributes', 'esbp-text-domain' ),
        'parent_item_colon'     => __( 'Parent Event:', 'esbp-text-domain' ),
        'all_items'             => __( 'All Events', 'esbp-text-domain' ),
        'add_new_item'          => __( 'Add New Event', 'esbp-text-domain' ),
        'add_new'               => __( 'Add New', 'esbp-text-domain' ),
        'new_item'              => __( 'New Event', 'esbp-text-domain' ),
        'edit_item'             => __( 'Edit Event', 'esbp-text-domain' ),
        'update_item'           => __( 'Update Event', 'esbp-text-domain' ),
        'view_item'             => __( 'View Event', 'esbp-text-domain' ),
        'view_items'            => __( 'View Events', 'esbp-text-domain' ),
        'search_items'          => __( 'Search Event', 'esbp-text-domain' ),
        'not_found'             => __( 'Not found', 'esbp-text-domain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'esbp-text-domain' ),
        'featured_image'        => __( 'Featured Image', 'esbp-text-domain' ),
        'set_featured_image'    => __( 'Set featured image', 'esbp-text-domain' ),
        'remove_featured_image' => __( 'Remove featured image', 'esbp-text-domain' ),
        'use_featured_image'    => __( 'Use as featured image', 'esbp-text-domain' ),
        'insert_into_item'      => __( 'Insert into event', 'esbp-text-domain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this event', 'esbp-text-domain' ),
        'items_list'            => __( 'Events list', 'esbp-text-domain' ),
        'items_list_navigation' => __( 'Events list navigation', 'esbp-text-domain' ),
        'filter_items_list'     => __( 'Filter events list', 'esbp-text-domain' ),
    );
    $args = array(
        'label'                 => __( 'Event', 'esbp-text-domain' ),
        'description'           => __( 'Post Type for creating and managing events.', 'esbp-text-domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail' ),
        'taxonomies'            => array(),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'rewrite'               => array( 'slug' => 'events' ),
    );
    register_post_type( 'event', $args );
}
add_action( 'init', 'esbp_register_event_post_type', 0 );

// Load plugin textdomain
function esbp_load_textdomain() {
    load_plugin_textdomain( 'esbp-text-domain', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'esbp_load_textdomain' );

// Add meta boxes for event details
function esbp_add_event_meta_boxes() {
    add_meta_box(
        'esbp_event_details',
        __( 'Event Details', 'esbp-text-domain' ),
        'esbp_render_event_details_meta_box',
        'event',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'esbp_add_event_meta_boxes' );

// Render the meta box content
function esbp_render_event_details_meta_box( $post ) {
    // Add a nonce field for security
    wp_nonce_field( 'esbp_save_event_details', 'esbp_event_details_nonce' );

    // Get existing meta values
    $event_date = get_post_meta( $post->ID, '_event_date', true );
    $event_time = get_post_meta( $post->ID, '_event_time', true );
    $event_seat_map_id = get_post_meta( $post->ID, '_event_seat_map_id', true );

    global $wpdb;
    $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
    $seat_maps = $wpdb->get_results( "SELECT * FROM $seat_maps_table_name" );
    ?>
    <p>
        <label for="esbp_event_date"><?php _e( 'Event Date', 'esbp-text-domain' ); ?></label>
        <input type="date" id="esbp_event_date" name="esbp_event_date" value="<?php echo esc_attr( $event_date ); ?>" />
    </p>
    <p>
        <label for="esbp_event_time"><?php _e( 'Event Time', 'esbp-text-domain' ); ?></label>
        <input type="time" id="esbp_event_time" name="esbp_event_time" value="<?php echo esc_attr( $event_time ); ?>" />
    </p>
    <p>
        <label for="esbp_event_seat_map"><?php _e( 'Seat Map', 'esbp-text-domain' ); ?></label>
        <select id="esbp_event_seat_map" name="esbp_event_seat_map_id">
            <option value=""><?php _e( 'Select a Seat Map', 'esbp-text-domain' ); ?></option>
            <?php if ( $seat_maps ) : ?>
                <?php foreach ( $seat_maps as $seat_map ) : ?>
                    <option value="<?php echo esc_attr( $seat_map->id ); ?>" <?php selected( $event_seat_map_id, $seat_map->id ); ?>>
                        <?php echo esc_html( $seat_map->name ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </p>
    <?php
}

// Save the meta box data
function esbp_save_event_details( $post_id ) {
    // Check if our nonce is set.
    if ( ! isset( $_POST['esbp_event_details_nonce'] ) ) {
        return;
    }

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['esbp_event_details_nonce'], 'esbp_save_event_details' ) ) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Sanitize and save the data
    if ( isset( $_POST['esbp_event_date'] ) ) {
        update_post_meta( $post_id, '_event_date', sanitize_text_field( $_POST['esbp_event_date'] ) );
    }

    if ( isset( $_POST['esbp_event_time'] ) ) {
        update_post_meta( $post_id, '_event_time', sanitize_text_field( $_POST['esbp_event_time'] ) );
    }

    if ( isset( $_POST['esbp_event_seat_map_id'] ) ) {
        $seat_map_id = absint( $_POST['esbp_event_seat_map_id'] );
        update_post_meta( $post_id, '_event_seat_map_id', $seat_map_id );

        // Copy seats from template to event
        global $wpdb;
        $seats_table_name = $wpdb->prefix . 'esbp_seats';
        $event_seats_table_name = $wpdb->prefix . 'esbp_event_seats';
        $template_seats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $seats_table_name WHERE seat_map_id = %d", $seat_map_id ) );

        // Check if seats have already been copied
        $existing_seats = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $event_seats_table_name WHERE event_id = %d", $post_id ) );

        if ( $existing_seats == 0 ) {
            foreach ( $template_seats as $seat ) {
                $wpdb->insert(
                    $event_seats_table_name,
                    array(
                        'seat_id'  => $seat->id,
                        'event_id' => $post_id,
                    )
                );
            }
        }
    }
}
add_action( 'save_post', 'esbp_save_event_details' );

// Add admin menu for Seat Maps
function esbp_add_seat_map_admin_menu() {
    add_menu_page(
        __( 'Seat Maps', 'esbp-text-domain' ),
        __( 'Seat Maps', 'esbp-text-domain' ),
        'manage_options',
        'esbp-seat-maps',
        'esbp_render_seat_maps_page',
        'dashicons-layout',
        20
    );

    add_submenu_page(
        'esbp-seat-maps',
        __( 'Edit Seat Map', 'esbp-text-domain' ),
        __( 'Edit Seat Map', 'esbp-text-domain' ),
        'manage_options',
        'esbp-edit-seat-map',
        'esbp_render_edit_seat_map_page'
    );
}
add_action( 'admin_menu', 'esbp_add_seat_map_admin_menu' );

// Handle form submission for adding a new seat map
function esbp_handle_add_seat_map_form() {
    if ( isset( $_POST['esbp_action'] ) && $_POST['esbp_action'] === 'add_seat_map' ) {
        if ( ! isset( $_POST['esbp_add_seat_map_nonce'] ) || ! wp_verify_nonce( $_POST['esbp_add_seat_map_nonce'], 'esbp_add_seat_map_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['esbp_seat_map_name'] ) ) {
            global $wpdb;
            $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
            $seat_map_name = sanitize_text_field( $_POST['esbp_seat_map_name'] );

            $wpdb->insert(
                $seat_maps_table_name,
                array(
                    'name' => $seat_map_name,
                )
            );
        }
    }
}
add_action( 'admin_init', 'esbp_handle_add_seat_map_form' );

// Handle seat map deletion
function esbp_handle_delete_seat_map() {
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['seat_map_id'] ) ) {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'esbp_delete_seat_map' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
        $seats_table_name = $wpdb->prefix . 'esbp_seats';
        $seat_map_id = absint( $_GET['seat_map_id'] );

        $wpdb->delete( $seat_maps_table_name, array( 'id' => $seat_map_id ) );
        $wpdb->delete( $seats_table_name, array( 'seat_map_id' => $seat_map_id ) );

        wp_redirect( admin_url( 'admin.php?page=esbp-seat-maps' ) );
        exit;
    }
}
add_action( 'admin_init', 'esbp_handle_delete_seat_map' );

// Render the Seat Maps admin page
function esbp_render_seat_maps_page() {
    global $wpdb;
    $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
    $seat_maps = $wpdb->get_results( "SELECT * FROM $seat_maps_table_name" );
    ?>
    <div class="wrap">
        <h1><?php _e( 'Seat Maps', 'esbp-text-domain' ); ?></h1>

        <div id="col-container">
            <div id="col-right">
                <div class="col-wrap">
                    <h2><?php _e( 'Existing Seat Maps', 'esbp-text-domain' ); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column"><?php _e( 'ID', 'esbp-text-domain' ); ?></th>
                                <th scope="col" class="manage-column"><?php _e( 'Name', 'esbp-text-domain' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $seat_maps ) : ?>
                                <?php foreach ( $seat_maps as $seat_map ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $seat_map->id ); ?></td>
                                         <td>
                                            <a href="?page=esbp-edit-seat-map&seat_map_id=<?php echo esc_attr( $seat_map->id ); ?>">
                                                <?php echo esc_html( $seat_map->name ); ?>
                                            </a>
                                            <div class="row-actions">
                                                <span class="delete">
                                                    <a href="?page=esbp-seat-maps&action=delete&seat_map_id=<?php echo esc_attr( $seat_map->id ); ?>&_wpnonce=<?php echo wp_create_nonce( 'esbp_delete_seat_map' ); ?>">
                                                        <?php _e( 'Delete', 'esbp-text-domain' ); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="2"><?php _e( 'No seat maps found.', 'esbp-text-domain' ); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="col-left">
                <div class="col-wrap">
                    <h2><?php _e( 'Add New Seat Map', 'esbp-text-domain' ); ?></h2>
                    <form method="post" action="">
                        <input type="hidden" name="esbp_action" value="add_seat_map">
                        <?php wp_nonce_field( 'esbp_add_seat_map_nonce', 'esbp_add_seat_map_nonce' ); ?>
                        <div class="form-field">
                            <label for="esbp_seat_map_name"><?php _e( 'Seat Map Name', 'esbp-text-domain' ); ?></label>
                            <input type="text" id="esbp_seat_map_name" name="esbp_seat_map_name" value="" required />
                        </div>
                        <?php submit_button( __( 'Add New Seat Map', 'esbp-text-domain' ) ); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Handle form submission for editing a seat map
function esbp_handle_edit_seat_map_form() {
    if ( isset( $_POST['esbp_action'] ) && $_POST['esbp_action'] === 'edit_seat_map' ) {
        if ( ! isset( $_POST['esbp_edit_seat_map_nonce'] ) || ! wp_verify_nonce( $_POST['esbp_edit_seat_map_nonce'], 'esbp_edit_seat_map_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['esbp_seat_map_id'] ) && isset( $_POST['esbp_seats'] ) ) {
            global $wpdb;
            $seats_table_name = $wpdb->prefix . 'esbp_seats';
            $seat_map_id = absint( $_POST['esbp_seat_map_id'] );
            $seats = $_POST['esbp_seats'];

            foreach ( $seats as $row_number => $row_seats ) {
                foreach ( $row_seats as $seat_number => $seat_type ) {
                    $wpdb->replace(
                        $seats_table_name,
                        array(
                            'seat_map_id' => $seat_map_id,
                            'row_number'  => $row_number,
                            'seat_number' => $seat_number,
                            'seat_type'   => sanitize_text_field( $seat_type ),
                        )
                    );
                }
            }
        }
    }
}
add_action( 'admin_init', 'esbp_handle_edit_seat_map_form' );


// Render the Edit Seat Map page
function esbp_render_edit_seat_map_page() {
    if ( ! isset( $_GET['seat_map_id'] ) ) {
        return;
    }

    global $wpdb;
    $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
    $seats_table_name = $wpdb->prefix . 'esbp_seats';
    $seat_map_id = absint( $_GET['seat_map_id'] );
    $seat_map = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $seat_maps_table_name WHERE id = %d", $seat_map_id ) );
    $seats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $seats_table_name WHERE seat_map_id = %d ORDER BY row_number, seat_number", $seat_map_id ) );

    $seat_grid = array();
    foreach ( $seats as $seat ) {
        $seat_grid[ $seat->row_number ][ $seat->seat_number ] = $seat->seat_type;
    }

    $rows = isset( $_POST['esbp_rows'] ) ? absint( $_POST['esbp_rows'] ) : ( ! empty( $seat_grid ) ? max( array_keys( $seat_grid ) ) : 5 );
    $cols = isset( $_POST['esbp_cols'] ) ? absint( $_POST['esbp_cols'] ) : ( ! empty( $seat_grid ) ? max( array_map( 'max', array_map( 'array_keys', $seat_grid ) ) ) : 5 );
    ?>
    <div class="wrap">
        <h1><?php _e( 'Edit Seat Map', 'esbp-text-domain' ); ?>: <?php echo esc_html( $seat_map->name ); ?></h1>

        <form method="post" action="">
            <input type="hidden" name="esbp_action" value="generate_grid">
            <label for="esbp_rows"><?php _e( 'Rows:', 'esbp-text-domain' ); ?></label>
            <input type="number" id="esbp_rows" name="esbp_rows" value="<?php echo esc_attr( $rows ); ?>" min="1" max="50">
            <label for="esbp_cols"><?php _e( 'Columns:', 'esbp-text-domain' ); ?></label>
            <input type="number" id="esbp_cols" name="esbp_cols" value="<?php echo esc_attr( $cols ); ?>" min="1" max="50">
            <?php submit_button( __( 'Generate Grid', 'esbp-text-domain' ), 'secondary' ); ?>
        </form>

        <form method="post" action="">
            <input type="hidden" name="esbp_action" value="edit_seat_map">
            <input type="hidden" name="esbp_seat_map_id" value="<?php echo esc_attr( $seat_map_id ); ?>">
            <?php wp_nonce_field( 'esbp_edit_seat_map_nonce', 'esbp_edit_seat_map_nonce' ); ?>

            <table class="esbp-seat-map">
                <?php for ( $row = 1; $row <= $rows; $row++ ) : ?>
                    <tr>
                        <?php for ( $col = 1; $col <= $cols; $col++ ) : ?>
                            <td>
                                <select name="esbp_seats[<?php echo esc_attr( $row ); ?>][<?php echo esc_attr( $col ); ?>]">
                                    <option value="regular" <?php selected( isset( $seat_grid[ $row ][ $col ] ) ? $seat_grid[ $row ][ $col ] : '', 'regular' ); ?>><?php _e( 'Regular', 'esbp-text-domain' ); ?></option>
                                    <option value="premium" <?php selected( isset( $seat_grid[ $row ][ $col ] ) ? $seat_grid[ $row ][ $col ] : '', 'premium' ); ?>><?php _e( 'Premium', 'esbp-text-domain' ); ?></option>
                                    <option value="vip" <?php selected( isset( $seat_grid[ $row ][ $col ] ) ? $seat_grid[ $row ][ $col ] : '', 'vip' ); ?>><?php _e( 'VIP', 'esbp-text-domain' ); ?></option>
                                    <option value="disabled" <?php selected( isset( $seat_grid[ $row ][ $col ] ) ? $seat_grid[ $row ][ $col ] : '', 'disabled' ); ?>><?php _e( 'Disabled', 'esbp-text-domain' ); ?></option>
                                </select>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
            </table>
            <?php submit_button( __( 'Save Seat Map', 'esbp-text-domain' ) ); ?>
        </form>
    </div>
    <style>
        .esbp-seat-map {
            border-collapse: collapse;
            margin-top: 20px;
        }
        .esbp-seat-map td {
            border: 1px solid #ccc;
            padding: 5px;
        }
    </style>
    <?php
}

// Display seat map on the single event page
function esbp_display_seat_map_on_event_page( $content ) {
    if ( is_singular( 'event' ) ) {
        global $post, $wpdb;
        $seat_map_id = get_post_meta( $post->ID, '_event_seat_map_id', true );

        if ( $seat_map_id ) {
            $event_seats_table_name = $wpdb->prefix . 'esbp_event_seats';
            $seats_table_name = $wpdb->prefix . 'esbp_seats';
            $seats = $wpdb->get_results( $wpdb->prepare( "
                SELECT s.row_number, s.seat_number, s.seat_type, es.booking_id
                FROM $event_seats_table_name es
                JOIN $seats_table_name s ON es.seat_id = s.id
                WHERE es.event_id = %d
                ORDER BY s.row_number, s.seat_number
            ", $post->ID ) );

            if ( $seats ) {
                $seat_grid = array();
                foreach ( $seats as $seat ) {
                    $seat_grid[ $seat->row_number ][ $seat->seat_number ] = array(
                        'type' => $seat->seat_type,
                        'booked' => ! is_null( $seat->booking_id ),
                    );
                }

                ob_start();
                ?>
                <h2><?php _e( 'Choose Your Seats', 'esbp-text-domain' ); ?></h2>
                <table class="esbp-seat-map-frontend">
                    <?php foreach ( $seat_grid as $row_number => $row_seats ) : ?>
                        <tr>
                            <?php foreach ( $row_seats as $seat_number => $seat_data ) : ?>
                                <td class="seat-<?php echo esc_attr( $seat_data['type'] ); ?> <?php echo $seat_data['booked'] ? 'seat-booked' : ''; ?>">
                                    <?php echo esc_html( $row_number . '-' . $seat_number ); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <style>
                    .esbp-seat-map-frontend {
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    .esbp-seat-map-frontend td {
                        border: 1px solid #ccc;
                        padding: 10px;
                        text-align: center;
                        cursor: pointer;
                    }
                    .esbp-seat-map-frontend .seat-regular { background-color: #a4e7a4; }
                    .esbp-seat-map-frontend .seat-premium { background-color: #f7d5a3; }
                    .esbp-seat-map-frontend .seat-vip { background-color: #f7a3a3; }
                    .esbp-seat-map-frontend .seat-disabled { background-color: #ccc; cursor: not-allowed; }
                    .esbp-seat-map-frontend .selected { background-color: #6a6af4; }
                </style>
                <form id="esbp-booking-form">
                    <?php wp_nonce_field( 'esbp_book_seats_nonce', 'esbp_book_seats_nonce' ); ?>
                    <input type="hidden" name="esbp_event_id" value="<?php echo esc_attr( $post->ID ); ?>">
                    <input type="hidden" name="esbp_seat_map_id" value="<?php echo esc_attr( $seat_map_id ); ?>">
                    <input type="hidden" name="esbp_selected_seats" id="esbp_selected_seats" value="">
                    <p>
                        <label for="esbp_user_name"><?php _e( 'Name:', 'esbp-text-domain' ); ?></label>
                        <input type="text" id="esbp_user_name" name="esbp_user_name" required>
                    </p>
                    <p>
                        <label for="esbp_user_email"><?php _e( 'Email:', 'esbp-text-domain' ); ?></label>
                        <input type="email" id="esbp_user_email" name="esbp_user_email" required>
                    </p>
                    <?php submit_button( __( 'Book Now', 'esbp-text-domain' ), 'primary', 'esbp-book-now' ); ?>
                </form>
                <div id="esbp-booking-response"></div>
                <?php
                $content .= ob_get_clean();
            }
        }
    }
    return $content;
}
add_filter( 'the_content', 'esbp_display_seat_map_on_event_page' );

// Enqueue scripts and styles for the frontend
function esbp_enqueue_scripts() {
    if ( is_singular( 'event' ) ) {
        wp_enqueue_script( 'esbp-frontend-js', plugin_dir_url( __FILE__ ) . 'js/frontend.js', array( 'jquery' ), '1.0.0', true );
        wp_localize_script( 'esbp-frontend-js', 'esbp_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }
}
add_action( 'wp_enqueue_scripts', 'esbp_enqueue_scripts' );

// AJAX handler for booking seats
function esbp_book_seats() {
    if ( ! isset( $_POST['esbp_book_seats_nonce'] ) || ! wp_verify_nonce( $_POST['esbp_book_seats_nonce'], 'esbp_book_seats_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'esbp-text-domain' ) ) );
    }

    if ( ! isset( $_POST['event_id'] ) || ! isset( $_POST['seat_map_id'] ) || ! isset( $_POST['selected_seats'] ) || ! isset( $_POST['user_name'] ) || ! isset( $_POST['user_email'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid data.', 'esbp-text-domain' ) ) );
    }

    global $wpdb;
    $bookings_table_name = $wpdb->prefix . 'esbp_bookings';
    $event_seats_table_name = $wpdb->prefix . 'esbp_event_seats';
    $seats_table_name = $wpdb->prefix . 'esbp_seats';
    $event_id = absint( $_POST['event_id'] );
    $seat_map_id = absint( $_POST['seat_map_id'] );
    $selected_seats = json_decode( stripslashes( $_POST['selected_seats'] ) );
    $user_name = sanitize_text_field( $_POST['user_name'] );
    $user_email = sanitize_email( $_POST['user_email'] );

    // Check for concurrency
    foreach ( $selected_seats as $seat ) {
        $seat_parts = explode( '-', $seat );
        $row_number = absint( $seat_parts[0] );
        $seat_number = absint( $seat_parts[1] );

        $seat_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT id FROM $seats_table_name
            WHERE seat_map_id = %d AND row_number = %d AND seat_number = %d
        ", $seat_map_id, $row_number, $seat_number ) );

        $is_booked = $wpdb->get_var( $wpdb->prepare( "
            SELECT booking_id FROM $event_seats_table_name
            WHERE event_id = %d AND seat_id = %d
        ", $event_id, $seat_id ) );

        if ( ! is_null( $is_booked ) ) {
            wp_send_json_error( array( 'message' => __( 'Sorry, one or more of the seats you selected have already been booked.', 'esbp-text-domain' ) ) );
        }
    }

    // Insert booking
    $wpdb->insert(
        $bookings_table_name,
        array(
            'event_id'   => $event_id,
            'user_name'  => $user_name,
            'user_email' => $user_email,
        )
    );
    $booking_id = $wpdb->insert_id;

    // Update seats with booking ID
    foreach ( $selected_seats as $seat ) {
        $seat_parts = explode( '-', $seat );
        $row_number = absint( $seat_parts[0] );
        $seat_number = absint( $seat_parts[1] );

        $seat_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT id FROM $seats_table_name
            WHERE seat_map_id = %d AND row_number = %d AND seat_number = %d
        ", $seat_map_id, $row_number, $seat_number ) );

        $wpdb->update(
            $event_seats_table_name,
            array( 'booking_id' => $booking_id ),
            array(
                'event_id' => $event_id,
                'seat_id'  => $seat_id,
            )
        );
    }

    wp_send_json_success( array( 'message' => __( 'Booking successful!', 'esbp-text-domain' ) ) );
}
add_action( 'wp_ajax_esbp_book_seats', 'esbp_book_seats' );
add_action( 'wp_ajax_nopriv_esbp_book_seats', 'esbp_book_seats' );

// Uninstall hook for cleaning up database tables
function esbp_uninstall() {
    global $wpdb;
    $seat_maps_table_name = $wpdb->prefix . 'esbp_seat_maps';
    $seats_table_name = $wpdb->prefix . 'esbp_seats';
    $event_seats_table_name = $wpdb->prefix . 'esbp_event_seats';
    $bookings_table_name = $wpdb->prefix . 'esbp_bookings';

    $wpdb->query( "DROP TABLE IF EXISTS $seat_maps_table_name" );
    $wpdb->query( "DROP TABLE IF EXISTS $seats_table_name" );
    $wpdb->query( "DROP TABLE IF EXISTS $event_seats_table_name" );
    $wpdb->query( "DROP TABLE IF EXISTS $bookings_table_name" );
}
register_uninstall_hook( __FILE__, 'esbp_uninstall' );
