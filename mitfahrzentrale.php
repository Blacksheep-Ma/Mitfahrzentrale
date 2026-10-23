<?php
/**
 * Plugin Name: Mitfahrzentrale Kalender
 * Description: Verwaltet Fahrten-Slots, Fahrzeuge und bestätigungspflichtige Fahrer- und Mitfahreranmeldungen.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mitfahrzentrale-kalender
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MZK_Mitfahrzentrale_Kalender
{
    const SLOT = 'mzk_slot';
    const VEHICLE = 'mzk_vehicle';
    const BOOKING = 'mzk_booking';

    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_post_types'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta'), 10, 2);
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        add_shortcode('mitfahrzentrale_kalender', array(__CLASS__, 'render_calendar'));
        add_shortcode('mitfahrzentrale_slot', array(__CLASS__, 'render_single_slot'));
        add_shortcode('mitfahrzentrale_terminverwaltung', array(__CLASS__, 'render_admin_shortcode'));
        add_action('admin_post_mzk_book', array(__CLASS__, 'submit_booking'));
        add_action('admin_post_mzk_create_appointment', array(__CLASS__, 'create_appointment'));
    }

    public static function register_post_types()
    {
        register_post_type(self::SLOT, array(
            'labels' => array(
                'name' => 'Fahrten-Slots',
                'singular_name' => 'Fahrten-Slot',
                'add_new_item' => 'Fahrten-Slot hinzufügen',
                'edit_item' => 'Fahrten-Slot bearbeiten',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-calendar-alt',
            'capabilities' => self::admin_capabilities(),
            'map_meta_cap' => true,
            'supports' => array('title', 'thumbnail'),
        ));

        register_post_type(self::VEHICLE, array(
            'labels' => array(
                'name' => 'Fahrzeuge',
                'singular_name' => 'Fahrzeug',
                'add_new_item' => 'Fahrzeug hinzufügen',
                'edit_item' => 'Fahrzeug bearbeiten',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capabilities' => self::admin_capabilities(),
            'map_meta_cap' => true,
            'supports' => array('title'),
        ));

        register_post_type(self::BOOKING, array(
            'labels' => array(
                'name' => 'Anmeldungen',
                'singular_name' => 'Anmeldung',
                'edit_item' => 'Anmeldung prüfen',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capabilities' => self::admin_capabilities(),
            'map_meta_cap' => true,
            'supports' => array('title'),
        ));
    }

    public static function render_admin_page($check_permissions = true)
    {
        if ($check_permissions && !current_user_can('manage_options')) {
            wp_die('Du bist nicht berechtigt, Termine zu erstellen.');
        }

        $vehicles = get_posts(array(
            'post_type' => self::VEHICLE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        $message = isset($_GET['mzk_admin_message']) ? sanitize_key(wp_unslash($_GET['mzk_admin_message'])) : '';
        $return_url = get_permalink();
        ?>
        <div class="wrap">
            <h1>Termin und Slots erstellen</h1>
            <?php if ($message === 'created') : ?>
                <div class="notice notice-success is-dismissible"><p>Termin und Slots wurden erstellt.</p></div>
            <?php elseif ($message === 'error') : ?>
                <div class="notice notice-error"><p>Der Termin konnte nicht erstellt werden. Bitte prüfe die Angaben und versuche es erneut.</p></div>
            <?php endif; ?>
            <p>Erstelle einen Termin mit gemeinsamer Strecke und Datum. Jede Abfahrtszeit wird als eigener buchbarer Slot angelegt.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mzk_create_appointment">
                <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">
                <?php wp_nonce_field('mzk_create_appointment', 'mzk_appointment_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mzk_appointment_title">Terminname</label></th>
                        <td><input name="appointment_title" id="mzk_appointment_title" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mzk_appointment_date">Datum</label></th>
                        <td><input name="appointment_date" id="mzk_appointment_date" type="date" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mzk_appointment_origin">Start</label></th>
                        <td><input name="appointment_origin" id="mzk_appointment_origin" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mzk_appointment_destination">Ziel</label></th>
                        <td><input name="appointment_destination" id="mzk_appointment_destination" type="text" class="regular-text" required></td>
                    </tr>
                </table>
                <h2>Abfahrts-Slots</h2>
                <p>Jeder Slot erhält eine eigene Abfahrtszeit, Bezeichnung und Mitfahrerkapazität.</p>
                <div id="mzk-slots">
                    <?php self::render_admin_slot_row(0, $vehicles); ?>
                </div>
                <p><button type="button" class="button" id="mzk-add-slot">Weiteren Slot hinzufügen</button></p>
                <p class="submit"><button type="submit" class="button button-primary">Termin und Slots erstellen</button></p>
            </form>
            <template id="mzk-slot-template"><?php self::render_admin_slot_row('__INDEX__', $vehicles); ?></template>
        </div>
        <script>
        (function () {
            var slots = document.getElementById('mzk-slots');
            var template = document.getElementById('mzk-slot-template');
            var addButton = document.getElementById('mzk-add-slot');
            var nextIndex = 1;
            addButton.addEventListener('click', function () {
                slots.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex)));
                nextIndex++;
            });
            slots.addEventListener('click', function (event) {
                if (event.target.classList.contains('mzk-remove-slot')) {
                    event.target.closest('.mzk-slot-row').remove();
                }
            });
        }());
        </script>
        <?php
    }

    public static function render_admin_shortcode()
    {
        // if (!current_user_can('manage_options')) {
        //     return '<p role="alert">Die Terminverwaltung ist nur für Administratoren verfügbar.</p>';
        // }

        ob_start();
        self::render_admin_page(false);
        return '<div class="mzk-admin-form">' . ob_get_clean() . '</div>';
    }

    private static function render_admin_slot_row($index, $vehicles)
    {
        ?>
        <fieldset class="mzk-slot-row" style="border:1px solid #c3c4c7;padding:12px;margin:12px 0;max-width:900px">
            <legend>Abfahrts-Slot</legend>
            <p><label>Bezeichnung<br>
                <input type="text" name="slot_label[<?php echo esc_attr($index); ?>]" class="regular-text" placeholder="z. B. Frühfahrt" required></label></p>
            <p><label>Abfahrtszeit<br>
                <input type="time" name="slot_time[<?php echo esc_attr($index); ?>]" required></label></p>
            <p><label>Mitfahrerplätze<br>
                <input type="number" name="slot_capacity[<?php echo esc_attr($index); ?>]" min="0" step="1" value="0" required></label></p>
            <p><label>Fahrzeug (optional)<br>
                <select name="slot_vehicle[<?php echo esc_attr($index); ?>]">
                    <option value="0">Kein Fahrzeug zugeordnet</option>
                    <?php foreach ($vehicles as $vehicle) : ?>
                        <option value="<?php echo esc_attr($vehicle->ID); ?>"><?php echo esc_html($vehicle->post_title); ?></option>
                    <?php endforeach; ?>
                </select></label></p>
            <button type="button" class="button-link-delete mzk-remove-slot">Diesen Slot entfernen</button>
        </fieldset>
        <?php
    }

    public static function create_appointment()
    {
        if (!current_user_can('manage_options') || !isset($_POST['mzk_appointment_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mzk_appointment_nonce'])), 'mzk_create_appointment')) {
            wp_die('Die Anfrage konnte nicht verifiziert werden.');
        }

        $return_url = isset($_POST['return_url'])
            ? esc_url_raw(wp_unslash($_POST['return_url']))
            : '';
        $return_url = wp_validate_redirect(
            $return_url,
            home_url('/')
        );

        $title = isset($_POST['appointment_title']) ? sanitize_text_field(wp_unslash($_POST['appointment_title'])) : '';
        $date = isset($_POST['appointment_date']) ? sanitize_text_field(wp_unslash($_POST['appointment_date'])) : '';
        $origin = isset($_POST['appointment_origin']) ? sanitize_text_field(wp_unslash($_POST['appointment_origin'])) : '';
        $destination = isset($_POST['appointment_destination']) ? sanitize_text_field(wp_unslash($_POST['appointment_destination'])) : '';
        $labels = isset($_POST['slot_label']) && is_array($_POST['slot_label']) ? wp_unslash($_POST['slot_label']) : array();
        $times = isset($_POST['slot_time']) && is_array($_POST['slot_time']) ? wp_unslash($_POST['slot_time']) : array();
        $capacities = isset($_POST['slot_capacity']) && is_array($_POST['slot_capacity']) ? wp_unslash($_POST['slot_capacity']) : array();
        $vehicle_ids = isset($_POST['slot_vehicle']) && is_array($_POST['slot_vehicle']) ? wp_unslash($_POST['slot_vehicle']) : array();

        $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $date_errors = DateTimeImmutable::getLastErrors();
        if (!$title || !$origin || !$destination || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$date_object ||
            ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) || !$labels) {
            self::redirect_admin_page('error', $return_url);
        }

        $slot_data = array();
        foreach ($labels as $index => $label) {
            $label = sanitize_text_field($label);
            $time = isset($times[$index]) ? sanitize_text_field($times[$index]) : '';
            $capacity = isset($capacities[$index]) ? $capacities[$index] : '';
            $vehicle_id = isset($vehicle_ids[$index]) ? absint($vehicle_ids[$index]) : 0;
            $time_object = DateTimeImmutable::createFromFormat('!H:i', $time, wp_timezone());
            $time_errors = DateTimeImmutable::getLastErrors();
            if (!$label || !preg_match('/^\d{2}:\d{2}$/', $time) || !$time_object ||
                ($time_errors && ($time_errors['warning_count'] || $time_errors['error_count'])) ||
                !preg_match('/^\d+$/', (string) $capacity) ||
                ($vehicle_id && (get_post_type($vehicle_id) !== self::VEHICLE || get_post_status($vehicle_id) !== 'publish'))) {
                self::redirect_admin_page('error', $return_url);
            }
            $slot_data[] = array(
                'title' => $title . ' – ' . $label,
                'start' => $date . 'T' . $time,
                'capacity' => absint($capacity),
                'vehicle_id' => $vehicle_id,
            );
        }

        $created_ids = array();
        foreach ($slot_data as $slot) {
            $slot_id = wp_insert_post(array(
                'post_type' => self::SLOT,
                'post_status' => 'publish',
                'post_title' => $slot['title'],
            ), true);
            if (is_wp_error($slot_id)) {
                foreach ($created_ids as $created_id) {
                    wp_delete_post($created_id, true);
                }
                self::redirect_admin_page('error', $return_url);
            }
            $created_ids[] = $slot_id;
            update_post_meta($slot_id, '_mzk_start', $slot['start']);
            update_post_meta($slot_id, '_mzk_origin', $origin);
            update_post_meta($slot_id, '_mzk_destination', $destination);
            update_post_meta($slot_id, '_mzk_vehicle_id', $slot['vehicle_id']);
            update_post_meta($slot_id, '_mzk_passenger_seats', $slot['capacity']);
        }

        self::redirect_admin_page('created', $return_url);
    }

    private static function redirect_admin_page($message, $return_url)
    {
        $return_url = remove_query_arg('mzk_admin_message', $return_url);
        wp_safe_redirect(add_query_arg('mzk_admin_message', $message, $return_url));
        exit;
    }

    private static function admin_capabilities()
    {
        $capabilities = array(
            'edit_post',
            'read_post',
            'delete_post',
            'edit_posts',
            'edit_others_posts',
            'publish_posts',
            'read_private_posts',
            'delete_posts',
            'delete_private_posts',
            'delete_published_posts',
            'delete_others_posts',
            'edit_private_posts',
            'edit_published_posts',
            'create_posts',
        );
        $mapped = array();
        foreach ($capabilities as $capability) {
            $mapped[$capability] = 'manage_options';
        }
        return $mapped;
    }

    public static function add_meta_boxes()
    {
        add_meta_box('mzk_slot_details', 'Fahrten-Details', array(__CLASS__, 'slot_meta_box'), self::SLOT);
        add_meta_box('mzk_vehicle_details', 'Fahrzeug-Details', array(__CLASS__, 'vehicle_meta_box'), self::VEHICLE);
        add_meta_box('mzk_booking_details', 'Anmeldungsdetails', array(__CLASS__, 'booking_meta_box'), self::BOOKING);
    }

    public static function slot_meta_box($post)
    {
        wp_nonce_field('mzk_save_meta', 'mzk_meta_nonce');
        $start = get_post_meta($post->ID, '_mzk_start', true);
        $origin = get_post_meta($post->ID, '_mzk_origin', true);
        $destination = get_post_meta($post->ID, '_mzk_destination', true);
        $vehicle_id = (int) get_post_meta($post->ID, '_mzk_vehicle_id', true);
        $passenger_seats = get_post_meta($post->ID, '_mzk_passenger_seats', true);
        $vehicles = get_posts(array(
            'post_type' => self::VEHICLE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        ?>
        <p><label for="mzk_start">Datum und Uhrzeit</label><br>
            <input type="datetime-local" id="mzk_start" name="mzk_start" value="<?php echo esc_attr($start); ?>"></p>
        <p><label for="mzk_origin">Start</label><br>
            <input type="text" class="widefat" id="mzk_origin" name="mzk_origin" value="<?php echo esc_attr($origin); ?>"></p>
        <p><label for="mzk_destination">Ziel</label><br>
            <input type="text" class="widefat" id="mzk_destination" name="mzk_destination" value="<?php echo esc_attr($destination); ?>"></p>
        <p><label for="mzk_vehicle_id">Fahrzeug</label><br>
            <select id="mzk_vehicle_id" name="mzk_vehicle_id">
                <option value="0">Kein Fahrzeug zugeordnet</option>
                <?php foreach ($vehicles as $vehicle) : ?>
                    <option value="<?php echo esc_attr($vehicle->ID); ?>" <?php selected($vehicle_id, $vehicle->ID); ?>>
                        <?php echo esc_html($vehicle->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select></p>
        <p><label for="mzk_passenger_seats">Mitfahrerplätze für diesen Slot</label><br>
            <input type="number" min="0" step="1" id="mzk_passenger_seats" name="mzk_passenger_seats"
                value="<?php echo esc_attr($passenger_seats); ?>">
            <span class="description">Leer lassen, um die Kapazität des Fahrzeugs zu verwenden.</span></p>
        <?php
    }

    public static function vehicle_meta_box($post)
    {
        wp_nonce_field('mzk_save_meta', 'mzk_meta_nonce');
        $seats = get_post_meta($post->ID, '_mzk_passenger_seats', true);
        ?>
        <p><label for="mzk_passenger_seats">Verfügbare Mitfahrerplätze</label><br>
            <input type="number" min="1" step="1" id="mzk_passenger_seats" name="mzk_passenger_seats"
                value="<?php echo esc_attr($seats); ?>" required></p>
        <?php
    }

    public static function booking_meta_box($post)
    {
        $slot_id = (int) get_post_meta($post->ID, '_mzk_slot_id', true);
        $user_id = (int) get_post_meta($post->ID, '_mzk_user_id', true);
        $type = get_post_meta($post->ID, '_mzk_type', true);
        $seats = (int) get_post_meta($post->ID, '_mzk_seats', true);
        $status = get_post_meta($post->ID, '_mzk_status', true);
        $user = get_user_by('id', $user_id);
        wp_nonce_field('mzk_save_meta', 'mzk_meta_nonce');
        ?>
        <p><strong>Slot:</strong> <?php echo esc_html(get_the_title($slot_id)); ?></p>
        <p><strong>Benutzer:</strong> <?php echo esc_html($user ? $user->display_name : 'Unbekannt'); ?></p>
        <p><strong>Rolle:</strong> <?php echo esc_html($type === 'driver' ? 'Fahrer' : 'Mitfahrer'); ?></p>
        <?php if ($type === 'passenger') : ?>
            <p><strong>Angefragte Plätze:</strong> <?php echo esc_html($seats); ?></p>
        <?php endif; ?>
        <p><label for="mzk_status">Entscheidung</label><br>
            <select id="mzk_status" name="mzk_status">
                <option value="pending" <?php selected($status, 'pending'); ?>>Ausstehend</option>
                <option value="approved" <?php selected($status, 'approved'); ?>>Bestätigt</option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>>Abgelehnt</option>
            </select></p>
        <?php
    }

    public static function save_meta($post_id, $post)
    {
        if (!isset($_POST['mzk_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mzk_meta_nonce'])), 'mzk_save_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type === self::SLOT) {
            $start = isset($_POST['mzk_start']) ? sanitize_text_field(wp_unslash($_POST['mzk_start'])) : '';
            $origin = isset($_POST['mzk_origin']) ? sanitize_text_field(wp_unslash($_POST['mzk_origin'])) : '';
            $destination = isset($_POST['mzk_destination']) ? sanitize_text_field(wp_unslash($_POST['mzk_destination'])) : '';
            $vehicle_id = isset($_POST['mzk_vehicle_id']) ? absint($_POST['mzk_vehicle_id']) : 0;
            $passenger_seats = isset($_POST['mzk_passenger_seats']) ? sanitize_text_field(wp_unslash($_POST['mzk_passenger_seats'])) : '';
            self::update_or_delete_meta($post_id, '_mzk_start', $start);
            self::update_or_delete_meta($post_id, '_mzk_origin', $origin);
            self::update_or_delete_meta($post_id, '_mzk_destination', $destination);
            update_post_meta($post_id, '_mzk_vehicle_id', $vehicle_id);
            if ($passenger_seats === '') {
                delete_post_meta($post_id, '_mzk_passenger_seats');
            } else {
                update_post_meta($post_id, '_mzk_passenger_seats', absint($passenger_seats));
            }
        } elseif ($post->post_type === self::VEHICLE) {
            $seats = isset($_POST['mzk_passenger_seats']) ? absint($_POST['mzk_passenger_seats']) : 0;
            update_post_meta($post_id, '_mzk_passenger_seats', $seats);
        } elseif ($post->post_type === self::BOOKING && current_user_can('manage_options')) {
            $status = isset($_POST['mzk_status']) ? sanitize_key(wp_unslash($_POST['mzk_status'])) : '';
            if (in_array($status, array('pending', 'approved', 'rejected'), true)) {
                if ($status !== 'approved') {
                    update_post_meta($post_id, '_mzk_status', $status);
                } elseif (self::can_approve_booking($post_id)) {
                    update_post_meta($post_id, '_mzk_status', $status);
                } else {
                    set_transient('mzk_approval_error_' . get_current_user_id(), true, MINUTE_IN_SECONDS);
                }
            }
        }
    }

    public static function admin_notices()
    {
        $key = 'mzk_approval_error_' . get_current_user_id();
        if (!get_transient($key)) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error"><p>Diese Anmeldung kann noch nicht bestätigt werden. Prüfe, ob bereits ein Fahrer bestätigt ist und ob für Mitfahrer ein bestätigter Fahrer sowie genügend Fahrzeugplätze vorhanden sind.</p></div>';
    }

    private static function update_or_delete_meta($post_id, $key, $value)
    {
        if ($value === '') {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }

    public static function render_calendar()
    {
        $is_admin = current_user_can('manage_options');
        $filter = isset($_GET['mzk_date']) ? sanitize_text_field(wp_unslash($_GET['mzk_date'])) : '';
        $args = array(
            'post_type' => self::SLOT,
            'post_status' => self::visible_slot_statuses($is_admin),
            'posts_per_page' => 100,
            'meta_key' => '_mzk_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        );
        $meta_query = array();
        if (!$is_admin) {
            $meta_query[] = array(
                'key' => '_mzk_start',
                'value' => current_time('Y-m-d\TH:i'),
                'compare' => '>=',
                'type' => 'CHAR',
            );
        }
        if ($filter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter)) {
            $meta_query[] = array(
                'key' => '_mzk_start',
                'value' => array($filter . 'T00:00', $filter . 'T23:59'),
                'compare' => 'BETWEEN',
                'type' => 'CHAR',
            );
        }
        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }
        $slots = get_posts($args);
        $message = isset($_GET['mzk_message']) ? sanitize_key(wp_unslash($_GET['mzk_message'])) : '';
        ob_start();
        ?>
        <section class="mzk-calendar">
            <?php if ($message === 'requested') : ?>
                <p role="status">Deine Anmeldung wurde eingereicht und wartet auf Bestätigung.</p>
            <?php elseif ($message === 'error') : ?>
                <p role="alert">Die Anmeldung konnte nicht verarbeitet werden. Bitte prüfe deine Angaben und versuche es erneut.</p>
            <?php endif; ?>
            <form method="get" class="mzk-filter">
                <label>Datum filtern
                    <input type="date" name="mzk_date" value="<?php echo esc_attr($filter); ?>">
                </label>
                <button type="submit">Filtern</button>
                <?php if ($filter) : ?><a href="<?php echo esc_url(remove_query_arg('mzk_date')); ?>">Filter zurücksetzen</a><?php endif; ?>
            </form>
            <?php if (!$slots) : ?>
                <p>Für diesen Zeitraum sind keine Fahrten-Slots verfügbar.</p>
            <?php else : ?>
                <?php foreach ($slots as $slot) : ?>
                    <?php self::render_slot($slot, $slot->post_status === 'publish'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_single_slot($attributes)
    {
        $attributes = shortcode_atts(array('id' => 0), $attributes, 'mitfahrzentrale_slot');
        $slot_id = absint($attributes['id']);
        $slot = $slot_id ? get_post($slot_id) : null;

        $is_admin = current_user_can('manage_options');
        if (!$slot || $slot->post_type !== self::SLOT ||
            !in_array($slot->post_status, self::visible_slot_statuses($is_admin), true)) {
            return '<p class="mzk-message">Der angegebene Fahrten-Slot wurde nicht gefunden.</p>';
        }

        $message = isset($_GET['mzk_message']) ? sanitize_key(wp_unslash($_GET['mzk_message'])) : '';
        ob_start();
        ?>
        <section class="mzk-single-slot">
            <?php if ($message === 'requested') : ?>
                <p role="status">Deine Anmeldung wurde eingereicht und wartet auf Bestätigung.</p>
            <?php elseif ($message === 'error') : ?>
                <p role="alert">Die Anmeldung konnte nicht verarbeitet werden. Bitte prüfe deine Angaben und versuche es erneut.</p>
            <?php endif; ?>
            <?php self::render_slot($slot, $slot->post_status === 'publish'); ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_slot($slot, $allow_booking = true)
    {
        $start = get_post_meta($slot->ID, '_mzk_start', true);
        $origin = get_post_meta($slot->ID, '_mzk_origin', true);
        $destination = get_post_meta($slot->ID, '_mzk_destination', true);
        $vehicle_id = (int) get_post_meta($slot->ID, '_mzk_vehicle_id', true);
        $capacity = self::passenger_capacity($slot->ID);
        $approved_seats = self::approved_passenger_seats($slot->ID);
        $remaining = max(0, $capacity - $approved_seats);
        $driver = self::approved_driver($slot->ID);
        ?>
        <article class="mzk-slot">
            <h3><?php echo esc_html(get_the_title($slot)); ?></h3>
            <?php if (has_post_thumbnail($slot->ID)) : ?>
                <div class="mzk-slot-image">
                    <?php echo get_the_post_thumbnail($slot->ID, 'medium', array('loading' => 'lazy')); ?>
                </div>
            <?php endif; ?>
            <p><?php echo esc_html(self::format_date($start)); ?>
                <?php if ($origin || $destination) : ?>
                    &mdash; <?php echo esc_html($origin); ?> nach <?php echo esc_html($destination); ?>
                <?php endif; ?>
            </p>
            <?php if ($driver) : ?>
                <p>Ein Fahrer ist bestätigt.</p>
            <?php else : ?>
                <p>Noch kein Fahrer bestätigt.</p>
            <?php endif; ?>
            <?php if ($vehicle_id || $capacity > 0) : ?>
                <p>Freie Mitfahrerplätze: <?php echo esc_html($remaining); ?></p>
            <?php endif; ?>
            <?php if (!is_user_logged_in()) : ?>
                <p>Bitte melde dich an, um dich für eine Fahrt einzutragen.</p>
            <?php elseif (!$allow_booking) : ?>
                <p>Vorschau: Für nicht veröffentlichte Slots sind keine Anmeldungen möglich.</p>
            <?php else : ?>
                <?php if (!$driver) : ?>
                    <?php self::render_booking_form($slot->ID, 'driver', 'Als Fahrer anmelden'); ?>
                <?php endif; ?>
                <?php if ($driver && $capacity > 0 && $remaining > 0) : ?>
                    <?php self::render_booking_form($slot->ID, 'passenger', 'Als Mitfahrer anmelden', $remaining); ?>
                <?php elseif ($driver && $capacity < 1) : ?>
                    <p>Für diesen Slot sind keine Mitfahrerplätze konfiguriert.</p>
                <?php elseif ($driver && $remaining < 1) : ?>
                    <p>Für diese Fahrt sind derzeit keine Plätze frei.</p>
                <?php endif; ?>
            <?php endif; ?>
        </article>
        <?php
    }

    private static function visible_slot_statuses($is_admin)
    {
        if (!$is_admin) {
            return array('publish');
        }

        return array_values(array_diff(array_keys(get_post_stati()), array('trash', 'auto-draft')));
    }

    private static function render_booking_form($slot_id, $type, $label, $max_seats = 1)
    {
        $existing = self::user_booking($slot_id, get_current_user_id(), $type);
        if ($existing) {
            $status = get_post_meta($existing, '_mzk_status', true);
            $status_text = $status === 'approved' ? 'bestätigt' : ($status === 'rejected' ? 'abgelehnt' : 'wartet auf Bestätigung');
            echo '<p>Deine Anmeldung (' . esc_html($type === 'driver' ? 'Fahrer' : 'Mitfahrer') . ') ' . esc_html($status_text) . '.</p>';
            return;
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mzk-booking-form">
            <input type="hidden" name="action" value="mzk_book">
            <input type="hidden" name="slot_id" value="<?php echo esc_attr($slot_id); ?>">
            <input type="hidden" name="booking_type" value="<?php echo esc_attr($type); ?>">
            <?php wp_nonce_field('mzk_book_' . $slot_id, 'mzk_booking_nonce'); ?>
            <?php if ($type === 'passenger') : ?>
                <label>Plätze
                    <input type="number" name="seats" min="1" max="<?php echo esc_attr($max_seats); ?>" value="1" required>
                </label>
            <?php endif; ?>
            <button type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    public static function submit_booking()
    {
        $slot_id = isset($_POST['slot_id']) ? absint($_POST['slot_id']) : 0;
        if (!is_user_logged_in() || !$slot_id || get_post_type($slot_id) !== self::SLOT || !isset($_POST['mzk_booking_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mzk_booking_nonce'])), 'mzk_book_' . $slot_id)) {
            self::redirect_with_message('error');
        }

        $type = isset($_POST['booking_type']) ? sanitize_key(wp_unslash($_POST['booking_type'])) : '';
        if (!in_array($type, array('driver', 'passenger'), true) || get_post_status($slot_id) !== 'publish') {
            self::redirect_with_message('error');
        }

        $start = get_post_meta($slot_id, '_mzk_start', true);
        if (!$start || $start < current_time('Y-m-d\TH:i') || self::user_booking($slot_id, get_current_user_id(), $type)) {
            self::redirect_with_message('error');
        }

        $seats = $type === 'passenger' && isset($_POST['seats']) ? absint($_POST['seats']) : 1;
        if ($type === 'passenger') {
            $vehicle_id = (int) get_post_meta($slot_id, '_mzk_vehicle_id', true);
            $capacity = self::passenger_capacity($slot_id);
            $remaining = $capacity - self::approved_passenger_seats($slot_id);
            if (!self::approved_driver($slot_id) || $seats < 1 || $seats > $remaining) {
                self::redirect_with_message('error');
            }
        } elseif (self::approved_driver($slot_id)) {
            self::redirect_with_message('error');
        }

        $user = wp_get_current_user();
        $booking_id = wp_insert_post(array(
            'post_type' => self::BOOKING,
            'post_status' => 'private',
            'post_title' => sprintf('%s – %s – %s', $user->display_name, $type === 'driver' ? 'Fahrer' : 'Mitfahrer', get_the_title($slot_id)),
        ), true);
        if (is_wp_error($booking_id)) {
            self::redirect_with_message('error');
        }
        update_post_meta($booking_id, '_mzk_slot_id', $slot_id);
        update_post_meta($booking_id, '_mzk_user_id', get_current_user_id());
        update_post_meta($booking_id, '_mzk_type', $type);
        update_post_meta($booking_id, '_mzk_seats', $seats);
        update_post_meta($booking_id, '_mzk_status', 'pending');
        self::redirect_with_message('requested');
    }

    private static function can_approve_booking($booking_id)
    {
        $slot_id = (int) get_post_meta($booking_id, '_mzk_slot_id', true);
        $type = get_post_meta($booking_id, '_mzk_type', true);
        if (!$slot_id || get_post_type($slot_id) !== self::SLOT) {
            return false;
        }
        if ($type === 'driver') {
            $approved_driver = self::approved_driver($slot_id);
            return !$approved_driver || (int) $approved_driver->ID === (int) get_post_meta($booking_id, '_mzk_user_id', true);
        }
        if ($type === 'passenger') {
            $vehicle_id = (int) get_post_meta($slot_id, '_mzk_vehicle_id', true);
            $capacity = self::passenger_capacity($slot_id);
            $seats = (int) get_post_meta($booking_id, '_mzk_seats', true);
            return self::approved_driver($slot_id) && $seats > 0 &&
                self::approved_passenger_seats($slot_id, $booking_id) + $seats <= $capacity;
        }
        return false;
    }

    private static function user_booking($slot_id, $user_id, $type)
    {
        $posts = get_posts(array(
            'post_type' => self::BOOKING,
            'post_status' => array('private', 'publish', 'pending'),
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array('key' => '_mzk_slot_id', 'value' => $slot_id),
                array('key' => '_mzk_user_id', 'value' => $user_id),
                array('key' => '_mzk_type', 'value' => $type),
                array('key' => '_mzk_status', 'value' => array('pending', 'approved'), 'compare' => 'IN'),
            ),
        ));
        return $posts ? (int) $posts[0] : 0;
    }

    private static function approved_driver($slot_id)
    {
        $posts = get_posts(array(
            'post_type' => self::BOOKING,
            'post_status' => array('private', 'publish', 'pending'),
            'numberposts' => 1,
            'meta_query' => array(
                array('key' => '_mzk_slot_id', 'value' => $slot_id),
                array('key' => '_mzk_type', 'value' => 'driver'),
                array('key' => '_mzk_status', 'value' => 'approved'),
            ),
        ));
        return $posts ? get_user_by('id', (int) get_post_meta($posts[0]->ID, '_mzk_user_id', true)) : false;
    }

    private static function approved_passenger_seats($slot_id, $exclude_booking_id = 0)
    {
        $posts = get_posts(array(
            'post_type' => self::BOOKING,
            'post_status' => array('private', 'publish', 'pending'),
            'numberposts' => -1,
            'meta_query' => array(
                array('key' => '_mzk_slot_id', 'value' => $slot_id),
                array('key' => '_mzk_type', 'value' => 'passenger'),
                array('key' => '_mzk_status', 'value' => 'approved'),
            ),
        ));
        $total = 0;
        foreach ($posts as $booking) {
            if ((int) $booking->ID !== (int) $exclude_booking_id) {
                $total += (int) get_post_meta($booking->ID, '_mzk_seats', true);
            }
        }
        return $total;
    }

    private static function passenger_capacity($slot_id)
    {
        $slot_capacity = get_post_meta($slot_id, '_mzk_passenger_seats', true);
        if ($slot_capacity !== '') {
            return (int) $slot_capacity;
        }
        $vehicle_id = (int) get_post_meta($slot_id, '_mzk_vehicle_id', true);
        return $vehicle_id ? (int) get_post_meta($vehicle_id, '_mzk_passenger_seats', true) : 0;
    }

    private static function format_date($value)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return '';
        }
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $date->getTimestamp());
    }

    private static function redirect_with_message($message)
    {
        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = home_url('/');
        }
        wp_safe_redirect(add_query_arg('mzk_message', $message, $redirect));
        exit;
    }
}

MZK_Mitfahrzentrale_Kalender::init();
