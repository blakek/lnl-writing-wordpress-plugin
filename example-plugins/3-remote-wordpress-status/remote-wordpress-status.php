<?php

/**
 * Plugin Name: GS&F - Remote WordPress Status
 * Plugin URI: https://github.com/gsandf/example-does-not-exist
 * Description: Sends health stats of your site to the GS&F development team for monitoring.
 * Version: 1.0.0
 * Requires at least: 5.0.0
 * Requires PHP: 7.0.0
 * Author: GS&F Devs <dev@gsandf.com>
 * Author URI: https://gsandf.com
 * License: NOT LICENSED
 */

final class GSF_Remote_WordPress_Status {
	static $cronName = 'gsf_remotestatus_cron_hook';

	static function init() {
		add_action('admin_init', ['GSF_Remote_WordPress_Status', 'add_admin_settings']);
		add_action('admin_menu', ['GSF_Remote_WordPress_Status', 'add_admin_menu']);
		add_action(self::$cronName, ['GSF_Remote_WordPress_Status', 'send_status']);

		self::ensure_event_scheduled();

		register_deactivation_hook(__FILE__, ['GSF_Remote_WordPress_Status', 'deactivate']);
	}

	function add_admin_settings() {
		register_setting('gsf_remotestatus', 'gsf_remotestatus_options');

		add_settings_section(
			'gsf_remotestatus_section_developers',
			'General Settings',
			null,
			'gsf_remotestatus'
		);

		function settings_field_endpoint_html($args) {
			$options = get_option('gsf_remotestatus_options');
			$id = esc_attr($args['label_for']);
			$name = "gsf_remotestatus_options[{$id}]";
			$value = esc_attr($options['gsf_remotestatus_endpoint']);

			printf(
				'<input id="%s" name="%s" type="text" value="%s" style="width: 500px;" />',
				$id,
				$name,
				$value
			);
		}

		add_settings_field(
			'gsf_remotestatus_endpoint', // As of WP 4.6 this value is used only internally.
			'Endpoint to call',
			'settings_field_endpoint_html',
			'gsf_remotestatus',
			'gsf_remotestatus_section_developers',
			[
				'class' => 'gsf_remotestatus_row',
				'label_for' => 'gsf_remotestatus_endpoint'
			]
		);
	}

	/**
	 * Add the top level menu page.
	 */
	function add_admin_menu() {
		add_menu_page(
			'GS&F Remote Status',
			'GS&F Remote Status Options',
			'manage_options',
			'gsf_remotestatus',
			['GSF_Remote_WordPress_Status', 'render_options_page']
		);
	}

	static function ensure_event_scheduled() {
		if (!wp_next_scheduled(self::$cronName)) {
			wp_schedule_event(time(), 'hourly', self::$cronName);
		}
	}

	static function deactivate() {
		// Remove scheduled events
		wp_unschedule_event(
			wp_next_scheduled(self::$cronName),
			self::$cronName
		);
	}

	function render_options_page() {
		// check user capabilities
		if (!current_user_can('manage_options')) {
			return;
		}

		// check if the user have submitted the settings
		// WordPress will add the "settings-updated" $_GET parameter to the url
		if (isset($_GET['settings-updated'])) {
			// add settings saved message with the class of "updated"
			add_settings_error(
				'gsf_remotestatus_messages',
				'gsf_remotestatus_message',
				'Settings Saved',
				'success'
			);
		}

		// show error/update messages
		settings_errors('gsf_remotestatus_messages');

?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields('gsf_remotestatus');
				do_settings_sections('gsf_remotestatus');
				submit_button('Save Settings');
				?>
			</form>
		</div>
<?php
	}

	function send_status() {
		$options = get_option('gsf_remotestatus_options');

		if (empty($options['endpoint'])) {
			return;
		}

		$endpoint = $options['endpoint'];

		$data = [
			'phpVersion' => phpversion()
		];

		wp_remote_post($endpoint, ['body' => $data]);

		self::ensure_event_scheduled();
	}
}

GSF_Remote_WordPress_Status::init();
