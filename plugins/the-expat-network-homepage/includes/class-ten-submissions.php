<?php
/**
 * Private lead storage, retention, email status, and admin review screens.
 *
 * @package TheExpatNetworkHomepage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TEN_Submissions {
	const POST_TYPE            = 'ten_lead';
	const CRON_HOOK            = 'ten_cleanup_expired_leads';
	const DATA_VERSION_OPTION  = 'ten_homepage_data_version';
	const CURRENT_DATA_VERSION = '1.2.0';
	const CANDIDATE_SHORT_DAYS = 60;
	const CANDIDATE_LONG_DAYS  = 365;
	const PARTNER_DAYS         = 365;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
		add_action( 'init', array( $this, 'ensure_cleanup_scheduled' ), 30 );
		add_action( self::CRON_HOOK, array( $this, 'cleanup_expired_leads' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'filter_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_admin_sorting' ) );
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public function activate() {
		$this->register_post_type();
		$this->maybe_upgrade();
		$this->ensure_cleanup_scheduled();
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Register a private, administrator-only custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$capabilities = array(
			'edit_post'              => 'manage_options',
			'read_post'              => 'manage_options',
			'delete_post'            => 'manage_options',
			'edit_posts'             => 'manage_options',
			'edit_others_posts'      => 'manage_options',
			'publish_posts'          => 'manage_options',
			'read_private_posts'     => 'manage_options',
			'delete_posts'           => 'manage_options',
			'delete_private_posts'   => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_others_posts'    => 'manage_options',
			'edit_private_posts'     => 'manage_options',
			'edit_published_posts'   => 'manage_options',
			'create_posts'           => 'do_not_allow',
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => esc_html__( 'Expat Network Leads', 'the-expat-network-homepage' ),
					'singular_name' => esc_html__( 'Lead', 'the-expat-network-homepage' ),
					'menu_name'     => esc_html__( 'Expat Network Leads', 'the-expat-network-homepage' ),
					'all_items'     => esc_html__( 'All Leads', 'the-expat-network-homepage' ),
					'edit_item'     => esc_html__( 'Review Lead', 'the-expat-network-homepage' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => false,
				'menu_icon'          => 'dashicons-groups',
				'menu_position'      => 26,
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capabilities'       => $capabilities,
				'map_meta_cap'       => false,
			)
		);
	}

	/**
	 * Create a private lead record and its enforceable retention deadline.
	 *
	 * @param string $type Lead type: candidate or partner.
	 * @param array  $payload Sanitized form payload.
	 * @return int|WP_Error
	 */
	public function create_lead( $type, $payload ) {
		$type = in_array( $type, array( 'candidate', 'partner' ), true ) ? $type : 'candidate';

		if ( 'partner' === $type ) {
			$name    = isset( $payload['full_name'] ) ? $payload['full_name'] : '';
			$company = isset( $payload['company_name'] ) ? $payload['company_name'] : '';
			$title   = sprintf( 'Partner: %1$s — %2$s', $company, $name );
			$email   = isset( $payload['work_email'] ) ? $payload['work_email'] : '';
			$summary = isset( $payload['opportunity_type'] ) ? $payload['opportunity_type'] : '';
		} else {
			$name    = trim( ( isset( $payload['first_name'] ) ? $payload['first_name'] : '' ) . ' ' . ( isset( $payload['last_name'] ) ? $payload['last_name'] : '' ) );
			$title   = sprintf( 'Candidate: %s', $name );
			$email   = isset( $payload['email'] ) ? $payload['email'] : '';
			$summary = ! empty( $payload['pathways'] ) && is_array( $payload['pathways'] ) ? implode( ', ', $payload['pathways'] ) : '';
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => wp_strip_all_tags( $title ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$created_timestamp = time();
		$retention          = $this->calculate_retention( $type, $payload, $created_timestamp );

		update_post_meta( $post_id, '_ten_lead_type', $type );
		update_post_meta( $post_id, '_ten_lead_name', sanitize_text_field( $name ) );
		update_post_meta( $post_id, '_ten_lead_email', sanitize_email( $email ) );
		update_post_meta( $post_id, '_ten_lead_summary', sanitize_text_field( $summary ) );
		update_post_meta( $post_id, '_ten_lead_payload', $payload );
		update_post_meta( $post_id, '_ten_lead_created_gmt', gmdate( 'Y-m-d H:i:s', $created_timestamp ) );
		update_post_meta( $post_id, '_ten_retention_expires_gmt', (int) $retention['expires'] );
		update_post_meta( $post_id, '_ten_retention_policy', sanitize_text_field( $retention['label'] ) );
		update_post_meta( $post_id, '_ten_admin_email_status', 'pending' );
		update_post_meta( $post_id, '_ten_submitter_email_status', 'pending' );

		return $post_id;
	}

	/**
	 * Record WordPress mail acceptance status for a lead.
	 *
	 * wp_mail() returning true means WordPress accepted the send request; it does
	 * not prove inbox delivery.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $recipient admin or submitter.
	 * @param string $status sent, failed, or not_configured.
	 * @return void
	 */
	public function record_email_status( $lead_id, $recipient, $status ) {
		if ( ! in_array( $recipient, array( 'admin', 'submitter' ), true ) ) {
			return;
		}
		if ( ! in_array( $status, array( 'sent', 'failed', 'not_configured' ), true ) ) {
			$status = 'failed';
		}

		update_post_meta( absint( $lead_id ), '_ten_' . $recipient . '_email_status', $status );
		update_post_meta( absint( $lead_id ), '_ten_email_attempted_gmt', current_time( 'mysql', true ) );
	}

	/**
	 * Calculate the retention deadline for a new or migrated lead.
	 *
	 * @param string $type Lead type.
	 * @param array  $payload Lead payload.
	 * @param int    $created_timestamp UTC timestamp.
	 * @return array
	 */
	private function calculate_retention( $type, $payload, $created_timestamp ) {
		if ( 'partner' === $type ) {
			$days  = self::PARTNER_DAYS;
			$label = sprintf( __( '%d days — partner enquiry', 'the-expat-network-homepage' ), $days );
		} else {
			$permission = isset( $payload['future_opportunity_permission'] ) ? (string) $payload['future_opportunity_permission'] : 'No';
			$granted    = 0 === strpos( $permission, 'Yes' );
			$days       = $granted ? self::CANDIDATE_LONG_DAYS : self::CANDIDATE_SHORT_DAYS;
			$label      = $granted
				? sprintf( __( '%d days — future-opportunity permission', 'the-expat-network-homepage' ), $days )
				: sprintf( __( '%d days — immediate enquiry review only', 'the-expat-network-homepage' ), $days );
		}

		return array(
			'expires' => $created_timestamp + ( $days * DAY_IN_SECONDS ),
			'label'   => $label,
		);
	}

	/**
	 * One-time migration for leads created by earlier plugin versions.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( self::CURRENT_DATA_VERSION === get_option( self::DATA_VERSION_OPTION ) ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => array( 'private', 'trash' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_ten_retention_expires_gmt',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$type    = (string) get_post_meta( $post_id, '_ten_lead_type', true );
			$payload = get_post_meta( $post_id, '_ten_lead_payload', true );
			$payload = is_array( $payload ) ? $payload : array();
			$created = (string) get_post_meta( $post_id, '_ten_lead_created_gmt', true );
			$created_timestamp = $created ? strtotime( $created . ' UTC' ) : false;
			if ( ! $created_timestamp ) {
				$post = get_post( $post_id );
				$created_timestamp = $post ? strtotime( $post->post_date_gmt . ' UTC' ) : false;
			}
			if ( ! $created_timestamp ) {
				$created_timestamp = time();
			}

			$retention = $this->calculate_retention( $type, $payload, (int) $created_timestamp );
			update_post_meta( $post_id, '_ten_retention_expires_gmt', (int) $retention['expires'] );
			update_post_meta( $post_id, '_ten_retention_policy', sanitize_text_field( $retention['label'] ) );

			if ( '' === get_post_meta( $post_id, '_ten_admin_email_status', true ) ) {
				update_post_meta( $post_id, '_ten_admin_email_status', 'unknown' );
			}
			if ( '' === get_post_meta( $post_id, '_ten_submitter_email_status', true ) ) {
				update_post_meta( $post_id, '_ten_submitter_email_status', 'unknown' );
			}
		}

		update_option( self::DATA_VERSION_OPTION, self::CURRENT_DATA_VERSION, false );
	}

	/**
	 * Ensure the daily retention cleanup is scheduled.
	 *
	 * @return void
	 */
	public function ensure_cleanup_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Permanently delete leads once their configured retention deadline expires.
	 *
	 * Expired personal data is not kept in WordPress Trash because doing so would
	 * extend storage beyond the public retention promise. Processing is batched
	 * to keep the scheduled job bounded on shared hosting.
	 *
	 * @return void
	 */
	public function cleanup_expired_leads() {
		$now          = time();
		$batch_size   = 100;
		$max_batches  = 20;
		$batch_number = 0;

		do {
			$expired = new WP_Query(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => 'private',
					'posts_per_page'         => $batch_size,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'meta_query'             => array(
						array(
							'key'     => '_ten_retention_expires_gmt',
							'value'   => $now,
							'compare' => '<=',
							'type'    => 'NUMERIC',
						),
					),
				)
			);

			foreach ( $expired->posts as $post_id ) {
				wp_delete_post( $post_id, true );
			}

			++$batch_number;
		} while ( count( $expired->posts ) === $batch_size && $batch_number < $max_batches );
	}

	/**
	 * Add read-only lead details meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'ten_lead_details',
			esc_html__( 'Submitted information', 'the-expat-network-homepage' ),
			array( $this, 'render_details_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render lead details and operational metadata.
	 *
	 * @param WP_Post $post Lead post.
	 * @return void
	 */
	public function render_details_meta_box( $post ) {
		$type    = get_post_meta( $post->ID, '_ten_lead_type', true );
		$payload = get_post_meta( $post->ID, '_ten_lead_payload', true );
		$payload = is_array( $payload ) ? $payload : array();
		$labels  = $this->get_field_labels( $type );
		$expiry  = absint( get_post_meta( $post->ID, '_ten_retention_expires_gmt', true ) );
		$policy  = (string) get_post_meta( $post->ID, '_ten_retention_policy', true );
		$admin_email_status = (string) get_post_meta( $post->ID, '_ten_admin_email_status', true );
		$submitter_email_status = (string) get_post_meta( $post->ID, '_ten_submitter_email_status', true );

		echo '<p><strong>' . esc_html__( 'Lead type:', 'the-expat-network-homepage' ) . '</strong> ' . esc_html( ucfirst( (string) $type ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Retention:', 'the-expat-network-homepage' ) . '</strong> ' . esc_html( $policy );
		if ( $expiry ) {
			echo ' — ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry ) );
		}
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Email status:', 'the-expat-network-homepage' ) . '</strong> ' . esc_html( sprintf( 'Admin: %1$s | Submitter: %2$s', $admin_email_status, $submitter_email_status ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:1000px"><tbody>';

		foreach ( $labels as $key => $label ) {
			if ( ! array_key_exists( $key, $payload ) ) {
				continue;
			}

			$value = $payload[ $key ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			}

			echo '<tr><th style="width:260px">' . esc_html( $label ) . '</th><td>';
			if ( false !== strpos( $key, 'email' ) && is_email( $value ) ) {
				echo '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';
			} elseif ( in_array( $key, array( 'linkedin_url', 'company_website' ), true ) && $value ) {
				echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
			} else {
				echo nl2br( esc_html( (string) $value ) );
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'This record is private, unavailable through the front end and REST API, and is scheduled for permanent deletion when its retention deadline is reached.', 'the-expat-network-homepage' ) . '</p>';
	}

	/**
	 * Return readable labels for saved fields.
	 *
	 * @param string $type Lead type.
	 * @return array
	 */
	private function get_field_labels( $type ) {
		if ( 'partner' === $type ) {
			return array(
				'full_name'              => __( 'Full name', 'the-expat-network-homepage' ),
				'work_email'             => __( 'Email address', 'the-expat-network-homepage' ),
				'company_name'           => __( 'Company or organization', 'the-expat-network-homepage' ),
				'company_website'        => __( 'Website', 'the-expat-network-homepage' ),
				'opportunity_type'       => __( 'Partner interest', 'the-expat-network-homepage' ),
				'opportunity_type_other' => __( 'Other partner interest', 'the-expat-network-homepage' ),
				'message'                => __( 'Message', 'the-expat-network-homepage' ),
				'contact_consent'        => __( 'Contact acknowledgement', 'the-expat-network-homepage' ),
			);
		}

		return array(
			'first_name'                    => __( 'First name', 'the-expat-network-homepage' ),
			'last_name'                     => __( 'Last name', 'the-expat-network-homepage' ),
			'email'                         => __( 'Email address', 'the-expat-network-homepage' ),
			'current_country'               => __( 'Current country of residence', 'the-expat-network-homepage' ),
			'current_city'                  => __( 'Current city', 'the-expat-network-homepage' ),
			'current_situation'             => __( 'Current situation', 'the-expat-network-homepage' ),
			'current_situation_other'       => __( 'Other current situation', 'the-expat-network-homepage' ),
			'professional_role'             => __( 'Current or most recent professional role', 'the-expat-network-homepage' ),
			'experience_years'              => __( 'Years of relevant experience', 'the-expat-network-homepage' ),
			'professional_languages'        => __( 'Professional languages', 'the-expat-network-homepage' ),
			'german_level'                  => __( 'German language level', 'the-expat-network-homepage' ),
			'pathways'                      => __( 'Interests', 'the-expat-network-homepage' ),
			'linkedin_url'                  => __( 'LinkedIn profile', 'the-expat-network-homepage' ),
			'career_goal'                   => __( 'What the candidate is looking for', 'the-expat-network-homepage' ),
			'privacy_acknowledgement'       => __( 'Privacy acknowledgement', 'the-expat-network-homepage' ),
			'future_opportunity_permission' => __( 'Future opportunity permission', 'the-expat-network-homepage' ),
		);
	}

	/**
	 * Customize admin list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function filter_columns( $columns ) {
		return array(
			'cb'               => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'title'            => esc_html__( 'Lead', 'the-expat-network-homepage' ),
			'ten_type'         => esc_html__( 'Type', 'the-expat-network-homepage' ),
			'ten_email'        => esc_html__( 'Email', 'the-expat-network-homepage' ),
			'ten_summary'      => esc_html__( 'Interest / Partner Type', 'the-expat-network-homepage' ),
			'ten_retention'    => esc_html__( 'Retention expiry', 'the-expat-network-homepage' ),
			'ten_email_status' => esc_html__( 'Email status', 'the-expat-network-homepage' ),
			'date'             => esc_html__( 'Submitted', 'the-expat-network-homepage' ),
		);
	}

	/**
	 * Render custom admin list column.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Lead post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'ten_type' === $column ) {
			echo esc_html( ucfirst( (string) get_post_meta( $post_id, '_ten_lead_type', true ) ) );
		} elseif ( 'ten_email' === $column ) {
			$email = sanitize_email( get_post_meta( $post_id, '_ten_lead_email', true ) );
			if ( $email ) {
				echo '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
			}
		} elseif ( 'ten_summary' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_ten_lead_summary', true ) );
		} elseif ( 'ten_retention' === $column ) {
			$expiry = absint( get_post_meta( $post_id, '_ten_retention_expires_gmt', true ) );
			if ( $expiry ) {
				echo esc_html( wp_date( get_option( 'date_format' ), $expiry ) );
				if ( $expiry <= time() ) {
					echo '<br><strong>' . esc_html__( 'Expired', 'the-expat-network-homepage' ) . '</strong>';
				}
			}
		} elseif ( 'ten_email_status' === $column ) {
			$admin_status = (string) get_post_meta( $post_id, '_ten_admin_email_status', true );
			$submitter_status = (string) get_post_meta( $post_id, '_ten_submitter_email_status', true );
			echo esc_html( sprintf( 'A: %1$s / S: %2$s', $admin_status, $submitter_status ) );
		}
	}


	/**
	 * Make the retention-expiry column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['ten_retention'] = 'ten_retention';
		return $columns;
	}

	/**
	 * Apply numeric meta sorting for the private lead list.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function apply_admin_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( self::POST_TYPE !== $post_type || 'ten_retention' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_ten_retention_expires_gmt' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Remove actions that do not make sense for private lead records.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'], $actions['view'] );
		return $actions;
	}
}
