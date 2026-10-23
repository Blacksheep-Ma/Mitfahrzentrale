<?php
/**
 * Plugin Name: Mitfahrzentrale für CommonsBooking
 * Description: Termine, CommonsBooking-Artikel und Fahrer-Zeitslots verwalten.
 * Version: 0.1.0
 * Author: Mitfahrzentrale
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class MFZ_Mitfahrzentrale {
	const POST_TYPE = 'mfz_termin';
	const INSTITUTION_POST_TYPE = 'mfz_institution';
	const META_INSTITUTION_USERS = '_mfz_institution_users';
	const META_PASSENGERS = '_mfz_passengers';
	const META_INSTITUTION_ADDRESS = '_mfz_institution_address';
	const META_INSTITUTION_STREET = '_mfz_institution_street';
	const META_INSTITUTION_POSTCODE = '_mfz_institution_postcode';
	const META_INSTITUTION_CITY = '_mfz_institution_city';
	const META_ITEMS = '_mfz_items';
	const META_DATE = '_mfz_date';
	const META_LOCATION = '_mfz_location';
	const META_SLOTS = '_mfz_slots';
	const OPTION_VIEW_ROLES = '_mfz_view_roles';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_institution_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_event' ), 10, 2 );
		add_shortcode( 'mitfahrzentrale_fahrer', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'mitfahrzentrale_institution', array( __CLASS__, 'institution_shortcode' ) );
		add_shortcode( 'mitfahrzentrale_mitfahrer', array( __CLASS__, 'passenger_management_shortcode' ) );
		add_shortcode( 'mitfahrzentrale_admin', array( __CLASS__, 'admin_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_footer', array( __CLASS__, 'calendar_scroll_script' ) );
		add_action( 'admin_post_mfz_signup', array( __CLASS__, 'signup' ) );
		add_action( 'admin_post_mfz_withdraw', array( __CLASS__, 'withdraw' ) );
		add_action( 'admin_post_mfz_send_confirmation', array( __CLASS__, 'send_confirmation' ) );
		add_action( 'admin_post_mfz_institution_save_passengers', array( __CLASS__, 'institution_save_passengers' ) );
		add_action( 'admin_post_mfz_add_passenger', array( __CLASS__, 'add_passenger' ) );
		add_action( 'admin_post_mfz_remove_passenger', array( __CLASS__, 'remove_passenger' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_event_columns' ) );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'admin_event_sortable_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_event_column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'admin_event_location_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_event_orderby' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_event_filter' ) );
		add_action( 'add_meta_boxes_' . self::INSTITUTION_POST_TYPE, array( __CLASS__, 'add_institution_meta_boxes' ) );
		add_action( 'save_post_' . self::INSTITUTION_POST_TYPE, array( __CLASS__, 'save_institution' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => 'Mitfahr-Termine',
					'singular_name' => 'Mitfahr-Termin',
					'add_new_item' => 'Termin erstellen',
					'edit_item' => 'Termin bearbeiten',
					'menu_name' => 'Mitfahrzentrale',
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'menu_icon' => 'dashicons-car',
				'supports' => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}

	public static function register_institution_post_type() {
		register_post_type( self::INSTITUTION_POST_TYPE, array(
			'labels' => array(
				'name' => 'Institutionen', 'singular_name' => 'Institution',
				'add_new_item' => 'Institution hinzufügen', 'edit_item' => 'Institution bearbeiten',
				'menu_name' => 'Institutionen',
			),
			'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=' . self::POST_TYPE,
			'menu_icon' => 'dashicons-building', 'supports' => array( 'title', 'editor' ),
			'capability_type' => 'post', 'map_meta_cap' => true,
		) );
	}

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'Berechtigungen', 'Berechtigungen', 'manage_options', 'mfz-permissions', array( __CLASS__, 'permissions_page' ) );
	}

	public static function register_settings() {
		register_setting( 'mfz_permissions', self::OPTION_VIEW_ROLES, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_view_roles' ), 'default' => self::default_view_roles() ) );
	}

	public static function admin_event_columns( $columns ) {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result['mfz_date'] = 'Tagesdatum';
				$result['mfz_location'] = 'Standort';
				$result['mfz_items'] = 'Artikel / Fahrräder';
			}
		}
		return $result;
	}

	public static function admin_event_sortable_columns( $columns ) {
		$columns['mfz_date'] = 'mfz_date';
		return $columns;
	}

	public static function admin_event_orderby( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) || 'mfz_date' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', self::META_DATE );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'meta_type', 'DATE' );
	}

	public static function admin_event_location_filter( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		$selected = absint( $_GET['mfz_location_filter'] ?? 0 );
		echo '<select name="mfz_location_filter"><option value="">Alle Standorte</option>';
		foreach ( self::locations() as $location ) {
			printf( '<option value="%d" %s>%s</option>', absint( $location->ID ), selected( $selected, $location->ID, false ), esc_html( $location->post_title ) );
		}
		echo '</select>';
	}

	public static function admin_event_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$location_id = absint( $_GET['mfz_location_filter'] ?? 0 );
		if ( $location_id ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key' => self::META_LOCATION,
						'value' => (string) $location_id,
						'compare' => '=',
					),
				)
			);
		}
	}

	public static function admin_event_column_content( $column, $post_id ) {
		if ( 'mfz_date' === $column ) {
			$date = get_post_meta( $post_id, self::META_DATE, true );
			if ( $date ) {
				$timestamp = strtotime( $date . ' 12:00:00' );
				echo esc_html( $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date );
			}
			return;
		}

		if ( 'mfz_location' === $column ) {
			$location_id = absint( get_post_meta( $post_id, self::META_LOCATION, true ) );
			echo esc_html( $location_id ? get_the_title( $location_id ) : '–' );
			return;
		}

		if ( 'mfz_items' === $column ) {
			$slots = (array) get_post_meta( $post_id, self::META_SLOTS, true );
			$item_ids = array_unique( array_filter( array_map( 'absint', array_column( $slots, 'item_id' ) ) ) );
			$item_titles = array_filter( array_map( 'get_the_title', $item_ids ) );
			echo esc_html( $item_titles ? implode( ', ', $item_titles ) : '–' );
		}
	}

	private static function default_view_roles() {
		$roles = array_keys( wp_roles()->roles );
		return array( 'driver' => $roles, 'institution' => $roles, 'admin' => array( 'administrator' ) );
	}

	public static function sanitize_view_roles( $value ) {
		$valid_roles = array_keys( wp_roles()->roles );
		$sanitized = array();
		foreach ( array( 'driver', 'institution', 'admin' ) as $view ) {
			$selected = isset( $value[ $view ] ) ? (array) $value[ $view ] : array();
			$sanitized[ $view ] = array_values( array_intersect( array_map( 'sanitize_key', $selected ), $valid_roles ) );
		}
		return $sanitized;
	}

	private static function can_view( $view ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$settings = get_option( self::OPTION_VIEW_ROLES, self::default_view_roles() );
		$allowed_roles = array_map( 'sanitize_key', (array) ( $settings[ $view ] ?? array() ) );
		return (bool) array_intersect( wp_get_current_user()->roles, $allowed_roles );
	}

	public static function permissions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$roles = wp_roles()->roles;
		$settings = get_option( self::OPTION_VIEW_ROLES, self::default_view_roles() );
		$views = array( 'driver' => 'Fahreransicht', 'institution' => 'Institutionsseite', 'admin' => 'Organisatorseite' );
		?>
		<div class="wrap">
			<h1>Mitfahrzentrale – Berechtigungen</h1>
			<p>Wählen Sie aus, welche WordPress-Rollen die jeweilige Seite anzeigen dürfen.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'mfz_permissions' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $views as $view => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<?php foreach ( $roles as $role_key => $role ) : ?>
									<label style="display:block;margin-bottom:6px">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_VIEW_ROLES . '[' . $view . '][]' ); ?>" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, (array) ( $settings[ $view ] ?? array() ), true ) ); ?> />
										<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function add_meta_boxes() {
		add_meta_box( 'mfz_details', 'Termin und Zeitslots', array( __CLASS__, 'details_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function add_institution_meta_boxes() {
		add_meta_box( 'mfz_institution_users', 'Benutzer dieser Institution', array( __CLASS__, 'institution_users_box' ), self::INSTITUTION_POST_TYPE, 'normal', 'high' );
	}

	private static function searchable_user_dropdown( $name, $users, $selected, $label ) {
		$selected = array_map( 'absint', (array) $selected );
		$selected_names = array(); foreach ( $users as $user ) if ( in_array( absint( $user->ID ), $selected, true ) ) $selected_names[] = $user->display_name;
		echo '<div class="mfz-user-dropdown"><p><strong>' . esc_html( $label ) . '</strong></p><p class="mfz-selected-user-names"><strong>Ausgewählt:</strong> ' . esc_html( $selected_names ? implode( ', ', $selected_names ) : 'keine' ) . '</p><input type="search" class="mfz-user-search" placeholder="Benutzer suchen ..." autocomplete="off"><div class="mfz-user-options">';
		foreach ( $users as $user ) {
			$is_selected = in_array( $user->ID, $selected, true );
			printf( '<label class="mfz-user-option" style="display:%s"><input type="checkbox" name="%s[]" value="%d" %s> <span>%s</span> <small>%s</small></label>', $is_selected ? 'block' : 'none', esc_attr( $name ), absint( $user->ID ), checked( $is_selected, true, false ), esc_html( $user->display_name ), esc_html( $user->user_email ) );
		}
		echo '</div></div><script>(function(){document.addEventListener("input",function(e){if(!e.target.classList.contains("mfz-user-search"))return;var q=e.target.value.toLowerCase(),box=e.target.closest(".mfz-user-dropdown");box.querySelectorAll(".mfz-user-option").forEach(function(row){var match=row.textContent.toLowerCase().indexOf(q)>=0,checked=row.querySelector("input").checked;row.style.display=(q?match:checked)?"block":"none";});});document.addEventListener("change",function(e){if(!e.target.matches(".mfz-user-options input[type=checkbox]"))return;var box=e.target.closest(".mfz-user-dropdown"),summary=box.querySelector(".mfz-selected-user-names");if(!summary)return;var names=[];box.querySelectorAll(".mfz-user-options input[type=checkbox]:checked").forEach(function(input){names.push(input.parentElement.querySelector("span").textContent);});summary.innerHTML="<strong>Ausgewählt:</strong> "+(names.length?names.join(", "):"keine");});}());</script>';
	}

	public static function institution_users_box( $post ) {
		wp_nonce_field( 'mfz_save_institution', 'mfz_institution_nonce' );
		echo '<h3>Adresse</h3><p><label for="mfz_institution_street">Straße und Hausnummer</label><br><input type="text" id="mfz_institution_street" name="mfz_institution_street" class="large-text" value="' . esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION_STREET, true ) ) . '" placeholder="Straße und Hausnummer"></p><p><label for="mfz_institution_postcode">Postleitzahl</label><br><input type="text" id="mfz_institution_postcode" name="mfz_institution_postcode" class="small-text" value="' . esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION_POSTCODE, true ) ) . '" placeholder="PLZ"> <label for="mfz_institution_city">Ort</label> <input type="text" id="mfz_institution_city" name="mfz_institution_city" class="regular-text" value="' . esc_attr( get_post_meta( $post->ID, self::META_INSTITUTION_CITY, true ) ) . '" placeholder="Ort"></p>';
		$selected = array_map( 'absint', (array) get_post_meta( $post->ID, self::META_INSTITUTION_USERS, true ) );
		self::searchable_user_dropdown( 'mfz_institution_users', get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) ), $selected, 'Fahrer auswählen und suchen' );
		$passengers = (array) get_post_meta( $post->ID, self::META_PASSENGERS, true ); echo '<p><strong>Mitfahrer</strong></p><div id="mfz-passengers">';
		foreach ( $passengers as $passenger_id => $passenger_name ) echo '<p><input type="hidden" name="mfz_passengers[' . absint( $passenger_id ) . ']" value="' . esc_attr( $passenger_name ) . '"><input type="text" value="' . esc_attr( $passenger_name ) . '" class="regular-text" readonly> <button type="button" class="button-link-delete mfz-remove-passenger">Entfernen</button></p>';
		echo '</div><p><input type="text" id="mfz-new-passenger" class="regular-text" placeholder="Name des Mitfahrers"><button type="button" class="button" id="mfz-add-passenger">Mitfahrer hinzufügen</button></p><script>(function(){var next=' . ( $passengers ? max( array_map( 'absint', array_keys( $passengers ) ) ) + 1 : 1 ) . ',box=document.getElementById("mfz-passengers"),input=document.getElementById("mfz-new-passenger");document.getElementById("mfz-add-passenger").addEventListener("click",function(){var n=input.value.trim();if(!n)return;var p=document.createElement("p");p.innerHTML="<input type=\"hidden\" name=\"mfz_passengers["+next+"]\" value=\"\"><input type=\"text\" class=\"regular-text\" value=\"\" readonly> <button type=\"button\" class=\"button-link-delete mfz-remove-passenger\">Entfernen</button>";p.querySelectorAll("input")[0].value=n;p.querySelectorAll("input")[1].value=n;box.appendChild(p);input.value="";next++;});box.addEventListener("click",function(e){if(e.target.classList.contains("mfz-remove-passenger"))e.target.parentElement.remove();});}());</script>';
	}

	public static function save_institution( $post_id, $post ) {
		if ( ! isset( $_POST['mfz_institution_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_institution_nonce'] ) ), 'mfz_save_institution' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) return;
		$selected = array_values( array_unique( array_map( 'absint', (array) ( $_POST['mfz_institution_users'] ?? array() ) ) ) );
		$old = array_map( 'absint', (array) get_post_meta( $post_id, self::META_INSTITUTION_USERS, true ) );
		foreach ( array_unique( array_merge( $old, $selected ) ) as $user_id ) {
			if ( in_array( $user_id, $selected, true ) ) update_user_meta( $user_id, '_mfz_location_id', $post_id );
			elseif ( absint( get_user_meta( $user_id, '_mfz_location_id', true ) ) === $post_id ) delete_user_meta( $user_id, '_mfz_location_id' );
		}
		update_post_meta( $post_id, self::META_INSTITUTION_USERS, $selected );
		update_post_meta( $post_id, self::META_INSTITUTION_STREET, sanitize_text_field( wp_unslash( $_POST['mfz_institution_street'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_POSTCODE, sanitize_text_field( wp_unslash( $_POST['mfz_institution_postcode'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_INSTITUTION_CITY, sanitize_text_field( wp_unslash( $_POST['mfz_institution_city'] ?? '' ) ) );
		$passengers = array(); foreach ( (array) ( $_POST['mfz_passengers'] ?? array() ) as $passenger_id => $passenger_name ) { $passenger_name = sanitize_text_field( wp_unslash( $passenger_name ) ); if ( $passenger_name !== '' ) $passengers[ absint( $passenger_id ) ] = $passenger_name; } update_post_meta( $post_id, self::META_PASSENGERS, $passengers );
	}

	private static function item_post_type() {
		$candidates = array( 'cb_item', 'commonsbooking_item', 'item' );
		foreach ( $candidates as $candidate ) {
			if ( post_type_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return apply_filters( 'mfz_commonsbooking_item_post_type', 'cb_item' );
	}

	private static function items() {
		return get_posts(
			array(
				'post_type' => self::item_post_type(),
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);
	}

	private static function item_image( $item_id ) {
		$item_id = absint( $item_id );
		$thumbnail_id = $item_id ? get_post_thumbnail_id( $item_id ) : 0;
		if ( ! $thumbnail_id ) {
			return '';
		}

		$image = wp_get_attachment_image( $thumbnail_id, 'medium', false, array(
			'class'   => 'mfz-bike-image',
			'loading' => 'lazy',
			'alt'     => get_the_title( $item_id ),
		) );

		return $image ? '<span class="mfz-bike-image-wrapper">' . $image . '</span>' : '';
	}

	private static function locations() {
		return get_posts( array( 'post_type' => self::INSTITUTION_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	}

	private static function institution_address( $institution_id ) {
		$parts = array_filter( array(
			get_post_meta( absint( $institution_id ), self::META_INSTITUTION_STREET, true ),
			trim( get_post_meta( absint( $institution_id ), self::META_INSTITUTION_POSTCODE, true ) . ' ' . get_post_meta( absint( $institution_id ), self::META_INSTITUTION_CITY, true ) ),
		) );
		return $parts ? implode( ', ', $parts ) : get_post_meta( absint( $institution_id ), self::META_INSTITUTION_ADDRESS, true );
	}

	private static function user_contact_details( $user_id ) {
		$user_id = absint( $user_id );
		$first_value = static function ( $keys ) use ( $user_id ) {
			foreach ( $keys as $key ) {
				$value = trim( (string) get_user_meta( $user_id, $key, true ) );
				if ( $value !== '' ) return $value;
			}
			return '';
		};
		$street = $first_value( array( 'mfz_street', 'street', 'address', 'billing_address_1', '_billing_address_1' ) );
		$postcode = $first_value( array( 'mfz_postcode', 'postcode', 'zip', 'billing_postcode', '_billing_postcode' ) );
		$city = $first_value( array( 'mfz_city', 'city', 'billing_city', '_billing_city' ) );
		return array(
			'address' => implode( ', ', array_filter( array( $street, trim( $postcode . ' ' . $city ) ) ) ),
			'phone' => $first_value( array( 'mfz_phone', 'phone', 'telephone', 'billing_phone', '_billing_phone', 'mobile' ) ),
		);
	}

	private static function user_institution_id( $user_id ) {
		foreach ( self::locations() as $institution ) {
			$users = array_map( 'absint', (array) get_post_meta( $institution->ID, self::META_INSTITUTION_USERS, true ) );
			if ( in_array( absint( $user_id ), $users, true ) ) return absint( $institution->ID );
		}
		return 0;
	}

	private static function location_passengers( $location_id ) {
		$passengers = (array) get_post_meta( absint( $location_id ), self::META_PASSENGERS, true ); $result = array();
		foreach ( $passengers as $passenger_id => $name ) { $result[] = (object) array( 'ID' => absint( $passenger_id ), 'display_name' => $name, 'user_email' => '' ); }
		return $result;
	}

	private static function searchable_passenger_dropdown( $name, $passengers, $selected, $label ) {
		$selected = array_map( 'absint', (array) $selected ); echo '<div class="mfz-user-dropdown mfz-passenger-input" style="position:relative"><p><strong>' . esc_html( $label ) . '</strong></p><input type="search" class="mfz-user-search" placeholder="Mitfahrer suchen ..." autocomplete="off"><div class="mfz-user-options" style="display:none;background:#fff;border:1px solid #ccd0d4;padding:8px;margin-top:4px;max-height:220px;overflow:auto;width:100%;box-sizing:border-box;">';
		foreach ( $passengers as $passenger ) printf( '<label class="mfz-user-option" style="display:none"><input type="checkbox" name="%s[]" value="%d" %s> <span>%s</span></label>', esc_attr( $name ), absint( $passenger->ID ), checked( in_array( $passenger->ID, $selected, true ), true, false ), esc_html( $passenger->display_name ) );
		echo '</div></div><script>(function(){document.addEventListener("focus",function(e){if(!e.target.classList.contains("mfz-user-search"))return;var box=e.target.closest(".mfz-passenger-input"),options=box.querySelector(".mfz-user-options");options.style.display="block";if(!e.target.value)box.querySelectorAll(".mfz-user-option").forEach(function(row){row.style.display="block";});},true);document.addEventListener("input",function(e){if(!e.target.classList.contains("mfz-user-search"))return;var q=e.target.value.toLowerCase(),box=e.target.closest(".mfz-passenger-input"),options=box.querySelector(".mfz-user-options");options.style.display="block";box.querySelectorAll(".mfz-user-option").forEach(function(row){row.style.display=!q||row.textContent.toLowerCase().indexOf(q)>=0?"block":"none";});});}());</script>';
	}

	public static function details_box( $post ) {
		wp_nonce_field( 'mfz_save_event', 'mfz_nonce' );
		$date = get_post_meta( $post->ID, self::META_DATE, true );
		$location_id = absint( get_post_meta( $post->ID, self::META_LOCATION, true ) );
		$slots = get_post_meta( $post->ID, self::META_SLOTS, true );
		$slots = is_array( $slots ) ? $slots : array();
		$options = '<option value="">Artikel auswählen</option>';
		foreach ( self::items() as $item ) {
			$options .= sprintf( '<option value="%d">%s</option>', absint( $item->ID ), esc_html( $item->post_title ) );
		}
		?>
		<p><label for="mfz_date"><strong>Termin</strong></label><br><input type="date" id="mfz_date" name="mfz_date" value="<?php echo esc_attr( $date ); ?>" required></p>
		<p><label for="mfz_location"><strong>Standort</strong></label><br><select id="mfz_location" name="mfz_location" required><option value="">Standort auswählen</option><?php foreach ( self::locations() as $location ) : ?><option value="<?php echo absint( $location->ID ); ?>" <?php selected( $location_id, $location->ID ); ?>><?php echo esc_html( $location->post_title . ( self::institution_address( $location->ID ) ? ' – ' . self::institution_address( $location->ID ) : '' ) ); ?></option><?php endforeach; ?></select></p>
		<p><strong>Zeitslots</strong></p>
		<div id="mfz-slots">
		<?php foreach ( $slots as $index => $slot ) : self::slot_row( $index, $slot, $options ); endforeach; ?>
		</div>
		<p><button type="button" class="button" id="mfz-add-slot">Zeitslot hinzufügen</button></p>
		<p class="description">Jeder Slot enthält Startzeit, Endzeit, Artikel, Mitfahrerzahl und genau einen Fahrer.</p>
		<script>
		(function () {
			const container = document.getElementById('mfz-slots');
			const options = <?php echo wp_json_encode( $options ); ?>;
			let index = <?php echo count( $slots ); ?>;
			document.getElementById('mfz-add-slot').addEventListener('click', function () {
				const row = document.createElement('div');
				row.className = 'mfz-slot-admin-row';
				row.innerHTML = '<p><label>Start <input type="time" name="mfz_slots[' + index + '][start]" required></label> <label>Ende <input type="time" name="mfz_slots[' + index + '][end]" required></label> <label>Artikel <select name="mfz_slots[' + index + '][item_id]" required>' + options + '</select></label> <label>Mitfahrer <input type="number" min="0" name="mfz_slots[' + index + '][passenger_count]" value="0" required></label> <span class="description">Fahrer wird durch Anmeldung gesetzt.</span> <button type="button" class="button-link-delete mfz-remove-slot" aria-label="Slot löschen" title="Slot löschen">×</button></p>';
				container.appendChild(row); index++; renumberRows();
			});
			function renumberRows() {
				container.querySelectorAll('.mfz-slot-admin-row').forEach(function (row, rowIndex) {
					row.querySelectorAll('[name]').forEach(function (field) {
						field.name = field.name.replace(/mfz_slots\[\d+\]/, 'mfz_slots[' + rowIndex + ']');
					});
				});
			}
			container.addEventListener('click', function (event) {
				if (event.target.classList.contains('mfz-remove-slot')) { event.target.closest('.mfz-slot-admin-row').remove(); renumberRows(); }
			});
		}());
		</script>
		<?php
	}

	private static function slot_row( $index, $slot, $options ) {
		$selected = str_replace( 'value="' . absint( $slot['item_id'] ?? 0 ) . '"', 'value="' . absint( $slot['item_id'] ?? 0 ) . '" selected', $options );
		$driver = absint( $slot['driver_id'] ?? 0 );
		echo '<div class="mfz-slot-admin-row"><p><label>Start <input type="time" name="mfz_slots[' . absint( $index ) . '][start]" value="' . esc_attr( $slot['start'] ?? '' ) . '" required></label> <label>Ende <input type="time" name="mfz_slots[' . absint( $index ) . '][end]" value="' . esc_attr( $slot['end'] ?? '' ) . '" required></label> <label>Artikel <select name="mfz_slots[' . absint( $index ) . '][item_id]" required>' . $selected . '</select></label> <label>Mitfahrer <input type="number" min="0" name="mfz_slots[' . absint( $index ) . '][passenger_count]" value="' . absint( $slot['passenger_count'] ?? 0 ) . '" required></label> <span class="description">Fahrer: ' . esc_html( $driver ? get_the_author_meta( 'display_name', $driver ) : 'noch frei' ) . '</span> <button type="button" class="button-link-delete mfz-remove-slot" aria-label="Slot löschen" title="Slot löschen">×</button></p></div>';
	}

	public static function save_event( $post_id, $post ) {
		if ( ! isset( $_POST['mfz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_nonce'] ) ), 'mfz_save_event' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$date = sanitize_text_field( wp_unslash( $_POST['mfz_date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}
		update_post_meta( $post_id, self::META_DATE, $date );
		$location_id = absint( $_POST['mfz_location'] ?? 0 );
		update_post_meta( $post_id, self::META_LOCATION, self::INSTITUTION_POST_TYPE === get_post_type( $location_id ) ? $location_id : 0 );
		$slots = array();
		$posted_slots = (array) ( $_POST['mfz_slots'] ?? array() );
		foreach ( $posted_slots as $posted_slot ) {
			$start = sanitize_text_field( $posted_slot['start'] ?? '' );
			$end = sanitize_text_field( $posted_slot['end'] ?? '' );
			$item_id = absint( $posted_slot['item_id'] ?? 0 );
			if ( preg_match( '/^\d{2}:\d{2}$/', $start ) && preg_match( '/^\d{2}:\d{2}$/', $end ) && get_post( $item_id ) && get_post_type( $item_id ) === self::item_post_type() ) {
				$slots[] = array( 'start' => $start, 'end' => $end, 'item_id' => $item_id, 'passenger_count' => absint( $posted_slot['passenger_count'] ?? 0 ), 'driver_id' => 0, 'passenger_ids' => array() );
			}
		}
		$old_slots = get_post_meta( $post_id, self::META_SLOTS, true );
		if ( is_array( $old_slots ) ) {
			foreach ( $slots as $index => $slot ) {
				$slots[ $index ]['driver_id'] = absint( $old_slots[ $index ]['driver_id'] ?? 0 );
				$slots[ $index ]['passenger_ids'] = array_map( 'absint', (array) ( $old_slots[ $index ]['passenger_ids'] ?? array() ) );
				$slots[ $index ]['confirmation_sent_at'] = absint( $old_slots[ $index ]['confirmation_sent_at'] ?? 0 );
			}
		}
		update_post_meta( $post_id, self::META_SLOTS, $slots );
	}

	private static function is_past_slot( $event_id, $slot ) {
		$event_date = (string) get_post_meta( $event_id, self::META_DATE, true );
		$today = current_time( 'Y-m-d' );
		if ( $event_date < $today ) {
			return true;
		}
		if ( $event_date !== $today || empty( $slot['end'] ) || ! preg_match( '/^(\d{1,2}):(\d{2})/', (string) $slot['end'], $matches ) ) {
			return false;
		}
		$end_time = sprintf( '%02d:%02d', absint( $matches[1] ), absint( $matches[2] ) );
		return current_time( 'H:i' ) >= $end_time;
	}

	private static function redirect( $status ) {
		$url = wp_get_referer() ?: home_url( '/' );
		$return_date = sanitize_text_field( wp_unslash( $_POST['mfz_return_date'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $return_date ) ) {
			$url = add_query_arg( 'mfz_date', $return_date, $url );
		}
		$return_scroll = absint( $_POST['mfz_return_scroll'] ?? 0 );
		if ( $return_scroll ) {
			$url = add_query_arg( 'mfz_scroll', $return_scroll, $url );
		}
		wp_safe_redirect( add_query_arg( 'mfz_status', $status, $url ) );
		exit;
	}

	private static function change_signup( $withdraw = false ) {
		if ( ! is_user_logged_in() ) {
			self::redirect( 'login' );
		}
		$event_id = absint( $_POST['event_id'] ?? 0 );
		$slot_index = absint( $_POST['slot_index'] ?? 0 );
		if ( ! isset( $_POST['mfz_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_action_nonce'] ) ), 'mfz_slot_action' ) ) {
			self::redirect( 'error' );
		}
		$slots = get_post_meta( $event_id, self::META_SLOTS, true );
		if ( get_post_type( $event_id ) !== self::POST_TYPE || absint( get_post_meta( $event_id, self::META_LOCATION, true ) ) !== self::user_institution_id( get_current_user_id() ) || ! is_array( $slots ) || ! isset( $slots[ $slot_index ] ) ) {
			self::redirect( 'error' );
		}
		if ( self::is_past_slot( $event_id, $slots[ $slot_index ] ) ) {
			self::redirect( 'past' );
		}
		$user_id = get_current_user_id();
		foreach ( $slots as $index => &$slot ) {
			$slot['driver_id'] = absint( $slot['driver_id'] ?? 0 );
			$slot['passenger_ids'] = array_values( array_diff( array_map( 'absint', (array) ( $slot['passenger_ids'] ?? array() ) ), array( $user_id ) ) );
			if ( $index === $slot_index ) {
				if ( $withdraw ) {
					if ( $slot['driver_id'] === $user_id ) {
						$slot['driver_id'] = 0;
					}
				} elseif ( $slot['driver_id'] ) {
					self::redirect( 'full' );
				} else {
					$slot['driver_id'] = $user_id;
				}
				if ( ! $withdraw && $slot['driver_id'] !== $user_id ) {
					self::redirect( 'full' );
				}
			}
		}
		update_post_meta( $event_id, self::META_SLOTS, $slots );
		self::redirect( $withdraw ? 'withdrawn' : 'signed_up' );
	}

	public static function signup() { self::change_signup( false ); }
	public static function withdraw() { self::change_signup( true ); }

	public static function send_confirmation() {
		$event_id = absint( $_POST['event_id'] ?? 0 );
		$slot_index = absint( $_POST['slot_index'] ?? 0 );
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! isset( $_POST['mfz_confirmation_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_confirmation_nonce'] ) ), 'mfz_send_confirmation' ) ) {
			self::redirect( 'error' );
		}
		$slots = get_post_meta( $event_id, self::META_SLOTS, true );
		if ( get_post_type( $event_id ) !== self::POST_TYPE || ! is_array( $slots ) || ! isset( $slots[ $slot_index ] ) || ! absint( $slots[ $slot_index ]['driver_id'] ?? 0 ) ) {
			self::redirect( 'confirmation_not_sent' );
		}
		$sent = self::send_slot_confirmation_emails( $event_id, $slot_index, $slots[ $slot_index ] );
		if ( $sent ) {
			$slots[ $slot_index ]['confirmation_sent_at'] = current_time( 'timestamp' );
			update_post_meta( $event_id, self::META_SLOTS, $slots );
		}
		self::redirect( $sent ? 'confirmation_sent' : 'confirmation_not_sent' );
	}

	private static function send_slot_confirmation_emails( $event_id, $slot_index, $slot ) {
		$event_id = absint( $event_id );
		$location_id = absint( get_post_meta( $event_id, self::META_LOCATION, true ) );
		$driver_id = absint( $slot['driver_id'] ?? 0 );
		$recipients = array();
		$driver = $driver_id ? get_userdata( $driver_id ) : false;
		if ( $driver && is_email( $driver->user_email ) ) {
			$recipients[] = $driver->user_email;
		}
		foreach ( (array) get_post_meta( $location_id, self::META_INSTITUTION_USERS, true ) as $user_id ) {
			$user = get_userdata( absint( $user_id ) );
			if ( $user && is_email( $user->user_email ) ) {
				$recipients[] = $user->user_email;
			}
		}
		$recipients = array_values( array_unique( $recipients ) );
		if ( ! $recipients ) {
			return false;
		}
		$event_title = get_the_title( $event_id );
		$date = get_post_meta( $event_id, self::META_DATE, true );
		$institution = $location_id ? get_the_title( $location_id ) : 'nicht festgelegt';
		$institution_address = $location_id ? self::institution_address( $location_id ) : 'nicht hinterlegt';
		$fahrrad = ! empty( $slot['item_id'] ) ? get_the_title( absint( $slot['item_id'] ) ) : 'nicht festgelegt';
		$passenger_count = count( array_filter( array_map( 'absint', (array) ( $slot['passenger_ids'] ?? array() ) ) ) );
		$subject = 'Terminbestätigung: ' . $event_title . ' – Slot ' . ( absint( $slot_index ) + 1 );
		$message = "Für den folgende Slot wurde Sie als Fahrer bestätigt:\n\n";
		$message .= "Termin: " . $event_title . "\nDatum: " . $date . "\nInstitution: " . $institution . "\nAdresse Institution: " . ( $institution_address ?: 'nicht hinterlegt' ) . "\n";
		$message .= "Zeit Slot: " . ( absint( $slot_index ) + 1 ) . "\nStart: " . ( $slot['start'] ?? '' ) . "\nEnde: " . ( $slot['end'] ?? '' ) . "\n";
		$message .= "Fahrrad: " . $fahrrad . "\nMitfahrer: " . $passenger_count . '/' . absint( $slot['passenger_count'] ?? 0 ) . "\n";
		$message .= "Fahrer: " . ( $driver ? $driver->display_name : 'nicht festgelegt' ) . "\n";
		return (bool) wp_mail( $recipients, $subject, $message );
	}

	private static function can_manage_institution( $location_id ) {
		return current_user_can( 'edit_users' ) || self::user_institution_id( get_current_user_id() ) === absint( $location_id );
	}

	public static function institution_save_passengers() {
		if ( ! is_user_logged_in() || ! isset( $_POST['mfz_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_action_nonce'] ) ), 'mfz_institution_passengers' ) ) self::redirect( 'error' );
		$event_id = absint( $_POST['event_id'] ?? 0 ); $slot_index = absint( $_POST['slot_index'] ?? 0 ); $slots = get_post_meta( $event_id, self::META_SLOTS, true ); $location_id = absint( get_post_meta( $event_id, self::META_LOCATION, true ) );
		if ( get_post_type( $event_id ) !== self::POST_TYPE || ! self::can_manage_institution( $location_id ) || ! isset( $slots[ $slot_index ] ) ) self::redirect( 'error' );
		$allowed = array_map( 'absint', wp_list_pluck( self::location_passengers( $location_id ), 'ID' ) ); $selected = array_map( 'absint', (array) ( $_POST['passenger_ids'] ?? array() ) );
		$selected = array_diff( array_values( array_intersect( $selected, $allowed ) ), array( absint( $slots[ $slot_index ]['driver_id'] ?? 0 ) ) );
		$slots[ $slot_index ]['passenger_ids'] = array_slice( $selected, 0, absint( $slots[ $slot_index ]['passenger_count'] ?? 0 ) );
		update_post_meta( $event_id, self::META_SLOTS, $slots );
		self::redirect( 'passengers_saved' );
	}

	public static function add_passenger() {
		$location_id = absint( $_POST['institution_id'] ?? 0 );
		if ( ! is_user_logged_in() || ! self::can_manage_institution( $location_id ) || ! isset( $_POST['mfz_passenger_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_passenger_nonce'] ) ), 'mfz_manage_passengers' ) ) self::redirect( 'error' );
		$name = sanitize_text_field( wp_unslash( $_POST['passenger_name'] ?? '' ) ); $passengers = (array) get_post_meta( $location_id, self::META_PASSENGERS, true );
		if ( $name !== '' ) { $next_id = $passengers ? max( array_map( 'absint', array_keys( $passengers ) ) ) + 1 : 1; $passengers[ $next_id ] = $name; update_post_meta( $location_id, self::META_PASSENGERS, $passengers ); }
		self::redirect( 'passenger_saved' );
	}

	public static function remove_passenger() {
		$location_id = absint( $_POST['institution_id'] ?? 0 ); $passenger_id = absint( $_POST['passenger_id'] ?? 0 );
		if ( ! is_user_logged_in() || ! self::can_manage_institution( $location_id ) || ! isset( $_POST['mfz_passenger_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfz_passenger_nonce'] ) ), 'mfz_manage_passengers' ) ) self::redirect( 'error' );
		$passengers = (array) get_post_meta( $location_id, self::META_PASSENGERS, true ); unset( $passengers[ $passenger_id ] ); update_post_meta( $location_id, self::META_PASSENGERS, $passengers );
		self::redirect( 'passenger_removed' );
	}

	public static function enqueue_styles() {
		wp_enqueue_style(
			'mfz-frontend',
			plugin_dir_url( __FILE__ ) . 'mitfahrzentrale.css',
			array(),
			'0.1.3'
		);
	}

	public static function calendar_scroll_script() {
		?>
		<script>
		document.addEventListener('click', function (event) {
			if (!event.target.matches('.mfz-calendar-days button')) return;
			var calendar = event.target.closest('.mfz-driver-calendar');
			if (calendar) window.setTimeout(function () { calendar.scrollIntoView({ block: 'start' }); }, 0);
		});
		</script>
		<?php
	}

	public static function institution_calendar_script() {
		?>
		<script>
		(function () {
			var calendar = document.querySelector('.mfz-institution-view .mfz-driver-calendar');
			if (!calendar) return;
			var events = Array.from(document.querySelectorAll('.mfz-institution-view > article'));
			var planned = {};
			document.querySelectorAll('.mfz-institution-view form').forEach(function (form) {
				var eventId = form.querySelector('input[name="event_id"]');
				var slotIndex = form.querySelector('input[name="slot_index"]');
				if (eventId && slotIndex) {
					form.id = 'mfz-institution-slot-' + eventId.value + '-' + slotIndex.value;
				}
				form.addEventListener('submit', function () {
					var dateField = form.querySelector('input[name="mfz_return_date"]');
					if (!dateField) {
						dateField = document.createElement('input');
						dateField.type = 'hidden';
						dateField.name = 'mfz_return_date';
						form.appendChild(dateField);
					}
					dateField.value = selected || '';
					var scrollField = form.querySelector('input[name="mfz_return_scroll"]');
					if (!scrollField) {
						scrollField = document.createElement('input');
						scrollField.type = 'hidden';
						scrollField.name = 'mfz_return_scroll';
						form.appendChild(scrollField);
					}
					scrollField.value = String(window.scrollY || window.pageYOffset || 0);
				});
			});
			events.forEach(function (article) {
				var match = article.textContent.match(/(\d{2})\.(\d{2})\.(\d{4})/);
				if (match) {
					var date = match[3] + '-' + match[2] + '-' + match[1];
					article.dataset.mfzDate = date;
					planned[date] = true;
				}
			});
			var requested = new URLSearchParams(window.location.search).get('mfz_date');
			var today = dateKey(new Date());
			var selected = requested && planned[requested] ? requested : null;
			if (!selected) events.some(function (event) { if (event.dataset.mfzDate >= today) { selected = event.dataset.mfzDate; return true; } return false; });
			if (!selected && events.length) selected = events[events.length - 1].dataset.mfzDate;
			var current = new Date(selected ? selected + 'T12:00:00' : new Date());
			function pad(number) { return String(number).padStart(2, '0'); }
			function dateKey(date) { return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()); }
			function selectDay(date) {
				selected = date;
				events.forEach(function (article) { article.style.display = article.dataset.mfzDate === date ? 'block' : 'none'; });
				render();
				calendar.scrollIntoView({ block: 'start' });
			}
			function render() {
				var year = current.getFullYear(), month = current.getMonth(), first = new Date(year, month, 1), last = new Date(year, month + 1, 0), offset = (first.getDay() + 6) % 7, days = calendar.querySelector('.mfz-calendar-days'), today = dateKey(new Date());
				calendar.querySelector('.mfz-calendar-month').textContent = current.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
				days.innerHTML = '';
				for (var i = 0; i < offset; i++) days.insertAdjacentHTML('beforeend', '<span class="mfz-calendar-empty"></span>');
				for (var day = 1; day <= last.getDate(); day++) {
					var key = dateKey(new Date(year, month, day)), button = document.createElement('button');
					button.type = 'button'; button.textContent = day; button.dataset.date = key;
					if (key < today) button.classList.add('mfz-past-day');
					if (planned[key]) button.classList.add('mfz-planned-day');
					if (planned[key] && key < today) button.classList.add('mfz-past-planned-day');
					if (key === selected) button.classList.add('mfz-selected-day');
					days.appendChild(button);
				}
			}
			calendar.addEventListener('click', function (event) {
				if (event.target.classList.contains('mfz-calendar-prev')) { current.setMonth(current.getMonth() - 1); render(); }
				if (event.target.classList.contains('mfz-calendar-next')) { current.setMonth(current.getMonth() + 1); render(); }
				if (event.target.dataset.date && planned[event.target.dataset.date]) selectDay(event.target.dataset.date);
			});
			if (selected) selectDay(selected); else render();
			var scrollPosition = new URLSearchParams(window.location.search).get('mfz_scroll');
			if (scrollPosition !== null && /^\d+$/.test(scrollPosition)) {
				var restoreScroll = function () { window.scrollTo(0, parseInt(scrollPosition, 10) || 0); };
				window.requestAnimationFrame(function () {
					window.requestAnimationFrame(restoreScroll);
				});
				window.setTimeout(restoreScroll, 150);
			}
		}());
		</script>
		<?php
	}

	public static function admin_calendar_script() {
		?>
		<script>
		(function () {
			var calendar = document.querySelector('.mfz-admin-view .mfz-driver-calendar');
			if (!calendar) return;
			var events = Array.from(document.querySelectorAll('.mfz-admin-view > .mfz-admin-day'));
			var planned = {};
			document.querySelectorAll('.mfz-admin-view form').forEach(function (form) {
				form.addEventListener('submit', function () {
					var dateField = form.querySelector('input[name="mfz_return_date"]');
					if (!dateField) {
						dateField = document.createElement('input');
						dateField.type = 'hidden';
						dateField.name = 'mfz_return_date';
						form.appendChild(dateField);
					}
					dateField.value = selected || '';
					var scrollField = form.querySelector('input[name="mfz_return_scroll"]');
					if (!scrollField) {
						scrollField = document.createElement('input');
						scrollField.type = 'hidden';
						scrollField.name = 'mfz_return_scroll';
						form.appendChild(scrollField);
					}
					scrollField.value = String(window.scrollY || window.pageYOffset || 0);
				});
			});
			events.forEach(function (event) {
				var match = event.textContent.match(/\d{4}-\d{2}-\d{2}/);
				if (match) {
					event.dataset.mfzDate = match[0];
					planned[match[0]] = true;
				}
			});
			var requested = new URLSearchParams(window.location.search).get('mfz_date');
			var today = dateKey(new Date());
			var selected = requested && planned[requested] ? requested : null;
			if (!selected) events.some(function (event) { if (event.dataset.mfzDate >= today) { selected = event.dataset.mfzDate; return true; } return false; });
			if (!selected && events.length) selected = events[events.length - 1].dataset.mfzDate;
			var current = new Date(selected ? selected + 'T12:00:00' : new Date());
			function pad(number) { return String(number).padStart(2, '0'); }
			function dateKey(date) { return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()); }
			function selectDay(date) {
				selected = date;
				events.forEach(function (event) { event.style.display = event.dataset.mfzDate === date ? 'block' : 'none'; });
				render();
				calendar.scrollIntoView({ block: 'start' });
			}
			function render() {
				var year = current.getFullYear(), month = current.getMonth(), first = new Date(year, month, 1), last = new Date(year, month + 1, 0), offset = (first.getDay() + 6) % 7, days = calendar.querySelector('.mfz-calendar-days'), today = dateKey(new Date());
				calendar.querySelector('.mfz-calendar-month').textContent = current.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
				days.innerHTML = '';
				for (var i = 0; i < offset; i++) days.insertAdjacentHTML('beforeend', '<span class="mfz-calendar-empty"></span>');
				for (var day = 1; day <= last.getDate(); day++) {
					var key = dateKey(new Date(year, month, day)), button = document.createElement('button');
					button.type = 'button'; button.textContent = day; button.dataset.date = key;
					if (key < today) button.classList.add('mfz-past-day');
					if (planned[key]) button.classList.add('mfz-planned-day');
					if (planned[key] && key < today) button.classList.add('mfz-past-planned-day');
					if (key === selected) button.classList.add('mfz-selected-day');
					days.appendChild(button);
				}
			}
			calendar.addEventListener('click', function (event) {
				if (event.target.classList.contains('mfz-calendar-prev')) { current.setMonth(current.getMonth() - 1); render(); }
				if (event.target.classList.contains('mfz-calendar-next')) { current.setMonth(current.getMonth() + 1); render(); }
				if (event.target.dataset.date && planned[event.target.dataset.date]) selectDay(event.target.dataset.date);
			});
			if (selected) selectDay(selected); else render();
			var scrollPosition = new URLSearchParams(window.location.search).get('mfz_scroll');
			if (scrollPosition !== null && /^\d+$/.test(scrollPosition)) {
				var restoreScroll = function () { window.scrollTo(0, parseInt(scrollPosition, 10) || 0); };
				window.requestAnimationFrame(function () {
					window.requestAnimationFrame(restoreScroll);
				});
				window.setTimeout(restoreScroll, 150);
			}
		}());
		</script>
		<?php
	}

	public static function frontend_styles() {
		return;
		echo '<style>.mfz-institution-view > article,.mfz-events > article.mfz-event,.mfz-admin-view > .mfz-admin-day{margin:0 0 24px;padding:16px;border:1px solid #ccd0d4;border-radius:6px;background:#fafafa}.mfz-institution-view > article > h3,.mfz-events > article.mfz-event > h3,.mfz-admin-view > .mfz-admin-day > summary h3{margin-top:0}.mfz-institution-view form{display:block;margin:16px 0!important;padding:12px!important;border:1px solid #dcdcde!important;border-radius:5px;background:#fff}.mfz-events .mfz-slot,.mfz-admin-view .mfz-slot{margin:16px 0;padding:12px;border:1px solid #dcdcde;border-radius:5px;background:#fff}.mfz-events .mfz-slot{position:relative;min-height:150px;padding-right:210px;padding-bottom:58px;line-height:1.6}.mfz-events .mfz-slot h4{line-height:1.25}.mfz-events .mfz-slot form{position:absolute;right:12px;bottom:12px;float:none;display:block!important;margin:0!important;vertical-align:bottom}.mfz-events .mfz-slot:after{content:"";display:block;clear:both}.mfz-bike-image-wrapper{position:absolute;top:12px;right:12px;display:block;margin:0}.mfz-bike-image{display:block;width:auto;max-width:180px;height:auto;max-height:100px;object-fit:contain;border-radius:4px}.mfz-driver-calendar{width:100%;max-width:none;box-sizing:border-box;margin:0 0 24px;padding:12px;border:1px solid #ccd0d4;border-radius:6px;background:#fafafa}.mfz-calendar-header{display:grid;grid-template-columns:36px 1fr 36px;align-items:center;gap:8px;text-align:center;margin-bottom:8px}.mfz-calendar-header button{padding:2px 8px}.mfz-calendar-weekdays,.mfz-calendar-days{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center}.mfz-calendar-weekdays{font-size:.85em;font-weight:bold;margin-bottom:4px}.mfz-calendar-days button{min-height:32px;padding:4px;border:1px solid transparent;border-radius:4px;background:#fff;cursor:pointer}.mfz-calendar-days .mfz-planned-day{background:#d9f2df;border-color:#168a35;font-weight:bold}.mfz-calendar-days .mfz-selected-day{outline:2px solid #2271b1;outline-offset:1px}.mfz-calendar-empty{min-height:32px}@media (max-width:600px){.mfz-events .mfz-slot{min-height:0;padding-right:12px;padding-bottom:12px}.mfz-events .mfz-slot form{position:static;float:none;margin:10px 0 0!important}.mfz-bike-image-wrapper{position:static;margin:10px 0}.mfz-bike-image{max-width:100%;max-height:180px}}@media print{@page{margin:10mm}html,body{margin:0!important;padding:0!important}body *{visibility:hidden!important}.mfz-admin-view .mfz-print-target,.mfz-admin-view .mfz-print-target *{visibility:visible!important}.mfz-admin-view .mfz-print-target{position:absolute;left:0;top:0;width:100%;margin:0!important;border:0!important;background:#fff!important}.mfz-admin-view .mfz-print-day,.mfz-admin-view .mfz-send-confirmation{display:none!important}}</style>';
	}

	public static function admin_shortcode( $atts ) {
		if ( ! self::can_view( 'admin' ) ) {
			return '<p>Für diese Organisatoransicht ist keine Berechtigung vorhanden.</p>';
		}
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => self::META_DATE, 'orderby' => 'meta_value', 'order' => 'ASC' ) );
		ob_start();
		echo '<div class="mfz-admin-view"><div class="mfz-driver-calendar" aria-label="Kalender für geplante Termine"><div class="mfz-calendar-header"><button type="button" class="mfz-calendar-prev" aria-label="Vorheriger Monat">‹</button><strong class="mfz-calendar-month"></strong><button type="button" class="mfz-calendar-next" aria-label="Nächster Monat">›</button></div><div class="mfz-calendar-weekdays"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div><div class="mfz-calendar-days"></div></div><h2>Organisatoransicht</h2>';
		if ( ! $query->have_posts() ) {
			echo '<p>Aktuell sind keine Termine verfügbar.</p>';
		}
		while ( $query->have_posts() ) {
			$query->the_post();
			$event_id = get_the_ID();
			$location_id = absint( get_post_meta( $event_id, self::META_LOCATION, true ) );
			$slots = (array) get_post_meta( $event_id, self::META_SLOTS, true );
			$item_ids = array_unique( array_filter( array_map( 'absint', array_column( $slots, 'item_id' ) ) ) );
			$passenger_names = array();
			foreach ( self::location_passengers( $location_id ) as $passenger ) {
				$passenger_names[ absint( $passenger->ID ) ] = $passenger->display_name;
			}
			echo '<details open class="mfz-admin-day"><summary style="display:flex;justify-content:space-between;align-items:center;gap:12px;cursor:pointer"><h3 style="margin:0">' . esc_html( get_the_title() ) . '</h3><button type="button" class="mfz-print-day" title="Diesen Tag drucken">Tag drucken</button></summary><div class="mfz-admin-day-content"><p><strong>Datum:</strong> ' . esc_html( get_post_meta( $event_id, self::META_DATE, true ) ) . ' · <strong>Institution:</strong> ' . esc_html( $location_id ? get_the_title( $location_id ) : 'nicht festgelegt' ) . '</p>';
			if ( $item_ids ) {
				echo '<p><strong>Eingesetzte Fahrräder:</strong></p><ul style="margin-top:0;padding-left:0;list-style-position:inside">';
				foreach ( $item_ids as $item_id ) {
					echo '<li>' . esc_html( get_the_title( $item_id ) ) . '</li>';
				}
				echo '</ul>';
			}
			$slot_number = 1;
			foreach ( $slots as $slot_index => $slot ) {
				$driver_id = absint( $slot['driver_id'] ?? 0 );
				$driver_contact = self::user_contact_details( $driver_id );
				$selected_passengers = array_map( 'absint', (array) ( $slot['passenger_ids'] ?? array() ) );
				$capacity = absint( $slot['passenger_count'] ?? 0 );
				$complete = $driver_id > 0 && count( $selected_passengers ) >= $capacity;
				$confirmation_sent_at = absint( $slot['confirmation_sent_at'] ?? 0 );
				echo '<section class="mfz-slot"><h4 style="margin-top:0">Slot ' . $slot_number++ . ( $complete ? ' <span style="color:#168a35;font-size:1.3em;font-weight:bold" title="Fahrer und Mitfahrer vollständig">✓</span>' : '' ) . '</h4><p><strong>Start:</strong> ' . esc_html( $slot['start'] ?? '' ) . ' · <strong>Ende:</strong> ' . esc_html( $slot['end'] ?? '' ) . '</p>';
				if ( ! empty( $slot['item_id'] ) ) {
					echo '<p><strong>Fahrrad:</strong> ' . esc_html( get_the_title( absint( $slot['item_id'] ) ) ) . '</p>';
				}
				echo '<p><strong>Fahrer:</strong> ' . esc_html( $driver_id ? get_the_author_meta( 'display_name', $driver_id ) : 'noch frei' ) . '</p>';
				if ( $driver_id ) {
					echo '<p><strong>Adresse Fahrer:</strong> ' . esc_html( $driver_contact['address'] ?: 'nicht hinterlegt' ) . '<br><strong>Telefon Fahrer:</strong> ' . esc_html( $driver_contact['phone'] ?: 'nicht hinterlegt' ) . '<br><strong>E-Mail Fahrer:</strong> ' . esc_html( get_the_author_meta( 'user_email', $driver_id ) ?: 'nicht hinterlegt' ) . '</p>';
				}
				echo '<p><strong>Mitfahrer:</strong> ' . count( $selected_passengers ) . '/' . $capacity . '</p><p><strong>Eingetragene Mitfahrer:</strong></p><div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px"><div style="flex:1">';
				if ( $selected_passengers ) {
					echo '<ul style="margin-top:0;padding-left:0;list-style-position:inside">';
					foreach ( $selected_passengers as $passenger_id ) {
						if ( isset( $passenger_names[ $passenger_id ] ) ) {
							echo '<li>' . esc_html( $passenger_names[ $passenger_id ] ) . '</li>';
						}
					}
					echo '</ul>';
				} else {
					echo '<p>keine</p>';
				}
				echo '<p><strong>Bestätigung:</strong> ' . esc_html( $confirmation_sent_at ? 'verschickt am ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $confirmation_sent_at ) : 'noch nicht verschickt' ) . '</p>';
				echo '</div>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mfz-send-confirmation" style="margin:0 0 4px auto;flex:0 0 auto">';
				wp_nonce_field( 'mfz_send_confirmation', 'mfz_confirmation_nonce' );
				echo '<input type="hidden" name="action" value="mfz_send_confirmation"><input type="hidden" name="event_id" value="' . $event_id . '"><input type="hidden" name="slot_index" value="' . absint( $slot_index ) . '"><button type="submit"' . ( $driver_id ? '' : ' disabled title="Für diesen Slot ist noch kein Fahrer eingetragen." style="opacity:.5;cursor:not-allowed"' ) . '>' . ( $confirmation_sent_at ? 'Bestätigung erneut senden' : 'Bestätigung senden' ) . '</button></form>';
				echo '</div></section>';
			}
			echo '</div></details>';
		}
		self::admin_calendar_script();
		wp_reset_postdata();
		echo '</div><script>(function(){document.addEventListener("click",function(e){if(!e.target.classList.contains("mfz-print-day"))return;var day=e.target.closest(".mfz-admin-day"),clone=day.cloneNode(true),button=clone.querySelector(".mfz-print-day"),confirmation=clone.querySelectorAll(".mfz-send-confirmation"),printWindow=window.open("","_blank","width=900,height=700");if(!printWindow)return;if(button)button.remove();confirmation.forEach(function(form){form.remove();});printWindow.document.open();printWindow.document.write("<!doctype html><html><head><meta charset=\"utf-8\"><title>Tag drucken</title><style>body{font-family:Arial,sans-serif;margin:10mm;color:#111}.mfz-slot{margin:16px 0;padding:12px;border:1px solid #dcdcde;border-radius:5px;background:#fff}h3{margin-top:0}h4{margin-top:0}</style></head><body></body></html>");printWindow.document.close();printWindow.document.body.appendChild(clone);printWindow.focus();printWindow.setTimeout(function(){printWindow.print();printWindow.close();},250);});}());</script>';
		return ob_get_clean();
	}

	public static function passenger_management_shortcode( $atts ) {
		$location_id = self::user_institution_id( get_current_user_id() );
		if ( ! self::can_view( 'institution' ) || ! $location_id || ! self::can_manage_institution( $location_id ) ) return '<p>Für diese Institutionsansicht ist keine Berechtigung oder Institution hinterlegt.</p>';
		ob_start(); echo '<div class="mfz-passenger-management"><h2>Mitfahrer – ' . esc_html( get_the_title( $location_id ) ) . '</h2><p><strong>Adresse:</strong> ' . esc_html( self::institution_address( $location_id ) ?: 'nicht hinterlegt' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'mfz_manage_passengers', 'mfz_passenger_nonce' ); echo '<input type="hidden" name="action" value="mfz_add_passenger"><input type="hidden" name="institution_id" value="' . $location_id . '"><input type="text" name="passenger_name" required placeholder="Name des Mitfahrers"><button type="submit">Mitfahrer hinzufügen</button></form><table><thead><tr><th>Name</th><th>Aktion</th></tr></thead><tbody>';
		foreach ( (array) get_post_meta( $location_id, self::META_PASSENGERS, true ) as $passenger_id => $name ) { echo '<tr><td>' . esc_html( $name ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'mfz_manage_passengers', 'mfz_passenger_nonce' ); echo '<input type="hidden" name="action" value="mfz_remove_passenger"><input type="hidden" name="institution_id" value="' . $location_id . '"><input type="hidden" name="passenger_id" value="' . absint( $passenger_id ) . '"><button type="submit">Entfernen</button></form></td></tr>'; }
		echo '</tbody></table></div>'; return ob_get_clean();
	}

	public static function institution_shortcode( $atts ) {
		$location_id = self::user_institution_id( get_current_user_id() );
		if ( ! self::can_view( 'institution' ) || ! $location_id || ! self::can_manage_institution( $location_id ) ) return '<p>Für diese Institutionsansicht ist keine Berechtigung oder Institution hinterlegt.</p>';
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => self::META_LOCATION, 'value' => $location_id, 'compare' => '=' ), array( 'key' => self::META_DATE, 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ) ), 'orderby' => 'meta_value', 'meta_key' => self::META_DATE, 'order' => 'ASC' ) );
		ob_start(); echo '<div class="mfz-institution-view"><div class="mfz-driver-calendar" aria-label="Kalender für geplante Termine"><div class="mfz-calendar-header"><button type="button" class="mfz-calendar-prev" aria-label="Vorheriger Monat">‹</button><strong class="mfz-calendar-month"></strong><button type="button" class="mfz-calendar-next" aria-label="Nächster Monat">›</button></div><div class="mfz-calendar-weekdays"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div><div class="mfz-calendar-days"></div></div><h2>Mitfahrer – ' . esc_html( get_the_title( $location_id ) ) . '</h2><p><strong>Adresse:</strong> ' . esc_html( self::institution_address( $location_id ) ?: 'nicht hinterlegt' ) . '</p>';
		if ( ! $query->have_posts() ) echo '<p>Keine Termine für diese Institution vorhanden.</p>';
		while ( $query->have_posts() ) { $query->the_post(); $event_id = get_the_ID(); $slots = (array) get_post_meta( $event_id, self::META_SLOTS, true ); echo '<article><h3>' . esc_html( get_the_title() ) . '</h3><p>' . esc_html( get_post_meta( $event_id, self::META_DATE, true ) ) . '</p>'; $slot_number = 1; foreach ( $slots as $index => $slot ) { $passengers = array_map( 'absint', (array) ( $slot['passenger_ids'] ?? array() ) ); $slot_passengers = self::location_passengers( $location_id ); $passenger_names = array(); foreach ( $slot_passengers as $passenger ) { if ( in_array( absint( $passenger->ID ), $passengers, true ) ) $passenger_names[] = $passenger->display_name; } $complete = ! empty( $slot['driver_id'] ) && count( $passengers ) >= absint( $slot['passenger_count'] ?? 0 ); $item_id = absint( $slot['item_id'] ?? 0 ); echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0;padding:8px;border:1px solid #ddd"><h4 style="margin-top:0">Slot ' . $slot_number++ . ( $complete ? ' <span style="color:#168a35;font-size:1.3em;font-weight:bold" title="Fahrer und Mitfahrer vollständig">✓</span>' : '' ) . '</h4><strong>Start:</strong> ' . esc_html( $slot['start'] ?? '' ) . ' · <strong>Ende:</strong> ' . esc_html( $slot['end'] ?? '' ); if ( $item_id ) { echo '<br><strong>Fahrrad:</strong> ' . esc_html( get_the_title( $item_id ) ) . self::item_image( $item_id ); } echo '<br><strong>Fahrer:</strong> ' . esc_html( ! empty( $slot['driver_id'] ) ? get_the_author_meta( 'display_name', absint( $slot['driver_id'] ) ) : 'noch frei' ) . '<br><p><strong>Eingetragene Mitfahrer (' . count( $passengers ) . '/' . absint( $slot['passenger_count'] ?? 0 ) . '):</strong></p>'; if ( $passenger_names ) { echo '<table class="mfz-passengers-table"><thead><tr><th>Name</th></tr></thead><tbody>'; foreach ( $passenger_names as $passenger_name ) echo '<tr><td>' . esc_html( $passenger_name ) . '</td></tr>'; echo '</tbody></table>'; } else echo '<p>keine</p>'; wp_nonce_field( 'mfz_institution_passengers', 'mfz_action_nonce' ); echo '<input type="hidden" name="action" value="mfz_institution_save_passengers"><input type="hidden" name="event_id" value="' . $event_id . '"><input type="hidden" name="slot_index" value="' . absint( $index ) . '">'; self::searchable_passenger_dropdown( 'passenger_ids', $slot_passengers, $passengers, 'Mitfahrer auswählen und suchen' ); echo '<button type="submit">Mitfahrer speichern</button></form>'; } echo '</article>'; }
		self::institution_calendar_script();
		wp_reset_postdata(); echo '</div>'; $output = ob_get_clean(); $output = preg_replace_callback( '/\\b(\\d{4})-(\\d{2})-(\\d{2})\\b/', static function ( $matches ) { return '<strong>Datum:</strong> ' . esc_html( $matches[3] . '.' . $matches[2] . '.' . $matches[1] ); }, $output ); return $output;
	}

	public static function admin_notice() {
		if ( isset( $_GET['mfz_status'] ) && current_user_can( 'edit_posts' ) ) {
			printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['mfz_status'] ) ) ) );
		}
	}

	public static function shortcode() {
		if ( ! self::can_view( 'driver' ) ) {
			return '<p>Für diese Fahreransicht ist keine Berechtigung vorhanden.</p>';
		}
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => self::META_DATE, 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ) ), 'meta_key' => self::META_DATE, 'orderby' => 'meta_value', 'order' => 'ASC' ) );
		ob_start();
		echo '<div class="mfz-events"><div class="mfz-driver-calendar" aria-label="Kalender für geplante Termine"><div class="mfz-calendar-header"><button type="button" class="mfz-calendar-prev" aria-label="Vorheriger Monat">‹</button><strong class="mfz-calendar-month"></strong><button type="button" class="mfz-calendar-next" aria-label="Nächster Monat">›</button></div><div class="mfz-calendar-weekdays"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div><div class="mfz-calendar-days"></div></div>';
		if ( ! $query->have_posts() ) { echo '<p>Aktuell sind keine Termine verfügbar.</p>'; }
		while ( $query->have_posts() ) { $query->the_post(); $id = get_the_ID(); $slots = get_post_meta( $id, self::META_SLOTS, true ); $location_id = absint( get_post_meta( $id, self::META_LOCATION, true ) ); $item_ids = array_unique( array_filter( array_map( 'absint', array_column( (array) $slots, 'item_id' ) ) ) );
			$street = $location_id ? get_post_meta( $location_id, self::META_INSTITUTION_STREET, true ) : '';
			$postcode_city = $location_id ? trim( get_post_meta( $location_id, self::META_INSTITUTION_POSTCODE, true ) . ' ' . get_post_meta( $location_id, self::META_INSTITUTION_CITY, true ) ) : '';
			 echo '<article class="mfz-event"><h3>' . esc_html( get_the_title() ) . '</h3><p><strong>Datum:</strong> ' . esc_html( get_post_meta( $id, self::META_DATE, true ) ) . '</p><p><strong>Standort:</strong> ' . esc_html( $location_id ? get_the_title( $location_id ) : 'nicht festgelegt' ) . '<br><strong>Adresse:</strong> ' . esc_html( $street ?: ( $location_id ? self::institution_address( $location_id ) : 'nicht hinterlegt' ) ) . ( $postcode_city ? '<br>' . esc_html( $postcode_city ) : '' ) . '</p>';
			if ( $item_ids ) { echo '<p><strong>Fahrräder:</strong> ' . esc_html( implode( ', ', array_filter( array_map( 'get_the_title', $item_ids ) ) ) ) . '</p>'; }
			$current_user_id = get_current_user_id(); $current_user_location = absint( get_user_meta( $current_user_id, '_mfz_location_id', true ) );
			$slot_number = 1; foreach ( (array) $slots as $index => $slot ) { $driver_id = absint( $slot['driver_id'] ?? 0 ); $mine = $driver_id === $current_user_id; $item_id = absint( $slot['item_id'] ?? 0 ); $capacity = absint( $slot['passenger_count'] ?? 0 ); $passenger_ids = array_map( 'absint', (array) ( $slot['passenger_ids'] ?? array() ) ); $passenger_count = count( array_filter( $passenger_ids ) ); $complete = $driver_id > 0 && $passenger_count >= $capacity; $slot_is_past = self::is_past_slot( $id, $slot ); echo '<div class="mfz-slot"><h4 style="margin-top:0">Slot ' . $slot_number++ . ( $complete ? ' <span style="color:#168a35;font-size:1.3em;font-weight:bold" title="Fahrer und Mitfahrer vollständig">✓</span>' : '' ) . '</h4><strong>Start:</strong> ' . esc_html( $slot['start'] ?? '' ) . ' · <strong>Ende:</strong> ' . esc_html( $slot['end'] ?? '' ); if ( $item_id ) { echo '<br><strong>Fahrrad:</strong> ' . esc_html( get_the_title( $item_id ) ) . self::item_image( $item_id ); } echo '<br><strong>Mitfahrer:</strong> ' . $passenger_count . '/' . $capacity . '<br><strong>Fahrer:</strong> ' . esc_html( $driver_id ? get_the_author_meta( 'display_name', $driver_id ) : 'noch frei' ); if ( is_user_logged_in() && ( $mine || ! $driver_id ) ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-left:10px">'; wp_nonce_field( 'mfz_slot_action', 'mfz_action_nonce' ); echo '<input type="hidden" name="action" value="' . ( $mine ? 'mfz_withdraw' : 'mfz_signup' ) . '"><input type="hidden" name="event_id" value="' . $id . '"><input type="hidden" name="slot_index" value="' . $index . '"><button type="submit"' . ( $slot_is_past ? ' disabled title="Dieser Zeitslot ist bereits vergangen."' : '' ) . '>' . ( $mine ? 'Als Fahrer austragen' : 'Als Fahrer eintragen' ) . '</button></form>'; } echo '</div>'; }
			echo '</article>'; }
		wp_reset_postdata(); echo '</div><script>(function(){var calendar=document.querySelector(".mfz-driver-calendar");if(!calendar)return;var events=Array.from(document.querySelectorAll(".mfz-events > article.mfz-event")),planned={};events.forEach(function(article){var match=article.textContent.match(/\\d{4}-\\d{2}-\\d{2}/);if(match){article.dataset.mfzDate=match[0];planned[match[0]]=true;}});var requested=new URLSearchParams(window.location.search).get("mfz_date"),selected=requested&&planned[requested]?requested:(events.length?events[0].dataset.mfzDate:null),current=new Date(selected?selected+"T12:00:00":new Date());function pad(number){return String(number).padStart(2,"0");}function dateKey(date){return date.getFullYear()+"-"+pad(date.getMonth()+1)+"-"+pad(date.getDate());}function selectDay(date){selected=date;events.forEach(function(article){article.style.display=article.dataset.mfzDate===date?"block":"none";});render();}function render(){var year=current.getFullYear(),month=current.getMonth(),first=new Date(year,month,1),last=new Date(year,month+1,0),offset=(first.getDay()+6)%7,days=calendar.querySelector(".mfz-calendar-days"),today=dateKey(new Date());calendar.querySelector(".mfz-calendar-month").textContent=current.toLocaleDateString("de-DE",{month:"long",year:"numeric"});days.innerHTML="";for(var i=0;i<offset;i++)days.insertAdjacentHTML("beforeend","<span class=\"mfz-calendar-empty\"></span>");for(var day=1;day<=last.getDate();day++){var key=dateKey(new Date(year,month,day)),button=document.createElement("button");button.type="button";button.textContent=day;button.dataset.date=key;if(key<today)button.classList.add("mfz-past-day");if(planned[key])button.classList.add("mfz-planned-day");if(planned[key]&&key<today)button.classList.add("mfz-past-planned-day");if(key===selected)button.classList.add("mfz-selected-day");days.appendChild(button);}}calendar.addEventListener("click",function(event){if(event.target.classList.contains("mfz-calendar-prev")){current.setMonth(current.getMonth()-1);render();}if(event.target.classList.contains("mfz-calendar-next")){current.setMonth(current.getMonth()+1);render();}if(event.target.dataset.date&&planned[event.target.dataset.date])selectDay(event.target.dataset.date);});document.addEventListener("submit",function(event){var form=event.target;if(!form.querySelector("input[name=\\"action\\"][value=\\"mfz_signup\\"]")&&!form.querySelector("input[name=\\"action\\"][value=\\"mfz_withdraw\\"]"))return;var field=form.querySelector("input[name=\\"mfz_return_date\\"]");if(!field){field=document.createElement("input");field.type="hidden";field.name="mfz_return_date";form.appendChild(field);}field.value=selected||"";});if(selected)selectDay(selected);else render();}());</script>'; return ob_get_clean();
	}
}

MFZ_Mitfahrzentrale::init();
