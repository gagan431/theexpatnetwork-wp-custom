<?php
/**
 * Admin settings for The Expat Network Homepage.
 *
 * @package TheExpatNetworkHomepage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEN_Settings {
	const OPTION_NAME = 'ten_homepage_settings';
	const PAGE_SLUG   = 'ten-homepage-settings';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'Expat Network Homepage', 'the-expat-network-homepage' ),
			esc_html__( 'Expat Network Homepage', 'the-expat-network-homepage' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ten_homepage_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);

		add_settings_section(
			'ten_homepage_contact_section',
			esc_html__( 'Forms, contact, and legal pages', 'the-expat-network-homepage' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'contact_email',
			esc_html__( 'Contact email', 'the-expat-network-homepage' ),
			array( $this, 'render_email_field' ),
			self::PAGE_SLUG,
			'ten_homepage_contact_section',
			array(
				'key'         => 'contact_email',
				'description' => esc_html__( 'Receives new lead notifications and is shown in transparency copy.', 'the-expat-network-homepage' ),
			)
		);

		add_settings_field(
			'privacy_page_id',
			esc_html__( 'Privacy Policy page', 'the-expat-network-homepage' ),
			array( $this, 'render_page_dropdown' ),
			self::PAGE_SLUG,
			'ten_homepage_contact_section',
			array(
				'key'         => 'privacy_page_id',
				'description' => esc_html__( 'Select the published page containing the site privacy policy.', 'the-expat-network-homepage' ),
			)
		);

		add_settings_field(
			'imprint_page_id',
			esc_html__( 'Imprint page', 'the-expat-network-homepage' ),
			array( $this, 'render_page_dropdown' ),
			self::PAGE_SLUG,
			'ten_homepage_contact_section',
			array(
				'key'         => 'imprint_page_id',
				'description' => esc_html__( 'Select the published Impressum / Imprint page.', 'the-expat-network-homepage' ),
			)
		);

		add_settings_field(
			'candidate_thank_you_page_id',
			esc_html__( 'Candidate thank-you page', 'the-expat-network-homepage' ),
			array( $this, 'render_page_dropdown' ),
			self::PAGE_SLUG,
			'ten_homepage_contact_section',
			array(
				'key'         => 'candidate_thank_you_page_id',
				'description' => esc_html__( 'Optional. Successful candidate registrations redirect to this page.', 'the-expat-network-homepage' ),
			)
		);

		add_settings_field(
			'partner_thank_you_page_id',
			esc_html__( 'Partner thank-you page', 'the-expat-network-homepage' ),
			array( $this, 'render_page_dropdown' ),
			self::PAGE_SLUG,
			'ten_homepage_contact_section',
			array(
				'key'         => 'partner_thank_you_page_id',
				'description' => esc_html__( 'Optional. Successful partner enquiries redirect to this page.', 'the-expat-network-homepage' ),
			)
		);
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'contact_email'               => 'hello@theexpatnetwork.org',
			'privacy_page_id'             => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
			'imprint_page_id'             => 0,
			'candidate_thank_you_page_id' => 0,
			'partner_thank_you_page_id'   => 0,
		);
	}

	/**
	 * Return merged settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_defaults() );
	}

	/**
	 * Return one setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$settings = $this->get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->get_defaults();

		$output = array(
			'contact_email'               => isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : $defaults['contact_email'],
			'privacy_page_id'             => isset( $input['privacy_page_id'] ) ? absint( $input['privacy_page_id'] ) : $defaults['privacy_page_id'],
			'imprint_page_id'             => isset( $input['imprint_page_id'] ) ? absint( $input['imprint_page_id'] ) : $defaults['imprint_page_id'],
			'candidate_thank_you_page_id' => isset( $input['candidate_thank_you_page_id'] ) ? absint( $input['candidate_thank_you_page_id'] ) : 0,
			'partner_thank_you_page_id'   => isset( $input['partner_thank_you_page_id'] ) ? absint( $input['partner_thank_you_page_id'] ) : 0,
		);

		if ( empty( $output['contact_email'] ) || ! is_email( $output['contact_email'] ) ) {
			$output['contact_email'] = $defaults['contact_email'];
			add_settings_error(
				self::OPTION_NAME,
				'ten_invalid_email',
				esc_html__( 'A valid contact email was not supplied. The default address has been retained.', 'the-expat-network-homepage' ),
				'error'
			);
		}

		return $output;
	}

	/**
	 * Section intro text.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__( 'The plugin now owns both lead forms. Configure where notifications go and which legal or thank-you pages are linked.', 'the-expat-network-homepage' ) . '</p>';
	}

	/**
	 * Render an email field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_email_field( $args ) {
		$key      = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$value    = sanitize_email( (string) $this->get( $key ) );
		$field_id = 'ten_' . $key;

		printf(
			'<input type="email" class="regular-text" id="%1$s" name="%2$s[%3$s]" value="%4$s" />',
			esc_attr( $field_id ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render a published-page dropdown.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_page_dropdown( $args ) {
		$key      = isset( $args['key'] ) ? sanitize_key( $args['key'] ) : '';
		$value    = absint( $this->get( $key ) );
		$field_id = 'ten_' . $key;

		wp_dropdown_pages(
			array(
				'name'              => self::OPTION_NAME . '[' . $key . ']',
				'id'                => $field_id,
				'selected'          => $value,
				'show_option_none'  => esc_html__( 'Select a page', 'the-expat-network-homepage' ),
				'option_none_value' => 0,
				'post_status'       => array( 'publish', 'private' ),
			)
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Resolve a configured page URL.
	 *
	 * @param string $setting_key Setting key.
	 * @param string $fallback Fallback URL.
	 * @return string
	 */
	public function get_page_url( $setting_key, $fallback = '' ) {
		$page_id = absint( $this->get( $setting_key ) );
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return $fallback ? $fallback : home_url( '/' );
	}

	/**
	 * Render settings page markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'The Expat Network Homepage', 'the-expat-network-homepage' ); ?></h1>
			<p><?php echo esc_html__( 'Place the shortcode [ten_homepage] on the WordPress page assigned as the site homepage.', 'the-expat-network-homepage' ); ?></p>
			<p><strong><?php echo esc_html__( 'Version 1.2.0:', 'the-expat-network-homepage' ); ?></strong> <?php echo esc_html__( 'Candidate and partner forms are built into this plugin, with automated retention deadlines and email-status recording. Fluent Forms is not required for this homepage.', 'the-expat-network-homepage' ); ?></p>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'ten_homepage_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
