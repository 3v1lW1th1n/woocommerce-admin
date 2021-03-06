/** @format */
/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

/**
 * WooCommerce dependencies
 */
import { numberFormat } from '@woocommerce/number';
import { CURRENCY as currency } from '@woocommerce/wc-admin-settings';

/**
 * Formats money with a given currency code. Uses site's currency settings for formatting.
 *
 * @param   {Number|String} number number to format
 * @param   {String}        currencySymbol currency code e.g. '$'
 * @returns {?String} A formatted string.
 */
export function formatCurrency( number, currencySymbol ) {
	if ( ! currencySymbol ) {
		currencySymbol = currency.symbol;
	}

	const precision = currency.precision;
	const formattedNumber = numberFormat( number, precision );
	const priceFormat = currency.priceFormat;

	if ( '' === formattedNumber ) {
		return formattedNumber;
	}

	return sprintf( priceFormat, currencySymbol, formattedNumber );
}

/**
 * Get the rounded decimal value of a number at the precision used for the current currency.
 * This is a work-around for fraction-cents, meant to be used like `wc_format_decimal`
 *
 * @param {Number|String} number A floating point number (or integer), or string that converts to a number
 * @return {Number} The original number rounded to a decimal point
 */
export function getCurrencyFormatDecimal( number ) {
	const { precision = 2 } = currency;
	if ( 'number' !== typeof number ) {
		number = parseFloat( number );
	}
	if ( Number.isNaN( number ) ) {
		return 0;
	}
	return Math.round( number * Math.pow( 10, precision ) ) / Math.pow( 10, precision );
}

/**
 * Get the string representation of a floating point number to the precision used by the current currency.
 * This is different from `formatCurrency` by not returning the currency symbol.
 *
 * @param  {Number|String} number A floating point number (or integer), or string that converts to a number
 * @return {String}               The original number rounded to a decimal point
 */
export function getCurrencyFormatString( number ) {
	const { precision = 2 } = currency;
	if ( 'number' !== typeof number ) {
		number = parseFloat( number );
	}
	if ( Number.isNaN( number ) ) {
		return '';
	}
	return number.toFixed( precision );
}

export function renderCurrency( number, currencySymbol ) {
	if ( 'number' !== typeof number ) {
		number = parseFloat( number );
	}
	if ( number < 0 ) {
		return <span className="is-negative">{ formatCurrency( number, currencySymbol ) }</span>;
	}
	return formatCurrency( number, currencySymbol );
}

/**
 * Returns currency data by country/region. Contains code, position, thousands separator, decimal separator, and precision.
 *
 * @format
 * @return {object} Curreny data.
 */
export function getCurrencyData() {
	// See https://github.com/woocommerce/woocommerce-admin/issues/3101.
	return {
		US: {
			code: 'USD',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
		EU: {
			code: 'EUR',
			position: 'left',
			grouping: '.',
			decimal: ',',
			precision: 2,
		},
		IN: {
			code: 'INR',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
		GB: {
			code: 'GBP',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
		BR: {
			code: 'BRL',
			position: 'left',
			grouping: '.',
			decimal: ',',
			precision: 2,
		},
		VN: {
			code: 'VND',
			position: 'right',
			grouping: '.',
			decimal: ',',
			precision: 1,
		},
		ID: {
			code: 'IDR',
			position: 'left',
			grouping: '.',
			decimal: ',',
			precision: 0,
		},
		BD: {
			code: 'BDT',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 0,
		},
		PK: {
			code: 'PKR',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
		RU: {
			code: 'RUB',
			position: 'right',
			grouping: ' ',
			decimal: ',',
			precision: 2,
		},
		TR: {
			code: 'TRY',
			position: 'left',
			grouping: '.',
			decimal: ',',
			precision: 2,
		},
		MX: {
			code: 'MXN',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
		CA: {
			code: 'CAD',
			position: 'left',
			grouping: ',',
			decimal: '.',
			precision: 2,
		},
	};
}
