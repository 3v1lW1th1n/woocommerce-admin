<?php
/**
 * WooCommerce Admin (Dashboard) WooCommerce.com Extension Subscriptions Note Provider.
 *
 * Adds notes to the merchant's inbox concerning WooCommerce.com extension subscriptions.
 *
 * @package WooCommerce Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin_Notes_Woo_Subscriptions_Notes
 */
class WC_Admin_Notes_Woo_Subscriptions_Notes {
	const CONNECTION_NOTE_NAME   = 'wc-admin-wc-helper-connection';
	const SUBSCRIPTION_NOTE_NAME = 'wc-admin-wc-helper-subscription';
	const NOTIFY_WHEN_DAYS_LEFT  = 460; // TODO: Put this back to 60.

	/**
	 * Hook all the things.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'remove_notes' ) ); // TODO For testing only. Do not commit this line.
		add_action( 'admin_init', array( $this, 'check_connection' ) );
		add_action( 'admin_init', array( $this, 'prune_inactive_subscription_notes' ) ); // TODO For testing only. Do not commit this line.
		add_action( 'admin_init', array( $this, 'refresh_subscription_notes' ) ); // TODO For testing only. Do not commit this line.
		add_action( 'update_option_woocommerce_helper_data', array( $this, 'update_option_woocommerce_helper_data' ), 10, 2 );
		// TODO : prune_inactive_subscription_notes daily.
		// TODO : refresh_subscription_notes daily.
	}

	/**
	 * Reacts to changes in the helper option.
	 *
	 * @param array $old_value The previous value of the option.
	 * @param array $value The new value of the option.
	 */
	public function update_option_woocommerce_helper_data( $old_value, $value ) {
		if ( ! is_array( $old_value ) ) {
			$old_value = array();
		}
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$old_auth  = array_key_exists( 'auth', $old_value ) ? $old_value['auth'] : array();
		$new_auth  = array_key_exists( 'auth', $value ) ? $value['auth'] : array();
		$old_token = array_key_exists( 'access_token', $old_auth ) ? $old_auth['access_token'] : '';
		$new_token = array_key_exists( 'access_token', $new_auth ) ? $new_auth['access_token'] : '';

		// The site just disconnected.
		if ( ! empty( $old_token ) && empty( $new_token ) ) {
			$this->remove_notes();
			$this->add_no_connection_note();
			return;
		}

		// The site just connected.
		if ( empty( $old_token ) && ! empty( $new_token ) ) {
			$this->remove_notes();
			$this->refresh_subscription_notes();
			return;
		}

		// TODO - refresh our notes if the user changes a subscription to active.
	}

	/**
	 * Checks the connection. Adds a note (as necessary) if there is no connection.
	 */
	public function check_connection() {
		if ( ! $this->is_connected() ) {
			$data_store = WC_Data_Store::load( 'admin-note' );
			$note_ids   = $data_store->get_notes_with_name( self::CONNECTION_NOTE_NAME );
			if ( ! empty( $note_ids ) ) {
				return;
			}

			$this->remove_notes();
			$this->add_no_connection_note();
		}
	}

	/**
	 * Whether or not we think the site is currently connected to WooCommerce.com.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$auth = WC_Helper_Options::get( 'auth' );
		return ( ! empty( $auth['access_token'] ) );
	}

	/**
	 * Returns the WooCommerce.com provided site ID for this site.
	 *
	 * @return int|false
	 */
	public function get_connected_site_id() {
		if ( ! $this->is_connected() ) {
			return false;
		}

		$auth = WC_Helper_Options::get( 'auth' );
		return absint( $auth['site_id'] );
	}

	/**
	 * Returns an array of product_ids whose subscriptions are active on this site.
	 *
	 * @return array
	 */
	public function get_subscription_active_product_ids() {
		$site_id = $this->get_connected_site_id();
		if ( ! $site_id ) {
			return array();
		}

		$product_ids = array();

		if ( $this->is_connected() ) {
			$subscriptions = WC_Helper::get_subscriptions();

			foreach ( (array) $subscriptions as $subscription ) {
				if ( in_array( $site_id, $subscription['connections'], true ) ) {
					$product_ids[] = $subscription['product_id'];
				}
			}
		}

		return $product_ids;
	}

