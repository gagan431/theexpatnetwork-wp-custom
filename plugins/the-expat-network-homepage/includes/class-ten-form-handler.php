<?php
/**
 * Native form rendering and secure submission handling.
 *
 * @package TheExpatNetworkHomepage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEN_Form_Handler {
	const CANDIDATE_ACTION = 'ten_submit_candidate';
	const PARTNER_ACTION   = 'ten_submit_partner';

	const MIN_FILL_SECONDS          = 4;
	const ATTEMPT_RATE_LIMIT_MAX    = 30;
	const SUCCESS_IP_LIMIT_MAX      = 10;
	const SUCCESS_EMAIL_LIMIT_MAX   = 3;
	const RATE_LIMIT_WINDOW         = HOUR_IN_SECONDS;
	const DUPLICATE_WINDOW          = 10 * MINUTE_IN_SECONDS;
	const FORM_STATE_TTL            = 15 * MINUTE_IN_SECONDS;
	const MAX_REQUEST_BYTES         = 32768;

	/** @var TEN_Settings */
	private $settings;

	/** @var TEN_Submissions */
	private $submissions;

	/** @var array */
	private $render_values = array();

	/** @var array */
	private $render_errors = array();

	/**
	 * Constructor.
	 *
	 * @param TEN_Settings    $settings Settings service.
	 * @param TEN_Submissions $submissions Submission storage service.
	 */
	public function __construct( TEN_Settings $settings, TEN_Submissions $submissions ) {
		$this->settings    = $settings;
		$this->submissions = $submissions;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_nopriv_' . self::CANDIDATE_ACTION, array( $this, 'handle_candidate' ) );
		add_action( 'admin_post_' . self::CANDIDATE_ACTION, array( $this, 'handle_candidate' ) );
		add_action( 'admin_post_nopriv_' . self::PARTNER_ACTION, array( $this, 'handle_partner' ) );
		add_action( 'admin_post_' . self::PARTNER_ACTION, array( $this, 'handle_partner' ) );
	}

	/**
	 * Render candidate registration form.
	 *
	 * @param string $privacy_url Privacy policy URL.
	 * @return string
	 */
	public function render_candidate_form( $privacy_url ) {
		$context = $this->load_form_state( 'candidate' );
		$this->set_render_context( $context );

		$contact_email = sanitize_email( (string) $this->settings->get( 'contact_email' ) );
		$current_situations = array(
			'india_exploring_germany'            => __( 'Based in India and exploring Germany', 'the-expat-network-homepage' ),
			'germany_opportunities'               => __( 'Based in Germany and exploring professional opportunities', 'the-expat-network-homepage' ),
			'south_germany_network'               => __( 'Based in South Germany and interested in professional networking or collaboration', 'the-expat-network-homepage' ),
			'elsewhere_india_germany'             => __( 'Based elsewhere and interested in India–Germany opportunities', 'the-expat-network-homepage' ),
			'entrepreneur_freelancer'              => __( 'Entrepreneur, founder, freelancer or independent professional', 'the-expat-network-homepage' ),
			'researcher_student_early_career'      => __( 'Researcher, student, graduate or early-career professional', 'the-expat-network-homepage' ),
			'other'                                => __( 'Other', 'the-expat-network-homepage' ),
		);
		$experience_options = array(
			'none'    => __( 'No professional experience yet', 'the-expat-network-homepage' ),
			'under_1' => __( 'Less than 1 year', 'the-expat-network-homepage' ),
			'1_3'     => __( '1–3 years', 'the-expat-network-homepage' ),
			'4_7'     => __( '4–7 years', 'the-expat-network-homepage' ),
			'8_12'    => __( '8–12 years', 'the-expat-network-homepage' ),
			'over_12' => __( 'More than 12 years', 'the-expat-network-homepage' ),
		);
		$german_levels = array(
			'not_applicable' => __( 'Not applicable to my current interest', 'the-expat-network-homepage' ),
			'none'           => __( 'No German yet', 'the-expat-network-homepage' ),
			'a1'             => 'A1',
			'a2'             => 'A2',
			'b1'             => 'B1',
			'b2'             => 'B2',
			'c1'             => 'C1',
			'c2'             => 'C2',
			'fluent'         => __( 'Native or fluent', 'the-expat-network-homepage' ),
			'unsure'         => __( 'Not sure', 'the-expat-network-homepage' ),
		);
		$pathways = array(
			'employment'                  => __( 'Career and employment opportunities', 'the-expat-network-homepage' ),
			'freelance'                   => __( 'Contract or specialist work', 'the-expat-network-homepage' ),
			'entrepreneurship_innovation' => __( 'Entrepreneurship and startup connections', 'the-expat-network-homepage' ),
			'technology_innovation'       => __( 'Technology and innovation collaboration', 'the-expat-network-homepage' ),
			'research_collaboration'      => __( 'Research or institutional collaboration', 'the-expat-network-homepage' ),
			'early_career'                => __( 'Graduate, internship or early-career opportunities', 'the-expat-network-homepage' ),
			'exploring'                   => __( 'I’m still exploring the right connection', 'the-expat-network-homepage' ),
		);

		$other_selected = 'other' === $this->value( 'current_situation' );
		$selected_pathways = $this->array_value( 'pathways' );

		ob_start();
		$this->render_notice( 'candidate', $context );
		?>
		<form class="ten-native-form ten-candidate-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CANDIDATE_ACTION ); ?>" />
			<?php wp_nonce_field( self::CANDIDATE_ACTION, 'ten_candidate_nonce' ); ?>
			<input type="hidden" name="ten_form_started" value="<?php echo esc_attr( (string) time() ); ?>" />
			<div class="ten-honeypot" aria-hidden="true">
				<label for="ten_candidate_website"><?php echo esc_html__( 'Leave this field empty', 'the-expat-network-homepage' ); ?></label>
				<input id="ten_candidate_website" type="text" name="website" value="" tabindex="-1" autocomplete="off" />
			</div>

			<fieldset class="ten-form-section">
				<legend><?php echo esc_html__( 'Personal details', 'the-expat-network-homepage' ); ?></legend>
				<div class="ten-form-grid ten-form-grid--2">
					<?php $this->text_field( 'first_name', __( 'First name', 'the-expat-network-homepage' ), __( 'First name', 'the-expat-network-homepage' ), true, 'given-name', '', 100 ); ?>
					<?php $this->text_field( 'last_name', __( 'Last name', 'the-expat-network-homepage' ), __( 'Last name', 'the-expat-network-homepage' ), true, 'family-name', '', 100 ); ?>
				</div>
				<?php $this->email_field( 'email', __( 'Email address', 'the-expat-network-homepage' ), 'you@example.com', true ); ?>
				<div class="ten-form-grid ten-form-grid--2">
					<?php $this->text_field( 'current_country', __( 'Current country of residence', 'the-expat-network-homepage' ), __( 'For example: Germany or India', 'the-expat-network-homepage' ), true, 'country-name', '', 100 ); ?>
					<?php $this->text_field( 'current_city', __( 'Current city (optional)', 'the-expat-network-homepage' ), __( 'City or region', 'the-expat-network-homepage' ), false, 'address-level2', '', 120 ); ?>
				</div>
				<?php $this->select_field( 'current_situation', __( 'Current situation', 'the-expat-network-homepage' ), __( 'Select your current situation', 'the-expat-network-homepage' ), $current_situations, true, 'ten-current-situation' ); ?>
				<div class="ten-conditional-field" data-ten-other-wrapper <?php echo $other_selected ? '' : 'hidden'; ?>>
					<?php $this->text_field( 'current_situation_other', __( 'Please describe your current situation', 'the-expat-network-homepage' ), __( 'Tell us briefly about your current situation', 'the-expat-network-homepage' ), $other_selected, 'off', 'ten-current-situation-other', 250 ); ?>
				</div>
			</fieldset>

			<fieldset class="ten-form-section">
				<legend><?php echo esc_html__( 'Professional profile', 'the-expat-network-homepage' ); ?></legend>
				<?php $this->text_field( 'professional_role', __( 'Current or most recent professional role', 'the-expat-network-homepage' ), __( 'For example: Software Developer, Healthcare Assistant, Marketing Student', 'the-expat-network-homepage' ), true, 'organization-title', '', 200 ); ?>
				<p class="ten-field-help"><?php echo esc_html__( 'Include your professional field if it is not clear from the role title.', 'the-expat-network-homepage' ); ?></p>
				<?php $this->select_field( 'experience_years', __( 'Years of relevant experience', 'the-expat-network-homepage' ), __( 'Select your experience level', 'the-expat-network-homepage' ), $experience_options, true ); ?>
				<?php $this->text_field( 'professional_languages', __( 'Languages you can use professionally', 'the-expat-network-homepage' ), __( 'For example: German B1, English C1, Hindi native', 'the-expat-network-homepage' ), true, 'off', '', 250 ); ?>
				<p class="ten-field-help"><?php echo esc_html__( 'Separate multiple languages with commas.', 'the-expat-network-homepage' ); ?></p>
				<?php $this->select_field( 'german_level', __( 'German language level', 'the-expat-network-homepage' ), __( 'Select your German level', 'the-expat-network-homepage' ), $german_levels, true ); ?>
			</fieldset>

			<fieldset class="ten-form-section" id="ten-pathways-group" aria-required="true" aria-describedby="ten-pathways-help<?php echo $this->has_error( 'pathways' ) ? ' ten-pathways-error' : ''; ?>" <?php echo $this->has_error( 'pathways' ) ? 'aria-invalid="true"' : ''; ?>>
				<legend><?php echo esc_html__( 'Interests', 'the-expat-network-homepage' ); ?></legend>
				<p class="ten-field-label" id="ten-pathways-label"><?php echo esc_html__( 'Which connections or opportunities are you interested in?', 'the-expat-network-homepage' ); ?> <span aria-hidden="true">*</span><span class="ten-sr-only"><?php echo esc_html__( 'Required', 'the-expat-network-homepage' ); ?></span></p>
				<p id="ten-pathways-help" class="ten-field-help ten-field-help--normal"><?php echo esc_html__( 'Select at least one option.', 'the-expat-network-homepage' ); ?></p>
				<div class="ten-check-list" role="group" aria-labelledby="ten-pathways-label">
					<?php foreach ( $pathways as $value => $label ) : ?>
						<label><input type="checkbox" name="pathways[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $selected_pathways, true ) ); ?> /> <span><?php echo esc_html( $label ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php $this->render_field_error( 'pathways', 'ten-pathways-error' ); ?>
			</fieldset>

			<fieldset class="ten-form-section">
				<legend><?php echo esc_html__( 'Additional information', 'the-expat-network-homepage' ); ?></legend>
				<?php $this->url_field( 'linkedin_url', __( 'LinkedIn profile (optional)', 'the-expat-network-homepage' ), 'https://www.linkedin.com/in/your-profile' ); ?>
				<p class="ten-field-help"><?php echo esc_html__( 'Helpful for understanding your professional background.', 'the-expat-network-homepage' ); ?></p>
				<?php $this->textarea_field( 'career_goal', __( 'What kind of connection or opportunity are you looking for?', 'the-expat-network-homepage' ), __( 'Briefly describe the role, collaboration, professional connection or India–Germany opportunity you are interested in. Do not include sensitive personal information.', 'the-expat-network-homepage' ), true, 800 ); ?>
			</fieldset>

			<fieldset class="ten-form-section ten-form-section--privacy">
				<legend><?php echo esc_html__( 'Privacy', 'the-expat-network-homepage' ); ?></legend>
				<label class="ten-consent">
					<input id="ten-privacy-acknowledgement" type="checkbox" name="privacy_acknowledgement" value="1" required <?php checked( 'Yes', $this->value( 'privacy_acknowledgement' ) ); ?> <?php echo $this->has_error( 'privacy_acknowledgement' ) ? 'aria-invalid="true" aria-describedby="ten-privacy-acknowledgement-error"' : ''; ?> />
					<span><strong><?php echo esc_html__( 'Required:', 'the-expat-network-homepage' ); ?></strong> <?php echo esc_html__( 'I have read the', 'the-expat-network-homepage' ); ?> <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Privacy Policy', 'the-expat-network-homepage' ); ?></a> <?php echo esc_html__( 'and understand how The Expat Network will process and respond to this submission.', 'the-expat-network-homepage' ); ?></span>
				</label>
				<?php $this->render_field_error( 'privacy_acknowledgement', 'ten-privacy-acknowledgement-error' ); ?>
				<label class="ten-consent">
					<input id="ten-future-opportunity-permission" type="checkbox" name="future_opportunity_permission" value="1" <?php checked( 'Yes', $this->value( 'future_opportunity_permission' ) ); ?> />
					<span><strong><?php echo esc_html__( 'Optional:', 'the-expat-network-homepage' ); ?></strong> <?php echo esc_html( sprintf( __( 'I agree that The Expat Network may retain my profile for up to 12 months and contact me about potentially relevant opportunities, collaborations or network connections related to my stated interests. I can withdraw this permission at any time by emailing %s.', 'the-expat-network-homepage' ), $contact_email ) ); ?></span>
				</label>
			</fieldset>

			<button class="ten-submit-button" type="submit"><?php echo esc_html__( 'Join The Network', 'the-expat-network-homepage' ); ?></button>
		</form>
		<?php
		$this->clear_render_context();
		return (string) ob_get_clean();
	}

	/**
	 * Render partner enquiry form.
	 *
	 * @param string $privacy_url Privacy policy URL.
	 * @return string
	 */
	public function render_partner_form( $privacy_url ) {
		$context = $this->load_form_state( 'partner' );
		$this->set_render_context( $context );

		$opportunity_types = array(
			'hiring_employment'       => __( 'Hiring or employment opportunities', 'the-expat-network-homepage' ),
			'contract_talent'         => __( 'Contract, freelance or specialist talent', 'the-expat-network-homepage' ),
			'recruiter_partnership'   => __( 'Recruiter or talent-sourcing partnership', 'the-expat-network-homepage' ),
			'startup_innovation'      => __( 'Startup, technology or innovation collaboration', 'the-expat-network-homepage' ),
			'research_collaboration'  => __( 'Research or institutional collaboration', 'the-expat-network-homepage' ),
			'industry_ecosystem'      => __( 'Industry, chamber or ecosystem partnership', 'the-expat-network-homepage' ),
			'service_partnership'     => __( 'Service or relocation partnership', 'the-expat-network-homepage' ),
			'other'                   => __( 'Other', 'the-expat-network-homepage' ),
		);
		$other_selected = 'other' === $this->value( 'opportunity_type' );

		ob_start();
		$this->render_notice( 'partner', $context );
		?>
		<form class="ten-native-form ten-partner-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::PARTNER_ACTION ); ?>" />
			<?php wp_nonce_field( self::PARTNER_ACTION, 'ten_partner_nonce' ); ?>
			<input type="hidden" name="ten_form_started" value="<?php echo esc_attr( (string) time() ); ?>" />
			<div class="ten-honeypot" aria-hidden="true">
				<label for="ten_partner_website_field"><?php echo esc_html__( 'Leave this field empty', 'the-expat-network-homepage' ); ?></label>
				<input id="ten_partner_website_field" type="text" name="website" value="" tabindex="-1" autocomplete="off" />
			</div>

			<div class="ten-form-grid ten-form-grid--2">
				<?php $this->text_field( 'full_name', __( 'Full name', 'the-expat-network-homepage' ), __( 'Your name', 'the-expat-network-homepage' ), true, 'name', '', 200 ); ?>
				<?php $this->email_field( 'work_email', __( 'Email address', 'the-expat-network-homepage' ), 'you@example.com', true ); ?>
			</div>
			<div class="ten-form-grid ten-form-grid--2">
				<?php $this->text_field( 'company_name', __( 'Company or organization', 'the-expat-network-homepage' ), __( 'Organization name', 'the-expat-network-homepage' ), true, 'organization', '', 200 ); ?>
				<?php $this->url_field( 'company_website', __( 'Website (optional)', 'the-expat-network-homepage' ), 'https://example.com' ); ?>
			</div>
			<?php $this->select_field( 'opportunity_type', __( 'How would you like to work with The Expat Network?', 'the-expat-network-homepage' ), __( 'Select the closest match', 'the-expat-network-homepage' ), $opportunity_types, true, 'ten-opportunity-type' ); ?>
			<div class="ten-conditional-field" data-ten-partner-other-wrapper <?php echo $other_selected ? '' : 'hidden'; ?>>
				<?php $this->text_field( 'opportunity_type_other', __( 'Please describe the collaboration or requirement', 'the-expat-network-homepage' ), __( 'Briefly describe what you have in mind', 'the-expat-network-homepage' ), $other_selected, 'off', 'ten-opportunity-type-other', 250 ); ?>
			</div>
			<?php $this->textarea_field( 'message', __( 'What are you looking to achieve?', 'the-expat-network-homepage' ), __( 'Describe the hiring need, collaboration, target profile, location, timing and any important requirements.', 'the-expat-network-homepage' ), true, 1200 ); ?>
			<label class="ten-consent">
				<input id="ten-contact-consent" type="checkbox" name="contact_consent" value="1" required <?php checked( 'Yes', $this->value( 'contact_consent' ) ); ?> <?php echo $this->has_error( 'contact_consent' ) ? 'aria-invalid="true" aria-describedby="ten-contact-consent-error"' : ''; ?> />
				<span><strong><?php echo esc_html__( 'Required:', 'the-expat-network-homepage' ); ?></strong> <?php echo esc_html__( 'I have read the', 'the-expat-network-homepage' ); ?> <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Privacy Policy', 'the-expat-network-homepage' ); ?></a> <?php echo esc_html__( 'and understand that The Expat Network may process this information and contact me about this enquiry.', 'the-expat-network-homepage' ); ?></span>
			</label>
			<?php $this->render_field_error( 'contact_consent', 'ten-contact-consent-error' ); ?>
			<button class="ten-submit-button" type="submit"><?php echo esc_html__( 'Send Partner Enquiry', 'the-expat-network-homepage' ); ?></button>
		</form>
		<?php
		$this->clear_render_context();
		return (string) ob_get_clean();
	}

	/**
	 * Process candidate submission.
	 *
	 * @return void
	 */
	public function handle_candidate() {
		$preflight_error = $this->preflight_request( 'ten_candidate_nonce', self::CANDIDATE_ACTION, 'candidate' );
		if ( $preflight_error ) {
			$this->redirect_error( 'candidate', $preflight_error );
		}

		$current_situations = array( 'india_exploring_germany', 'germany_opportunities', 'south_germany_network', 'elsewhere_india_germany', 'entrepreneur_freelancer', 'researcher_student_early_career', 'other' );
		$experience_options = array( 'none', 'under_1', '1_3', '4_7', '8_12', 'over_12' );
		$german_levels      = array( 'not_applicable', 'none', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2', 'fluent', 'unsure' );
		$allowed_pathways   = array( 'employment', 'freelance', 'entrepreneurship_innovation', 'technology_innovation', 'research_collaboration', 'early_career', 'exploring' );

		$pathways = isset( $_POST['pathways'] ) && is_array( $_POST['pathways'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['pathways'] ) ) : array();
		$pathways = array_values( array_unique( array_intersect( $allowed_pathways, $pathways ) ) );

		$payload = array(
			'first_name'                    => $this->post_text( 'first_name', 100 ),
			'last_name'                     => $this->post_text( 'last_name', 100 ),
			'email'                         => $this->post_email( 'email' ),
			'current_country'               => $this->post_text( 'current_country', 100 ),
			'current_city'                  => $this->post_text( 'current_city', 120 ),
			'current_situation'             => $this->post_choice( 'current_situation', $current_situations ),
			'current_situation_other'       => $this->post_text( 'current_situation_other', 250 ),
			'professional_role'             => $this->post_text( 'professional_role', 200 ),
			'experience_years'              => $this->post_choice( 'experience_years', $experience_options ),
			'professional_languages'        => $this->post_text( 'professional_languages', 250 ),
			'german_level'                  => $this->post_choice( 'german_level', $german_levels ),
			'pathways'                      => $pathways,
			'linkedin_url'                  => $this->post_url( 'linkedin_url', 500 ),
			'career_goal'                   => $this->post_textarea( 'career_goal', 800 ),
			'privacy_acknowledgement'       => isset( $_POST['privacy_acknowledgement'] ) ? 'Yes' : 'No',
			'future_opportunity_permission' => isset( $_POST['future_opportunity_permission'] ) ? 'Yes' : 'No',
		);

		$errors = $this->validate_candidate( $payload );
		if ( ! empty( $errors ) ) {
			$this->redirect_error( 'candidate', 'validation', $payload, $errors );
		}

		if ( 'other' !== $payload['current_situation'] ) {
			$payload['current_situation_other'] = '';
		}

		$fingerprint = $this->candidate_fingerprint( $payload );
		$limit_error = $this->check_submission_limits( 'candidate', $payload['email'], $fingerprint );
		if ( $limit_error ) {
			$this->redirect_error( 'candidate', $limit_error, $payload );
		}

		$stored_payload = $payload;
		$stored_payload['linkedin_url'] = $payload['linkedin_url'] ? esc_url_raw( $payload['linkedin_url'], array( 'http', 'https' ) ) : '';
		$stored_payload['current_situation'] = $this->candidate_situation_label( $payload['current_situation'] );
		$stored_payload['experience_years']  = $this->experience_label( $payload['experience_years'] );
		$stored_payload['german_level']      = $this->german_label( $payload['german_level'] );
		$stored_payload['pathways']          = array_map( array( $this, 'pathway_label' ), $payload['pathways'] );
		$stored_payload['future_opportunity_permission'] = 'Yes' === $payload['future_opportunity_permission'] ? 'Yes — up to 12 months' : 'No';

		$lead_id = $this->submissions->create_lead( 'candidate', $stored_payload );
		if ( is_wp_error( $lead_id ) ) {
			$this->redirect_error( 'candidate', 'storage', $payload );
		}

		$this->record_submission_limits( 'candidate', $payload['email'], $fingerprint );
		$this->send_candidate_emails( $lead_id, $stored_payload );
		$this->redirect_success( 'candidate' );
	}

	/**
	 * Process partner submission.
	 *
	 * @return void
	 */
	public function handle_partner() {
		$preflight_error = $this->preflight_request( 'ten_partner_nonce', self::PARTNER_ACTION, 'partner' );
		if ( $preflight_error ) {
			$this->redirect_error( 'partner', $preflight_error );
		}

		$opportunity_types = array( 'hiring_employment', 'contract_talent', 'recruiter_partnership', 'startup_innovation', 'research_collaboration', 'industry_ecosystem', 'service_partnership', 'other' );
		$payload = array(
			'full_name'              => $this->post_text( 'full_name', 200 ),
			'work_email'             => $this->post_email( 'work_email' ),
			'company_name'           => $this->post_text( 'company_name', 200 ),
			'company_website'        => $this->post_url( 'company_website', 500 ),
			'opportunity_type'       => $this->post_choice( 'opportunity_type', $opportunity_types ),
			'opportunity_type_other' => $this->post_text( 'opportunity_type_other', 250 ),
			'message'                => $this->post_textarea( 'message', 1200 ),
			'contact_consent'        => isset( $_POST['contact_consent'] ) ? 'Yes' : 'No',
		);

		$errors = $this->validate_partner( $payload );
		if ( ! empty( $errors ) ) {
			$this->redirect_error( 'partner', 'validation', $payload, $errors );
		}

		if ( 'other' !== $payload['opportunity_type'] ) {
			$payload['opportunity_type_other'] = '';
		}

		$fingerprint = $this->partner_fingerprint( $payload );
		$limit_error = $this->check_submission_limits( 'partner', $payload['work_email'], $fingerprint );
		if ( $limit_error ) {
			$this->redirect_error( 'partner', $limit_error, $payload );
		}

		$stored_payload = $payload;
		$stored_payload['company_website'] = $payload['company_website'] ? esc_url_raw( $payload['company_website'], array( 'http', 'https' ) ) : '';
		$stored_payload['opportunity_type'] = $this->opportunity_label( $payload['opportunity_type'] );

		$lead_id = $this->submissions->create_lead( 'partner', $stored_payload );
		if ( is_wp_error( $lead_id ) ) {
			$this->redirect_error( 'partner', 'storage', $payload );
		}

		$this->record_submission_limits( 'partner', $payload['work_email'], $fingerprint );
		$this->send_partner_emails( $lead_id, $stored_payload );
		$this->redirect_success( 'partner' );
	}

	/**
	 * Validate request-level security and abuse controls.
	 *
	 * @param string $nonce_field Nonce field name.
	 * @param string $action Nonce action.
	 * @param string $type candidate or partner.
	 * @return string Empty when valid, otherwise an error code.
	 */
	private function preflight_request( $nonce_field, $action, $type ) {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return 'security';
		}

		$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? absint( $_SERVER['CONTENT_LENGTH'] ) : 0;
		if ( $content_length > self::MAX_REQUEST_BYTES ) {
			return 'payload_too_large';
		}

		$nonce = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			return 'security';
		}

		$honeypot = isset( $_POST['website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) ) : '';
		if ( '' !== $honeypot ) {
			return 'spam';
		}

		$started = isset( $_POST['ten_form_started'] ) ? absint( $_POST['ten_form_started'] ) : 0;
		$now     = time();
		if ( $started <= 0 || $started > $now || ( $now - $started ) < self::MIN_FILL_SECONDS ) {
			return 'too_fast';
		}

		if ( ! $this->increment_rate_bucket( 'attempt_' . $type, $this->get_client_ip(), self::ATTEMPT_RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW ) ) {
			return 'rate_limit';
		}

		return '';
	}

	/**
	 * Validate candidate data and return field-level errors.
	 *
	 * @param array $payload Candidate payload.
	 * @return array
	 */
	private function validate_candidate( $payload ) {
		$errors = array();
		$required = array(
			'first_name'             => __( 'Enter your first name.', 'the-expat-network-homepage' ),
			'last_name'              => __( 'Enter your last name.', 'the-expat-network-homepage' ),
			'current_country'        => __( 'Enter your current country of residence.', 'the-expat-network-homepage' ),
			'current_situation'      => __( 'Select your current situation.', 'the-expat-network-homepage' ),
			'professional_role'      => __( 'Enter your current or most recent professional role.', 'the-expat-network-homepage' ),
			'experience_years'       => __( 'Select your experience level.', 'the-expat-network-homepage' ),
			'professional_languages' => __( 'Enter at least one professional language.', 'the-expat-network-homepage' ),
			'german_level'           => __( 'Select your German language level.', 'the-expat-network-homepage' ),
			'career_goal'            => __( 'Briefly describe what you are looking for.', 'the-expat-network-homepage' ),
		);

		foreach ( $required as $key => $message ) {
			if ( '' === $payload[ $key ] ) {
				$errors[ $key ] = $message;
			}
		}
		if ( ! is_email( $payload['email'] ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'the-expat-network-homepage' );
		}
		if ( 'other' === $payload['current_situation'] && '' === $payload['current_situation_other'] ) {
			$errors['current_situation_other'] = __( 'Describe your current situation.', 'the-expat-network-homepage' );
		}
		if ( empty( $payload['pathways'] ) ) {
			$errors['pathways'] = __( 'Select at least one interest.', 'the-expat-network-homepage' );
		}
		if ( '' !== $payload['linkedin_url'] && ! wp_http_validate_url( $payload['linkedin_url'] ) ) {
			$errors['linkedin_url'] = __( 'Enter a valid LinkedIn URL beginning with http:// or https://.', 'the-expat-network-homepage' );
		}
		if ( 'Yes' !== $payload['privacy_acknowledgement'] ) {
			$errors['privacy_acknowledgement'] = __( 'Confirm that you have read the Privacy Policy.', 'the-expat-network-homepage' );
		}

		return $errors;
	}

	/**
	 * Validate partner data and return field-level errors.
	 *
	 * @param array $payload Partner payload.
	 * @return array
	 */
	private function validate_partner( $payload ) {
		$errors = array();
		if ( '' === $payload['full_name'] ) {
			$errors['full_name'] = __( 'Enter your full name.', 'the-expat-network-homepage' );
		}
		if ( ! is_email( $payload['work_email'] ) ) {
			$errors['work_email'] = __( 'Enter a valid email address.', 'the-expat-network-homepage' );
		}
		if ( '' === $payload['company_name'] ) {
			$errors['company_name'] = __( 'Enter your company or organization.', 'the-expat-network-homepage' );
		}
		if ( '' !== $payload['company_website'] && ! wp_http_validate_url( $payload['company_website'] ) ) {
			$errors['company_website'] = __( 'Enter a valid website URL beginning with http:// or https://.', 'the-expat-network-homepage' );
		}
		if ( '' === $payload['opportunity_type'] ) {
			$errors['opportunity_type'] = __( 'Select how you would like to work with The Expat Network.', 'the-expat-network-homepage' );
		}
		if ( 'other' === $payload['opportunity_type'] && '' === $payload['opportunity_type_other'] ) {
			$errors['opportunity_type_other'] = __( 'Describe the collaboration or requirement.', 'the-expat-network-homepage' );
		}
		if ( '' === $payload['message'] ) {
			$errors['message'] = __( 'Describe what you are looking for.', 'the-expat-network-homepage' );
		}
		if ( 'Yes' !== $payload['contact_consent'] ) {
			$errors['contact_consent'] = __( 'Confirm that you have read the Privacy Policy.', 'the-expat-network-homepage' );
		}
		return $errors;
	}

	/**
	 * Check success-rate, email, and duplicate limits without consuming a slot.
	 *
	 * @param string $type Form type.
	 * @param string $email Normalized email.
	 * @param string $fingerprint Submission fingerprint.
	 * @return string Empty or error code.
	 */
	private function check_submission_limits( $type, $email, $fingerprint ) {
		$ip = $this->get_client_ip();
		if ( $ip && $this->rate_bucket_count( 'success_ip_' . $type, $ip ) >= self::SUCCESS_IP_LIMIT_MAX ) {
			return 'rate_limit';
		}
		if ( $email && $this->rate_bucket_count( 'success_email_' . $type, strtolower( $email ) ) >= self::SUCCESS_EMAIL_LIMIT_MAX ) {
			return 'email_rate_limit';
		}
		if ( get_transient( $this->transient_key( 'duplicate_' . $type, $fingerprint ) ) ) {
			return 'duplicate';
		}
		return '';
	}

	/**
	 * Consume successful submission buckets after storage succeeds.
	 *
	 * @param string $type Form type.
	 * @param string $email Normalized email.
	 * @param string $fingerprint Submission fingerprint.
	 * @return void
	 */
	private function record_submission_limits( $type, $email, $fingerprint ) {
		$ip = $this->get_client_ip();
		if ( $ip ) {
			$this->increment_rate_bucket( 'success_ip_' . $type, $ip, self::SUCCESS_IP_LIMIT_MAX, self::RATE_LIMIT_WINDOW );
		}
		if ( $email ) {
			$this->increment_rate_bucket( 'success_email_' . $type, strtolower( $email ), self::SUCCESS_EMAIL_LIMIT_MAX, self::RATE_LIMIT_WINDOW );
		}
		set_transient( $this->transient_key( 'duplicate_' . $type, $fingerprint ), 1, self::DUPLICATE_WINDOW );
	}

	/**
	 * Increment a transient counter without retaining the raw identifier.
	 *
	 * @param string $scope Counter scope.
	 * @param string $identifier Raw identifier.
	 * @param int    $maximum Maximum allowed count.
	 * @param int    $window Window in seconds.
	 * @return bool True if accepted.
	 */
	private function increment_rate_bucket( $scope, $identifier, $maximum, $window ) {
		if ( '' === $identifier ) {
			return true;
		}
		$key   = $this->transient_key( $scope, $identifier );
		$count = (int) get_transient( $key );
		if ( $count >= $maximum ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Return a transient counter.
	 *
	 * @param string $scope Counter scope.
	 * @param string $identifier Raw identifier.
	 * @return int
	 */
	private function rate_bucket_count( $scope, $identifier ) {
		return '' === $identifier ? 0 : (int) get_transient( $this->transient_key( $scope, $identifier ) );
	}

	/**
	 * Build a short keyed-HMAC transient key; raw IP/email values are not stored.
	 *
	 * @param string $scope Key scope.
	 * @param string $identifier Raw identifier.
	 * @return string
	 */
	private function transient_key( $scope, $identifier ) {
		$digest = hash_hmac( 'sha256', $scope . '|' . $identifier, wp_salt( 'nonce' ) );
		return 'ten_' . sanitize_key( $scope ) . '_' . substr( $digest, 0, 32 );
	}

	/**
	 * Return validated REMOTE_ADDR, or empty string.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** Candidate duplicate fingerprint. */
	private function candidate_fingerprint( $payload ) {
		$data = array(
			strtolower( $payload['email'] ),
			strtolower( $payload['first_name'] ),
			strtolower( $payload['last_name'] ),
			strtolower( $payload['career_goal'] ),
			implode( '|', $payload['pathways'] ),
		);
		return hash_hmac( 'sha256', implode( '||', $data ), wp_salt( 'auth' ) );
	}

	/** Partner duplicate fingerprint. */
	private function partner_fingerprint( $payload ) {
		$data = array(
			strtolower( $payload['work_email'] ),
			strtolower( $payload['company_name'] ),
			strtolower( $payload['message'] ),
			$payload['opportunity_type'],
		);
		return hash_hmac( 'sha256', implode( '||', $data ), wp_salt( 'auth' ) );
	}

	/**
	 * Send candidate emails and record wp_mail() acceptance status.
	 *
	 * @param int   $lead_id Lead ID.
	 * @param array $payload Stored payload.
	 * @return void
	 */
	private function send_candidate_emails( $lead_id, $payload ) {
		$contact_email = sanitize_email( (string) $this->settings->get( 'contact_email' ) );
		$name          = trim( $payload['first_name'] . ' ' . $payload['last_name'] );
		$admin_url     = admin_url( 'post.php?post=' . absint( $lead_id ) . '&action=edit' );

		if ( $contact_email ) {
			$subject = sprintf( '[Talent Registration] New candidate lead #%d', absint( $lead_id ) );
			$body    = "A new candidate registration has been stored privately in WordPress.\n\n";
			$body   .= 'Name: ' . $name . "\n";
			$body   .= 'Email: ' . $payload['email'] . "\n";
			$body   .= 'Country: ' . $payload['current_country'] . "\n";
			$body   .= 'Role: ' . $payload['professional_role'] . "\n";
			$body   .= 'Interests: ' . implode( ', ', $payload['pathways'] ) . "\n\n";
			$body   .= 'Review the complete entry: ' . $admin_url . "\n";
			$headers = array( 'Reply-To: ' . $name . ' <' . $payload['email'] . '>' );
			$admin_sent = wp_mail( $contact_email, $subject, $body, $headers );
			$this->submissions->record_email_status( $lead_id, 'admin', $admin_sent ? 'sent' : 'failed' );
		} else {
			$this->submissions->record_email_status( $lead_id, 'admin', 'not_configured' );
		}

		$subject = __( 'We received your registration — The Expat Network', 'the-expat-network-homepage' );
		$body    = sprintf( __( "Hi %1$s,\n\nThank you for registering your interest with The Expat Network. We have received your information and will review it manually.\n\nRegistering does not guarantee an opportunity, introduction, collaboration, interview, project, employment, partnership, visa sponsorship, relocation support, income, or response. If we identify a potentially relevant opportunity, collaboration or next step, we may contact you using the email address provided and in accordance with the preferences selected in the form.\n\nPlease do not send identification documents, residence permits, financial information, or other sensitive records by email unless a secure and appropriate process has been agreed.\n\nYou can request access, correction, or deletion of your information by contacting %2$s.\n\nThe Expat Network", 'the-expat-network-homepage' ), $payload['first_name'], $contact_email );
		$submitter_sent = wp_mail( $payload['email'], $subject, $body );
		$this->submissions->record_email_status( $lead_id, 'submitter', $submitter_sent ? 'sent' : 'failed' );
	}

	/**
	 * Send partner emails and record wp_mail() acceptance status.
	 *
	 * @param int   $lead_id Lead ID.
	 * @param array $payload Stored payload.
	 * @return void
	 */
	private function send_partner_emails( $lead_id, $payload ) {
		$contact_email = sanitize_email( (string) $this->settings->get( 'contact_email' ) );
		$admin_url     = admin_url( 'post.php?post=' . absint( $lead_id ) . '&action=edit' );

		if ( $contact_email ) {
			$subject = sprintf( '[Partner Enquiry] New partner lead #%d', absint( $lead_id ) );
			$body    = "A new partner enquiry has been stored privately in WordPress.\n\n";
			$body   .= 'Name: ' . $payload['full_name'] . "\n";
			$body   .= 'Email: ' . $payload['work_email'] . "\n";
			$body   .= 'Company: ' . $payload['company_name'] . "\n";
			$body   .= 'Partner interest: ' . $payload['opportunity_type'] . "\n\n";
			$body   .= 'Review the complete entry: ' . $admin_url . "\n";
			$headers = array( 'Reply-To: ' . $payload['full_name'] . ' <' . $payload['work_email'] . '>' );
			$admin_sent = wp_mail( $contact_email, $subject, $body, $headers );
			$this->submissions->record_email_status( $lead_id, 'admin', $admin_sent ? 'sent' : 'failed' );
		} else {
			$this->submissions->record_email_status( $lead_id, 'admin', 'not_configured' );
		}

		$subject = __( 'We received your enquiry — The Expat Network', 'the-expat-network-homepage' );
		$body    = sprintf( __( "Hi %s,\n\nThank you for contacting The Expat Network. Your enquiry has been received and will be reviewed manually.\n\nSubmitting this form does not create a partnership or any obligation on either side. If it appears relevant to the network, we may contact you for more information.\n\nThe Expat Network", 'the-expat-network-homepage' ), $payload['full_name'] );
		$submitter_sent = wp_mail( $payload['work_email'], $subject, $body );
		$this->submissions->record_email_status( $lead_id, 'submitter', $submitter_sent ? 'sent' : 'failed' );
	}

	/**
	 * Load short-lived form state from a random token in the URL.
	 *
	 * @param string $type candidate or partner.
	 * @return array
	 */
	private function load_form_state( $type ) {
		$token = isset( $_GET['ten_state'] ) ? sanitize_key( wp_unslash( $_GET['ten_state'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return array();
		}
		$state = get_transient( 'ten_form_state_' . $token );
		if ( ! is_array( $state ) || ! isset( $state['type'] ) || $type !== $state['type'] ) {
			return array();
		}
		return $state;
	}

	/** Save short-lived sanitized state and return its random token. */
	private function save_form_state( $type, $code, $values, $errors ) {
		$token = substr( hash_hmac( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ), wp_salt( 'secure_auth' ) ), 0, 32 );
		set_transient(
			'ten_form_state_' . $token,
			array(
				'type'   => $type,
				'code'   => sanitize_key( $code ),
				'values' => is_array( $values ) ? $values : array(),
				'errors' => is_array( $errors ) ? $errors : array(),
			),
			self::FORM_STATE_TTL
		);
		return $token;
	}

	/** Set helper render context. */
	private function set_render_context( $context ) {
		$this->render_values = isset( $context['values'] ) && is_array( $context['values'] ) ? $context['values'] : array();
		$this->render_errors = isset( $context['errors'] ) && is_array( $context['errors'] ) ? $context['errors'] : array();
	}

	/** Clear helper render context. */
	private function clear_render_context() {
		$this->render_values = array();
		$this->render_errors = array();
	}

	/** Render a status/error notice. */
	private function render_notice( $type, $context ) {
		$key    = 'ten_' . $type . '_status';
		$status = isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
		if ( ! $status ) {
			return;
		}
		if ( 'success' === $status ) {
			echo '<div class="ten-form-notice ten-form-notice--success" role="status">' . esc_html__( 'Thank you. Your submission has been received.', 'the-expat-network-homepage' ) . '</div>';
			return;
		}

		$code = isset( $context['code'] ) ? sanitize_key( $context['code'] ) : ( isset( $_GET['ten_error'] ) ? sanitize_key( wp_unslash( $_GET['ten_error'] ) ) : 'unexpected' );
		$errors = isset( $context['errors'] ) && is_array( $context['errors'] ) ? $context['errors'] : array();
		$message = $this->error_message( $code );
		echo '<div class="ten-form-notice ten-form-notice--error ten-error-summary" role="alert" tabindex="-1" data-ten-error-summary>';
		echo '<p><strong>' . esc_html( $message ) . '</strong></p>';
		if ( $errors ) {
			echo '<ul>';
			foreach ( $errors as $field => $error ) {
				echo '<li><a href="#' . esc_attr( $this->field_id( $field ) ) . '">' . esc_html( $error ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/** Map safe error codes to useful messages. */
	private function error_message( $code ) {
		$messages = array(
			'validation'       => __( 'Please correct the highlighted fields and submit the form again.', 'the-expat-network-homepage' ),
			'security'         => __( 'The security check expired or could not be verified. Reload the page and try again.', 'the-expat-network-homepage' ),
			'too_fast'         => __( 'The form was submitted too quickly. Wait a few seconds and try again.', 'the-expat-network-homepage' ),
			'spam'             => __( 'The submission could not be accepted.', 'the-expat-network-homepage' ),
			'rate_limit'       => __( 'Too many submissions were received from this connection. Please try again later.', 'the-expat-network-homepage' ),
			'email_rate_limit' => __( 'This email address has submitted several enquiries recently. Please try again later.', 'the-expat-network-homepage' ),
			'duplicate'        => __( 'This submission appears to have already been received. Please wait before trying again.', 'the-expat-network-homepage' ),
			'payload_too_large'=> __( 'The submitted information was too large. Shorten the text and try again.', 'the-expat-network-homepage' ),
			'storage'          => __( 'Your submission could not be stored. Your entered information has been restored so you can try again.', 'the-expat-network-homepage' ),
			'unexpected'       => __( 'We could not submit the form. Please try again.', 'the-expat-network-homepage' ),
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['unexpected'];
	}

	/** Redirect after success. */
	private function redirect_success( $type ) {
		$setting_key = 'candidate' === $type ? 'candidate_thank_you_page_id' : 'partner_thank_you_page_id';
		$page_id     = absint( $this->settings->get( $setting_key ) );
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				wp_safe_redirect( esc_url_raw( $url ) );
				exit;
			}
		}

		$anchor  = 'candidate' === $type ? '#join' : '#partner-with-us';
		$referer = wp_get_referer();
		$base    = wp_validate_redirect( $referer, home_url( '/' ) );
		$base    = remove_query_arg( array( 'ten_candidate_status', 'ten_partner_status', 'ten_error', 'ten_state' ), $base );
		$url     = add_query_arg( 'ten_' . $type . '_status', 'success', $base ) . $anchor;
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/** Redirect after failure, preserving only sanitized values in a short-lived transient. */
	private function redirect_error( $type, $code, $values = array(), $errors = array() ) {
		$anchor  = 'candidate' === $type ? '#join' : '#partner-with-us';
		$referer = wp_get_referer();
		$base    = wp_validate_redirect( $referer, home_url( '/' ) );
		$base    = remove_query_arg( array( 'ten_candidate_status', 'ten_partner_status', 'ten_error', 'ten_state' ), $base );
		$args    = array(
			'ten_' . $type . '_status' => 'error',
			'ten_error'                 => sanitize_key( $code ),
		);
		if ( $values || $errors ) {
			$args['ten_state'] = $this->save_form_state( $type, $code, $values, $errors );
		}
		$url = add_query_arg( $args, $base ) . $anchor;
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/** Form field helpers. */
	private function text_field( $name, $label, $placeholder, $required = false, $autocomplete = 'off', $id = '', $maxlength = 250 ) {
		$id = $id ? $id : $this->field_id( $name );
		?>
		<div class="ten-field<?php echo $this->has_error( $name ) ? ' ten-field--error' : ''; ?>">
			<?php $this->render_label( $id, $label, $required ); ?>
			<input id="<?php echo esc_attr( $id ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $this->value( $name ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="<?php echo esc_attr( $autocomplete ); ?>" maxlength="<?php echo esc_attr( (string) absint( $maxlength ) ); ?>" <?php echo $required ? 'required' : ''; ?> <?php $this->render_error_attributes( $name ); ?> />
			<?php $this->render_field_error( $name ); ?>
		</div>
		<?php
	}

	private function email_field( $name, $label, $placeholder, $required = false ) {
		$id = $this->field_id( $name );
		?>
		<div class="ten-field<?php echo $this->has_error( $name ) ? ' ten-field--error' : ''; ?>">
			<?php $this->render_label( $id, $label, $required ); ?>
			<input id="<?php echo esc_attr( $id ); ?>" type="email" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $this->value( $name ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="email" maxlength="254" inputmode="email" <?php echo $required ? 'required' : ''; ?> <?php $this->render_error_attributes( $name ); ?> />
			<?php $this->render_field_error( $name ); ?>
		</div>
		<?php
	}

	private function url_field( $name, $label, $placeholder ) {
		$id = $this->field_id( $name );
		?>
		<div class="ten-field<?php echo $this->has_error( $name ) ? ' ten-field--error' : ''; ?>">
			<?php $this->render_label( $id, $label, false ); ?>
			<input id="<?php echo esc_attr( $id ); ?>" type="url" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $this->value( $name ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="url" maxlength="500" inputmode="url" <?php $this->render_error_attributes( $name ); ?> />
			<?php $this->render_field_error( $name ); ?>
		</div>
		<?php
	}

	private function select_field( $name, $label, $placeholder, $options, $required = false, $id = '' ) {
		$id = $id ? $id : $this->field_id( $name );
		?>
		<div class="ten-field<?php echo $this->has_error( $name ) ? ' ten-field--error' : ''; ?>">
			<?php $this->render_label( $id, $label, $required ); ?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?> <?php $this->render_error_attributes( $name ); ?>>
				<option value=""><?php echo esc_html( $placeholder ); ?></option>
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->value( $name ), $value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php $this->render_field_error( $name ); ?>
		</div>
		<?php
	}

	private function textarea_field( $name, $label, $placeholder, $required = false, $maxlength = 1000 ) {
		$id = $this->field_id( $name );
		?>
		<div class="ten-field<?php echo $this->has_error( $name ) ? ' ten-field--error' : ''; ?>">
			<?php $this->render_label( $id, $label, $required ); ?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" maxlength="<?php echo esc_attr( (string) absint( $maxlength ) ); ?>" rows="5" <?php echo $required ? 'required' : ''; ?> <?php $this->render_error_attributes( $name ); ?>><?php echo esc_textarea( $this->value( $name ) ); ?></textarea>
			<?php $this->render_field_error( $name ); ?>
		</div>
		<?php
	}

	private function render_label( $id, $label, $required ) {
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span aria-hidden="true">*</span><span class="ten-sr-only">' . esc_html__( 'Required', 'the-expat-network-homepage' ) . '</span>';
		}
		echo '</label>';
	}

	private function render_error_attributes( $name ) {
		if ( $this->has_error( $name ) ) {
			echo 'aria-invalid="true" aria-describedby="' . esc_attr( $this->error_id( $name ) ) . '"';
		}
	}

	private function render_field_error( $name, $id = '' ) {
		if ( ! $this->has_error( $name ) ) {
			return;
		}
		$id = $id ? $id : $this->error_id( $name );
		echo '<p class="ten-field-error" id="' . esc_attr( $id ) . '">' . esc_html( $this->render_errors[ $name ] ) . '</p>';
	}

	private function field_id( $name ) {
		$special = array(
			'pathways'                => 'ten-pathways-group',
			'privacy_acknowledgement' => 'ten-privacy-acknowledgement',
			'contact_consent'         => 'ten-contact-consent',
		);
		return isset( $special[ $name ] ) ? $special[ $name ] : 'ten-' . str_replace( '_', '-', $name );
	}

	private function error_id( $name ) {
		return $this->field_id( $name ) . '-error';
	}

	private function value( $name ) {
		return isset( $this->render_values[ $name ] ) && ! is_array( $this->render_values[ $name ] ) ? (string) $this->render_values[ $name ] : '';
	}

	private function array_value( $name ) {
		return isset( $this->render_values[ $name ] ) && is_array( $this->render_values[ $name ] ) ? $this->render_values[ $name ] : array();
	}

	private function has_error( $name ) {
		return isset( $this->render_errors[ $name ] ) && '' !== $this->render_errors[ $name ];
	}

	/** Request data helpers. */
	private function post_text( $key, $max_length = 250 ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	private function post_email( $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_email( wp_unslash( $_POST[ $key ] ) ) : '';
		return substr( $value, 0, 254 );
	}

	private function post_url( $key, $max_length = 500 ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$value = trim( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	private function post_textarea( $key, $max_length ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	private function post_choice( $key, $allowed ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/** Label helpers. */
	private function candidate_situation_label( $value ) {
		$labels = array(
			'india_exploring_germany'       => __( 'Based in India and exploring Germany', 'the-expat-network-homepage' ),
			'germany_opportunities'          => __( 'Based in Germany and exploring professional opportunities', 'the-expat-network-homepage' ),
			'south_germany_network'          => __( 'Based in South Germany and interested in professional networking or collaboration', 'the-expat-network-homepage' ),
			'elsewhere_india_germany'        => __( 'Based elsewhere and interested in India–Germany opportunities', 'the-expat-network-homepage' ),
			'entrepreneur_freelancer'         => __( 'Entrepreneur, founder, freelancer or independent professional', 'the-expat-network-homepage' ),
			'researcher_student_early_career' => __( 'Researcher, student, graduate or early-career professional', 'the-expat-network-homepage' ),
			'other'                           => __( 'Other', 'the-expat-network-homepage' ),
		);
		return isset( $labels[ $value ] ) ? $labels[ $value ] : '';
	}

	private function experience_label( $value ) {
		$labels = array(
			'none'    => __( 'No professional experience yet', 'the-expat-network-homepage' ),
			'under_1' => __( 'Less than 1 year', 'the-expat-network-homepage' ),
			'1_3'     => __( '1–3 years', 'the-expat-network-homepage' ),
			'4_7'     => __( '4–7 years', 'the-expat-network-homepage' ),
			'8_12'    => __( '8–12 years', 'the-expat-network-homepage' ),
			'over_12' => __( 'More than 12 years', 'the-expat-network-homepage' ),
		);
		return isset( $labels[ $value ] ) ? $labels[ $value ] : '';
	}

	private function german_label( $value ) {
		$labels = array(
			'not_applicable' => __( 'Not applicable to my current interest', 'the-expat-network-homepage' ),
			'none'           => __( 'No German yet', 'the-expat-network-homepage' ),
			'a1'             => 'A1',
			'a2'             => 'A2',
			'b1'             => 'B1',
			'b2'             => 'B2',
			'c1'             => 'C1',
			'c2'             => 'C2',
			'fluent'         => __( 'Native or fluent', 'the-expat-network-homepage' ),
			'unsure'         => __( 'Not sure', 'the-expat-network-homepage' ),
		);
		return isset( $labels[ $value ] ) ? $labels[ $value ] : '';
	}

	public function pathway_label( $value ) {
		$labels = array(
			'employment'                  => __( 'Career and employment opportunities', 'the-expat-network-homepage' ),
			'freelance'                   => __( 'Contract or specialist work', 'the-expat-network-homepage' ),
			'entrepreneurship_innovation' => __( 'Entrepreneurship and startup connections', 'the-expat-network-homepage' ),
			'technology_innovation'       => __( 'Technology and innovation collaboration', 'the-expat-network-homepage' ),
			'research_collaboration'      => __( 'Research or institutional collaboration', 'the-expat-network-homepage' ),
			'early_career'                => __( 'Graduate, internship or early-career opportunities', 'the-expat-network-homepage' ),
			'exploring'                   => __( 'I’m still exploring the right connection', 'the-expat-network-homepage' ),
		);
		return isset( $labels[ $value ] ) ? $labels[ $value ] : '';
	}

	private function opportunity_label( $value ) {
		$labels = array(
			'hiring_employment'      => __( 'Hiring or employment opportunities', 'the-expat-network-homepage' ),
			'contract_talent'        => __( 'Contract, freelance or specialist talent', 'the-expat-network-homepage' ),
			'recruiter_partnership'  => __( 'Recruiter or talent-sourcing partnership', 'the-expat-network-homepage' ),
			'startup_innovation'     => __( 'Startup, technology or innovation collaboration', 'the-expat-network-homepage' ),
			'research_collaboration' => __( 'Research or institutional collaboration', 'the-expat-network-homepage' ),
			'industry_ecosystem'     => __( 'Industry, chamber or ecosystem partnership', 'the-expat-network-homepage' ),
			'service_partnership'    => __( 'Service or relocation partnership', 'the-expat-network-homepage' ),
			'other'                  => __( 'Other', 'the-expat-network-homepage' ),
		);
		return isset( $labels[ $value ] ) ? $labels[ $value ] : '';
	}
}
