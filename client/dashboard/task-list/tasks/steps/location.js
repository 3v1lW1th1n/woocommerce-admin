/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from 'newspack-components';
import { Component, Fragment } from '@wordpress/element';

/**
 * WooCommerce dependencies
 */
import { Form } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import {
	StoreAddress,
	validateStoreAddress,
} from 'dashboard/components/settings/general/store-address';

export default class StoreLocation extends Component {
	constructor() {
		super( ...arguments );
		this.onSubmit = this.onSubmit.bind( this );
	}

	async onSubmit( values ) {
		const { onComplete, createNotice, isSettingsError, updateSettings } = this.props;

		await updateSettings( {
			general: {
				woocommerce_store_address: values.addressLine1,
				woocommerce_store_address_2: values.addressLine2,
				woocommerce_default_country: values.countryState,
				woocommerce_store_city: values.city,
				woocommerce_store_postcode: values.postCode,
			},
		} );

		if ( ! isSettingsError ) {
			onComplete( values );
		} else {
			createNotice(
				'error',
				__( 'There was a problem saving your store location.', 'woocommerce-admin' )
			);
		}
	}

	getInitialValues() {
		const { settings } = this.props;

		const {
			woocommerce_store_address,
			woocommerce_store_address_2,
			woocommerce_store_city,
			woocommerce_default_country,
			woocommerce_store_postcode,
		} = settings;

		return {
			addressLine1: woocommerce_store_address || '',
			addressLine2: woocommerce_store_address_2 || '',
			city: woocommerce_store_city || '',
			countryState: woocommerce_default_country || '',
			postCode: woocommerce_store_postcode || '',
		};
	}

	render() {
		const { isSettingsRequesting } = this.props;

		if ( isSettingsRequesting ) {
			return null;
		}

		return (
			<Form
				initialValues={ this.getInitialValues() }
				onSubmitCallback={ this.onSubmit }
				validate={ validateStoreAddress }
			>
				{ ( { getInputProps, handleSubmit, setValue } ) => (
					<Fragment>
						<StoreAddress getInputProps={ getInputProps } setValue={ setValue } />
						<Button isPrimary onClick={ handleSubmit }>
							{ __( 'Continue', 'woocommerce-admin' ) }
						</Button>
					</Fragment>
				) }
			</Form>
		);
	}
}
