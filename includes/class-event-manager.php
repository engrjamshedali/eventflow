<?php
/**
 * EventFlow Event Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventFlow_Event_Manager {

	/**
	 * Get currently active event
	 *
	 * @return object|null
	 */
	public static function get_active_event() {
		global $wpdb;
		$table = EventFlow_DB::table( 'events' );

		// Check explicit active ID option first
		$active_id = (int) get_option( 'eventflow_active_event_id', 0 );
		if ( $active_id > 0 ) {
			$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $active_id ) );
			if ( $event ) {
				return $event;
			}
		}

		// Fallback to first status = 'active'
		$event = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id DESC LIMIT 1" );
		if ( $event ) {
			return $event;
		}

		// Fallback to latest event
		return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1" );
	}

	/**
	 * Get event by ID
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_event( $id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'events' );
		$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
		if ( $event ) {
			if ( ! isset( $event->fee_per_participant ) && isset( $event->team_fee_per_member ) ) {
				$event->fee_per_participant = $event->team_fee_per_member;
			}
			if ( ! isset( $event->fixed_team_fee ) && isset( $event->team_fixed_fee ) ) {
				$event->fixed_team_fee = $event->team_fixed_fee;
			}
		}
		return $event;
	}

	/**
	 * Get all events
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_all_events( $args = array() ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'events' );

		$status = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : '';
		if ( ! empty( $status ) ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC", $status ) );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	/**
	 * Get events (alias for get_all_events)
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_events( $args = array() ) {
		return self::get_all_events( $args );
	}

	/**
	 * Check if registration is open for an event
	 *
	 * @param object|null $event
	 * @return bool
	 */
	public static function is_registration_open( $event = null ) {
		if ( ! $event ) {
			$event = self::get_active_event();
		}
		if ( ! $event ) {
			return false;
		}

		// System level master switch
		$system_closed = get_option( 'eventflow_system_closed', 'no' );
		if ( 'yes' === $system_closed ) {
			return false;
		}

		if ( 'active' !== $event->status && 'open' !== $event->status ) {
			return false;
		}

		$now = current_time( 'timestamp' );

		if ( ! empty( $event->reg_start_date ) ) {
			$start = strtotime( $event->reg_start_date );
			if ( $now < $start ) {
				return false;
			}
		}

		if ( ! empty( $event->reg_end_date ) ) {
			$end = strtotime( $event->reg_end_date );
			if ( $now > $end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get registration status text & badge class
	 *
	 * @param object|null $event
	 * @return array
	 */
	public static function get_registration_status( $event = null ) {
		if ( ! $event ) {
			$event = self::get_active_event();
		}
		if ( ! $event ) {
			return array(
				'code'  => 'none',
				'label' => __( 'No Event Active', 'eventflow' ),
				'class' => 'ef-badge-neutral',
			);
		}

		$system_closed = get_option( 'eventflow_system_closed', 'no' );
		if ( 'yes' === $system_closed || 'closed' === $event->status ) {
			return array(
				'code'  => 'closed',
				'label' => __( 'Registration Closed', 'eventflow' ),
				'class' => 'ef-badge-danger',
			);
		}

		$now = current_time( 'timestamp' );

		if ( ! empty( $event->reg_start_date ) && $now < strtotime( $event->reg_start_date ) ) {
			return array(
				'code'  => 'coming_soon',
				'label' => __( 'Opening Soon', 'eventflow' ),
				'class' => 'ef-badge-warning',
			);
		}

		if ( ! empty( $event->reg_end_date ) && $now > strtotime( $event->reg_end_date ) ) {
			return array(
				'code'  => 'closed',
				'label' => __( 'Registration Closed', 'eventflow' ),
				'class' => 'ef-badge-danger',
			);
		}

		return array(
			'code'  => 'open',
			'label' => __( 'Registration Open', 'eventflow' ),
			'class' => 'ef-badge-success',
		);
	}

	/**
	 * Save or update event
	 *
	 * @param array $data
	 * @param int   $id
	 * @return int|WP_Error
	 */
	public static function save_event( $data, $id = 0 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'events' );

		$title = sanitize_text_field( $data['title'] );
		$slug  = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $title );

		$fields = array(
			'title'                    => $title,
			'slug'                     => $slug,
			'description'              => wp_kses_post( isset( $data['description'] ) ? $data['description'] : '' ),
			'banner_url'               => isset( $data['banner_url'] ) ? esc_url_raw( $data['banner_url'] ) : '',
			'logo_url'                 => isset( $data['logo_url'] ) ? esc_url_raw( $data['logo_url'] ) : '',
			'venue'                    => isset( $data['venue'] ) ? sanitize_text_field( $data['venue'] ) : 'Sukkur IBA University Main Campus',
			'university_name'          => isset( $data['university_name'] ) ? sanitize_text_field( $data['university_name'] ) : 'Sukkur IBA University',
			'organizing_dept'          => isset( $data['organizing_dept'] ) ? sanitize_text_field( $data['organizing_dept'] ) : 'Department of Computer Science',
			'website_url'              => isset( $data['website_url'] ) ? esc_url_raw( $data['website_url'] ) : home_url(),
			'start_date'               => ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
			'end_date'                 => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'reg_start_date'           => ! empty( $data['reg_start_date'] ) ? sanitize_text_field( $data['reg_start_date'] ) : null,
			'reg_end_date'             => ! empty( $data['reg_end_date'] ) ? sanitize_text_field( $data['reg_end_date'] ) : null,
			'start_time'               => isset( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : '09:00 AM',
			'end_time'                 => isset( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : '05:00 PM',
			'reporting_time'           => isset( $data['reporting_time'] ) ? sanitize_text_field( $data['reporting_time'] ) : '08:30 AM',
			'status'                   => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'contact_email'            => isset( $data['contact_email'] ) ? sanitize_email( $data['contact_email'] ) : 'info@portal.sibacom.tech',
			'contact_phone'            => isset( $data['contact_phone'] ) ? sanitize_text_field( $data['contact_phone'] ) : '+92 300 1234567',
			'allow_solo'               => isset( $data['allow_solo'] ) ? ( $data['allow_solo'] ? 1 : 0 ) : 1,
			'allow_team'               => isset( $data['allow_team'] ) ? ( $data['allow_team'] ? 1 : 0 ) : 1,
			'min_team_size'            => isset( $data['min_team_size'] ) ? max( 1, intval( $data['min_team_size'] ) ) : 2,
			'max_team_size'            => isset( $data['max_team_size'] ) ? max( 1, intval( $data['max_team_size'] ) ) : 5,
			'min_solo_registrations'   => isset( $data['min_solo_registrations'] ) ? max( 0, intval( $data['min_solo_registrations'] ) ) : 1,
			'max_total_registrations'  => isset( $data['max_total_registrations'] ) ? max( 0, intval( $data['max_total_registrations'] ) ) : 500,
			'max_team_registrations'   => isset( $data['max_team_registrations'] ) ? max( 0, intval( $data['max_team_registrations'] ) ) : 200,
			'allow_reg_edit'           => isset( $data['allow_reg_edit'] ) ? ( $data['allow_reg_edit'] ? 1 : 0 ) : 1,
			'allow_proof_reupload'     => isset( $data['allow_proof_reupload'] ) ? ( $data['allow_proof_reupload'] ? 1 : 0 ) : 1,
			'pricing_model'            => isset( $data['pricing_model'] ) ? sanitize_text_field( $data['pricing_model'] ) : 'per_participant',
			'reg_fee'                  => isset( $data['reg_fee'] ) ? floatval( $data['reg_fee'] ) : 0.00,
			'solo_fee_enabled'         => isset( $data['solo_fee_enabled'] ) ? ( $data['solo_fee_enabled'] ? 1 : 0 ) : 1,
			'solo_fee'                 => isset( $data['solo_fee'] ) ? floatval( $data['solo_fee'] ) : 500.00,
			'solo_discount'            => isset( $data['solo_discount'] ) ? floatval( $data['solo_discount'] ) : 0.00,
			'team_fee_enabled'         => isset( $data['team_fee_enabled'] ) ? ( $data['team_fee_enabled'] ? 1 : 0 ) : 1,
			'team_fee_per_member'      => isset( $data['fee_per_participant'] ) ? floatval( $data['fee_per_participant'] ) : ( isset( $data['team_fee_per_member'] ) ? floatval( $data['team_fee_per_member'] ) : 500.00 ),
			'team_fixed_fee'           => isset( $data['fixed_team_fee'] ) ? floatval( $data['fixed_team_fee'] ) : ( isset( $data['team_fixed_fee'] ) ? floatval( $data['team_fixed_fee'] ) : 2000.00 ),
			'team_discount'            => isset( $data['team_discount'] ) ? floatval( $data['team_discount'] ) : 0.00,
			'team_min_payable'         => isset( $data['team_min_payable'] ) ? floatval( $data['team_min_payable'] ) : 500.00,
			'currency'                 => isset( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : 'PKR',
			'accepted_payment_methods' => isset( $data['accepted_payment_methods'] ) ? ( is_array( $data['accepted_payment_methods'] ) ? implode( ',', array_map( 'sanitize_text_field', $data['accepted_payment_methods'] ) ) : sanitize_text_field( $data['accepted_payment_methods'] ) ) : 'bank,jazzcash,easypaisa',
			'bank_account_title'       => isset( $data['bank_account_title'] ) ? sanitize_text_field( $data['bank_account_title'] ) : '',
			'bank_name'                => isset( $data['bank_name'] ) ? sanitize_text_field( $data['bank_name'] ) : '',
			'bank_account_number'      => isset( $data['bank_account_number'] ) ? sanitize_text_field( $data['bank_account_number'] ) : ( isset( $data['bank_account_no'] ) ? sanitize_text_field( $data['bank_account_no'] ) : '' ),
			'bank_iban'                => isset( $data['bank_iban'] ) ? sanitize_text_field( $data['bank_iban'] ) : '',
			'easypaisa_details'        => isset( $data['easypaisa_details'] ) ? sanitize_text_field( $data['easypaisa_details'] ) : ( isset( $data['easypaisa_no'] ) ? sanitize_text_field( $data['easypaisa_no'] ) : '' ),
			'jazzcash_details'         => isset( $data['jazzcash_details'] ) ? sanitize_text_field( $data['jazzcash_details'] ) : ( isset( $data['jazzcash_no'] ) ? sanitize_text_field( $data['jazzcash_no'] ) : '' ),
			'raast_id'                 => isset( $data['raast_id'] ) ? sanitize_text_field( $data['raast_id'] ) : '',
			'payment_instructions'     => isset( $data['payment_instructions'] ) ? sanitize_textarea_field( $data['payment_instructions'] ) : '',
			'payment_deadline_hours'   => isset( $data['payment_deadline_hours'] ) ? max( 1, intval( $data['payment_deadline_hours'] ) ) : 48,
			'payment_info'             => isset( $data['payment_info'] ) ? sanitize_textarea_field( $data['payment_info'] ) : '',
			'qr_code_url'              => isset( $data['qr_code_url'] ) ? esc_url_raw( $data['qr_code_url'] ) : '',
			'updated_at'               => current_time( 'mysql' ),
		);

		// Synchronize global payment settings options for frontend payment displays
		if ( isset( $data['bank_name'] ) ) {
			update_option( 'eventflow_bank_name', sanitize_text_field( $data['bank_name'] ) );
		}
		if ( isset( $data['bank_account_title'] ) ) {
			update_option( 'eventflow_bank_account_title', sanitize_text_field( $data['bank_account_title'] ) );
		}
		if ( isset( $data['bank_account_no'] ) || isset( $data['bank_account_number'] ) ) {
			$acct_no = isset( $data['bank_account_no'] ) ? $data['bank_account_no'] : $data['bank_account_number'];
			update_option( 'eventflow_bank_account_no', sanitize_text_field( $acct_no ) );
		}
		if ( isset( $data['bank_iban'] ) ) {
			update_option( 'eventflow_bank_iban', sanitize_text_field( $data['bank_iban'] ) );
		}
		if ( isset( $data['bank_enabled'] ) ) {
			update_option( 'eventflow_bank_enabled', $data['bank_enabled'] ? 'yes' : 'no' );
		}
		if ( isset( $data['jazzcash_title'] ) ) {
			update_option( 'eventflow_jazzcash_title', sanitize_text_field( $data['jazzcash_title'] ) );
		}
		if ( isset( $data['jazzcash_no'] ) || isset( $data['jazzcash_details'] ) ) {
			$jc_no = isset( $data['jazzcash_no'] ) ? $data['jazzcash_no'] : $data['jazzcash_details'];
			update_option( 'eventflow_jazzcash_no', sanitize_text_field( $jc_no ) );
		}
		if ( isset( $data['jazzcash_enabled'] ) ) {
			update_option( 'eventflow_jazzcash_enabled', $data['jazzcash_enabled'] ? 'yes' : 'no' );
		}
		if ( isset( $data['easypaisa_title'] ) ) {
			update_option( 'eventflow_easypaisa_title', sanitize_text_field( $data['easypaisa_title'] ) );
		}
		if ( isset( $data['easypaisa_no'] ) || isset( $data['easypaisa_details'] ) ) {
			$ep_no = isset( $data['easypaisa_no'] ) ? $data['easypaisa_no'] : $data['easypaisa_details'];
			update_option( 'eventflow_easypaisa_no', sanitize_text_field( $ep_no ) );
		}
		if ( isset( $data['easypaisa_enabled'] ) ) {
			update_option( 'eventflow_easypaisa_enabled', $data['easypaisa_enabled'] ? 'yes' : 'no' );
		}
		if ( isset( $data['raast_id'] ) ) {
			update_option( 'eventflow_raast_id', sanitize_text_field( $data['raast_id'] ) );
		}
		if ( isset( $data['payment_instructions'] ) ) {
			update_option( 'eventflow_payment_instructions', sanitize_textarea_field( $data['payment_instructions'] ) );
		}
		if ( isset( $data['qr_code_url'] ) ) {
			update_option( 'eventflow_qr_code_url', esc_url_raw( $data['qr_code_url'] ) );
		}
		if ( isset( $data['currency'] ) ) {
			update_option( 'eventflow_currency', sanitize_text_field( $data['currency'] ) );
		}

		if ( $id > 0 ) {
			$updated = $wpdb->update( $table, $fields, array( 'id' => $id ) );
			EventFlow_Security::log_audit( 'event_updated', 'event', $id, $title );

			// Automated Trigger: Event Updated notification to registered participants
			$notify = isset( $data['notify_participants'] ) ? (bool) $data['notify_participants'] : false;
			if ( $notify && class_exists( 'EventFlow_Email_Manager' ) ) {
				$table_reg   = EventFlow_DB::table( 'registrations' );
				$table_teams = EventFlow_DB::table( 'teams' );
				$participants = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT t.leader_email, t.leader_name, t.team_name, r.reg_id
						 FROM {$table_reg} r
						 JOIN {$table_teams} t ON t.registration_id = r.id
						 WHERE r.event_id = %d",
						$id
					)
				);
				if ( ! empty( $participants ) ) {
					foreach ( $participants as $p ) {
						if ( empty( $p->leader_email ) || ! is_email( $p->leader_email ) ) {
							continue;
						}
						EventFlow_Email_Manager::send(
							'event_update',
							$p->leader_email,
							array(
								'participant_name'     => $p->leader_name ? $p->leader_name : 'Participant',
								'participant_email'    => $p->leader_email,
								'team_name'            => $p->team_name ? $p->team_name : '',
								'registration_id'      => $p->reg_id,
								'event_name'           => $title,
								'announcement_content' => sprintf( __( 'Important update: Event details and schedule for "%s" have been updated. Please check your participant portal for full details.', 'eventflow' ), $title ),
								'website_url'          => EventFlow_Shortcodes::get_dashboard_url(),
							),
							array(
								'idempotency_key' => sprintf( 'event_update_%d_%s_%s', $id, md5( $p->leader_email ), date( 'Ymd' ) ),
								'registration_id' => $p->reg_id,
							)
						);
					}
				}
			}

			return $id;
		} else {
			$fields['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $fields );
			$new_id = $wpdb->insert_id;
			EventFlow_Security::log_audit( 'event_created', 'event', $new_id, $title );
			return $new_id;
		}
	}

	/**
	 * Get event announcements
	 *
	 * @param int $event_id
	 * @param int $limit
	 * @return array
	 */
	public static function get_announcements( $event_id, $limit = 5 ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'announcements' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d AND is_active = 1 ORDER BY id DESC LIMIT %d",
				$event_id,
				$limit
			)
		);
	}

	/**
	 * Delete an announcement
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_announcement( $id ) {
		global $wpdb;
		$table = EventFlow_DB::table( 'announcements' );
		$id    = absint( $id );
		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( $deleted ) {
			EventFlow_Security::log_audit( 'announcement_deleted', 'announcement', $id );
		}
		return (bool) $deleted;
	}

	/**
	 * Dynamically calculate registration fee based on Event and Activity configuration
	 *
	 * Models supported:
	 * 1. 'per_participant' / 'per_person': rate * count - discount (enforcing min_payable)
	 * 2. 'fixed_team' / 'per_team': fixed_fee - discount (enforcing min_payable)
	 * 3. 'individual': solo_fee (or activity reg_fee)
	 *
	 * Returns an immutable snapshot breakdown array for historical integrity.
	 *
	 * @param int|object  $event_or_id
	 * @param string      $type 'solo' or 'team'
	 * @param int         $member_count
	 * @param object|null $activity Optional activity object
	 * @return array
	 */
	public static function calculate_fee( $event_or_id, $type = 'solo', $member_count = 1, $activity = null ) {
		$event = is_object( $event_or_id ) ? $event_or_id : self::get_event( $event_or_id );
		if ( ! $event ) {
			$event = self::get_active_event();
		}

		$currency     = $event && ! empty( $event->currency ) ? $event->currency : 'PKR';
		$member_count = max( 1, intval( $member_count ) );

		// Helper to format return payload with complete backward & forward compatibility
		$format_return = function( $data ) {
			$data['fee']              = floatval( $data['total_fee'] );
			$data['fee_breakdown']    = $data;
			$data['pricing_snapshot'] = isset( $data['snapshot'] ) ? $data['snapshot'] : array();
			return $data;
		};

		// Solo registration calculation
		if ( 'solo' === $type ) {
			$solo_enabled = isset( $event->solo_fee_enabled ) ? (bool) $event->solo_fee_enabled : true;
			if ( ! $solo_enabled ) {
				return $format_return( array(
					'total_fee'       => 0.00,
					'subtotal'        => 0.00,
					'discount'        => 0.00,
					'pricing_model'   => 'free',
					'rate_per_unit'   => 0.00,
					'units'           => 1,
					'currency'        => $currency,
					'breakdown_label' => __( 'Free Individual Registration', 'eventflow' ),
					'snapshot'        => array(
						'model'       => 'free',
						'type'        => 'solo',
						'base_rate'   => 0.00,
						'count'       => 1,
						'discount'    => 0.00,
						'calculated'  => 0.00,
						'currency'    => $currency,
						'snapshot_at' => current_time( 'mysql' ),
					),
				) );
			}

			$rate = 0.00;
			if ( $activity && isset( $activity->reg_fee ) ) {
				$rate = floatval( $activity->reg_fee );
			} elseif ( $event && isset( $event->solo_fee ) ) {
				$rate = floatval( $event->solo_fee );
			} elseif ( $event && isset( $event->reg_fee ) ) {
				$rate = floatval( $event->reg_fee );
			}

			$discount = ( $event && isset( $event->solo_discount ) ) ? floatval( $event->solo_discount ) : 0.00;
			$total    = max( 0.00, $rate - $discount );

			return $format_return( array(
				'total_fee'       => floatval( $total ),
				'subtotal'        => floatval( $rate ),
				'discount'        => floatval( $discount ),
				'pricing_model'   => 'individual',
				'rate_per_unit'   => floatval( $rate ),
				'units'           => 1,
				'currency'        => $currency,
				'breakdown_label' => sprintf( '%s %s', $currency, number_format( $total, 2 ) ),
				'snapshot'        => array(
					'model'       => 'individual',
					'type'        => 'solo',
					'base_rate'   => $rate,
					'count'       => 1,
					'discount'    => $discount,
					'calculated'  => $total,
					'currency'    => $currency,
					'snapshot_at' => current_time( 'mysql' ),
				),
			) );
		}

		// Team Registration Calculation
		$team_enabled = isset( $event->team_fee_enabled ) ? (bool) $event->team_fee_enabled : true;
		if ( ! $team_enabled ) {
			return $format_return( array(
				'total_fee'       => 0.00,
				'subtotal'        => 0.00,
				'discount'        => 0.00,
				'pricing_model'   => 'free',
				'rate_per_unit'   => 0.00,
				'units'           => $member_count,
				'currency'        => $currency,
				'breakdown_label' => __( 'Free Team Registration', 'eventflow' ),
				'snapshot'        => array(
					'model'       => 'free',
					'type'        => 'team',
					'base_rate'   => 0.00,
					'count'       => $member_count,
					'discount'    => 0.00,
					'calculated'  => 0.00,
					'currency'    => $currency,
					'snapshot_at' => current_time( 'mysql' ),
				),
			) );
		}

		// Check if activity overrides calculation model
		$activity_calc = ( $activity && ! empty( $activity->fee_calculation ) ) ? $activity->fee_calculation : '';
		$event_model   = ( $event && ! empty( $event->pricing_model ) ) ? $event->pricing_model : 'per_participant';

		$model = 'per_participant';
		if ( 'per_team' === $activity_calc || 'fixed_team' === $event_model ) {
			$model = 'fixed_team';
		} elseif ( 'per_person' === $activity_calc || 'per_participant' === $event_model ) {
			$model = 'per_participant';
		} elseif ( 'individual' === $event_model ) {
			$model = 'individual';
		}

		$discount    = ( $event && isset( $event->team_discount ) ) ? floatval( $event->team_discount ) : 0.00;
		$min_payable = ( $event && isset( $event->team_min_payable ) ) ? floatval( $event->team_min_payable ) : 0.00;

		if ( 'fixed_team' === $model ) {
			$base_fee = 0.00;
			if ( $activity && isset( $activity->reg_fee ) ) {
				$base_fee = floatval( $activity->reg_fee );
			} elseif ( $event && isset( $event->team_fixed_fee ) ) {
				$base_fee = floatval( $event->team_fixed_fee );
			} elseif ( $event && isset( $event->reg_fee ) ) {
				$base_fee = floatval( $event->reg_fee );
			}

			$subtotal = $base_fee;
			$total    = max( $min_payable, max( 0.00, $subtotal - $discount ) );
			$label    = sprintf( '%s %s (Fixed Team Fee)', $currency, number_format( $total, 2 ) );

			return $format_return( array(
				'total_fee'       => floatval( $total ),
				'subtotal'        => floatval( $subtotal ),
				'discount'        => floatval( $discount ),
				'pricing_model'   => 'fixed_team',
				'rate_per_unit'   => floatval( $base_fee ),
				'units'           => $member_count,
				'currency'        => $currency,
				'breakdown_label' => $label,
				'snapshot'        => array(
					'model'       => 'fixed_team',
					'type'        => 'team',
					'base_rate'   => $base_fee,
					'count'       => $member_count,
					'discount'    => $discount,
					'min_payable' => $min_payable,
					'calculated'  => $total,
					'currency'    => $currency,
					'snapshot_at' => current_time( 'mysql' ),
				),
			) );
		} else {
			// Per participant
			$rate_per_member = 0.00;
			if ( $activity && isset( $activity->reg_fee ) ) {
				$rate_per_member = floatval( $activity->reg_fee );
			} elseif ( $event && isset( $event->team_fee_per_member ) && floatval( $event->team_fee_per_member ) > 0 ) {
				$rate_per_member = floatval( $event->team_fee_per_member );
			} elseif ( $event && isset( $event->solo_fee ) && floatval( $event->solo_fee ) > 0 ) {
				$rate_per_member = floatval( $event->solo_fee );
			} elseif ( $event && isset( $event->reg_fee ) ) {
				$rate_per_member = floatval( $event->reg_fee );
			}

			$subtotal = $rate_per_member * $member_count;
			$total    = max( $min_payable, max( 0.00, $subtotal - $discount ) );
			$label    = sprintf( '%d × %s %s = %s %s', $member_count, $currency, number_format( $rate_per_member, 2 ), $currency, number_format( $total, 2 ) );

			return $format_return( array(
				'total_fee'       => floatval( $total ),
				'subtotal'        => floatval( $subtotal ),
				'discount'        => floatval( $discount ),
				'pricing_model'   => 'per_participant',
				'rate_per_unit'   => floatval( $rate_per_member ),
				'units'           => $member_count,
				'currency'        => $currency,
				'breakdown_label' => $label,
				'snapshot'        => array(
					'model'       => 'per_participant',
					'type'        => 'team',
					'base_rate'   => $rate_per_member,
					'count'       => $member_count,
					'discount'    => $discount,
					'min_payable' => $min_payable,
					'calculated'  => $total,
					'currency'    => $currency,
					'snapshot_at' => current_time( 'mysql' ),
				),
			) );
		}
	}
}