	/**
	 * Clears all connection or subscription notes.
	 */
	public function remove_notes() {
		WC_Admin_Notes::delete_notes_with_name( self::CONNECTION_NOTE_NAME );
		WC_Admin_Notes::delete_notes_with_name( self::SUBSCRIPTION_NOTE_NAME );
	}

	/**
	 * Adds a note prompting to connect to WooCommerce.com.
	 */
	public function add_no_connection_note() {
		$note = new WC_Admin_Note();
		$note->set_title( __( 'Connect to WooCommerce.com', 'wc-admin' ) );
		$note->set_content( __( 'Connect to get important product notifications and updates.', 'wc-admin' ) );
		$note->set_content_data( (object) array() );
		$note->set_type( WC_Admin_Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_icon( 'info' );
		$note->set_name( self::CONNECTION_NOTE_NAME );
		$note->set_source( 'wc-admin' );
		$note->add_action(
			'connect',
			__( 'Connect', 'wc-admin' ),
			'?page=wc-addons&section=helper'
		);
		$note->save();
	}

	/**
	 * Gets the product_id (if any) associated with a note.
	 *
	 * @param WC_Admin_Note $note The note object to interrogate.
	 * @return int|false
	 */
	public function get_product_id_from_subscription_note( &$note ) {
		$content_data = $note->get_content_data();

		if ( property_exists( $content_data, 'product_id' ) ) {
			return intval( $content_data->product_id );
		}

		return false;
	}

	/**
	 * Removes notes for product_ids no longer active on this site.
	 */
	public function prune_inactive_subscription_notes() {
		$active_product_ids = $this->get_subscription_active_product_ids();

		$data_store = WC_Data_Store::load( 'admin-note' );
		$note_ids   = $data_store->get_notes_with_name( self::SUBSCRIPTION_NOTE_NAME );

		foreach ( (array) $note_ids as $note_id ) {
			$note       = WC_Admin_Notes::get_note( $note_id );
			$product_id = $this->get_product_id_from_subscription_note( $note );
			if ( ! empty( $product_id ) ) {
				if ( ! in_array( $product_id, $active_product_ids, true ) ) {
					$note->delete();
				}
			}
		}
	}

	/**
	 * Finds a note for a given product ID, if the note exists at all.
	 *
	 * @param int $product_id The product ID to search for.
	 * @return WC_Admin_Note|false
	 */
	public function find_note_for_product_id( $product_id ) {
		$product_id = intval( $product_id );

		$data_store = WC_Data_Store::load( 'admin-note' );
		$note_ids   = $data_store->get_notes_with_name( self::SUBSCRIPTION_NOTE_NAME );
		foreach ( (array) $note_ids as $note_id ) {
			$note             = WC_Admin_Notes::get_note( $note_id );
			$found_product_id = $this->get_product_id_from_subscription_note( $note );

			if ( $product_id === $found_product_id ) {
				return $note;
			}
		}

		return false;
	}

	/**
	 * Deletes a note for a given product ID, if the note exists at all.
	 *
	 * @param int $product_id The product ID to search for.
	 */
	public function delete_any_note_for_product_id( $product_id ) {
		$product_id = intval( $product_id );

		$note = $this->find_note_for_product_id( $product_id );
		if ( $note ) {
			$note->delete();
		}
	}

	/**
	 * Adds or updates a note for an expiring subscription.
	 *
	 * @param array $subscription The subscription to work with.
	 */
	public function add_or_update_subscription_expiring( $subscription ) {
		$product_id            = $subscription['product_id'];
		$product_name          = $subscription['product_name'];
		$expires               = intval( $subscription['expires'] );
		$time_now_gmt          = current_time( 'timestamp', 0 );
		$days_until_expiration = ceil( ( $expires - $time_now_gmt ) / DAY_IN_SECONDS );

		$note = $this->find_note_for_product_id( $product_id );

		$note_title = sprintf(
			/* translators: name of the extension subscription expiring soon */
			__( '%s subscription expiring soon', 'wc-admin' ),
			$product_name
		);

		$note_content = sprintf(
			/* translators: number of days until the subscription expires */
			__( 'Your subscription expires in %d days. Enable autorenew to avoid losing updates and access to support.', 'wc-admin' ),
			$days_until_expiration
		);

		$note_content_data = (object) array(
			'product_id'            => $product_id,
			'product_name'          => $product_name,
			'expired'               => false,
			'days_until_expiration' => $days_until_expiration,
		);

		if ( ! $note ) {
			$note = new WC_Admin_Note();
			$note->set_title( $note_title );
			$note->set_type( WC_Admin_Note::E_WC_ADMIN_NOTE_WARNING );
			$note->set_icon( 'notice' );
			$note->set_name( self::SUBSCRIPTION_NOTE_NAME );
			$note->set_source( 'wc-admin' );
			$note->add_action(
				'enable-autorenew',
				__( 'Enable Autorenew', 'wc-admin' ),
				'https://woocommerce.com/my-account/my-subscriptions/'
			);
		}

		$note->set_content( $note_content );
		$note->set_content_data( $note_content_data );
		$note->save();
	}

	/**
	 * Adds a note for an expired subscription, or updates an expiring note to expired.
	 *
	 * @param array $subscription The subscription to work with.
	 */
	public function add_or_update_subscription_expired( $subscription ) {
		$product_id   = $subscription['product_id'];
		$product_name = $subscription['product_name'];
		$product_page = $subscription['product_url'];
		$expires      = intval( $subscription['expires'] );
		$expires_date = date( 'F jS', $expires );

		$note = $this->find_note_for_product_id( $product_id );
		if ( $note ) {
			$note_content_data = $note->get_content_data();
			if ( $note_content_data->expired ) {
				// We've already got a full fledged expired note for this. Bail.
				// These notes' content doesn't change with time.
				return;
			}
		}

		$note_title = sprintf(
			/* translators: name of the extension subscription that expired */
			__( '%s subscription expired', 'wc-admin' ),
			$product_name
		);

		$note_content = sprintf(
			/* translators: date the subscription expired, e.g. Jun 7th 2018 */
			__( 'Your subscription expired on %s. Get a new subscription to continue receiving updates and access to support.', 'wc-admin' ),
			$expires_date
		);

		$note_content_data = (object) array(
			'product_id'   => $product_id,
			'product_name' => $product_name,
			'expired'      => true,
			'expires'      => $expires,
			'expires_date' => $expires_date,
		);

		if ( ! $note ) {
			$note = new WC_Admin_Note();
		}

		$note->set_title( $note_title );
		$note->set_content( $note_content );
		$note->set_content_data( $note_content_data );
		$note->set_type( WC_Admin_Note::E_WC_ADMIN_NOTE_WARNING );
		$note->set_icon( 'notice' );
		$note->set_name( self::SUBSCRIPTION_NOTE_NAME );
		$note->set_source( 'wc-admin' );
		$note->add_action(
			'renew-subscription',
			__( 'Renew Subscription', 'wc-admin' ),
			$product_page
		);
		$note->save();
	}

	/**
	 * For each active subscription on this site, checks the expiration date and creates/updates notes.
	 */
	public function refresh_subscription_notes() {
		if ( ! $this->is_connected() ) {
			return;
		}

		$subscriptions      = WC_Helper::get_subscriptions();
		$active_product_ids = $this->get_subscription_active_product_ids();

		foreach ( (array) $subscriptions as $subscription ) {
			// Only concern ourselves with active products.
			$product_id = $subscription['product_id'];
			if ( ! in_array( $product_id, $active_product_ids, true ) ) {
				continue;
			}

			// If the subscription will auto-renew, clean up and exit.
			if ( $subscription['autorenew'] ) {
				$this->delete_any_note_for_product_id( $product_id );
				continue;
			}

			// If the subscription is not expiring soon, clean up and exit.
			$expires      = intval( $subscription['expires'] );
			$time_now_gmt = current_time( 'timestamp', 0 );
			if ( $expires > $time_now_gmt + self::NOTIFY_WHEN_DAYS_LEFT * DAY_IN_SECONDS ) {
				$this->delete_any_note_for_product_id( $product_id );
				continue;
			}

			// Otherwise, if the subscription can still have auto-renew enabled, let them know that now.
			if ( $expires > $time_now_gmt ) {
				$this->add_or_update_subscription_expiring( $subscription );
				continue;
			}

			// If we got this far, the subscription has completely expired, let them know.
			$this->add_or_update_subscription_expired( $subscription );
		}
	}
}

new WC_Admin_Notes_Woo_Subscriptions_Notes();
