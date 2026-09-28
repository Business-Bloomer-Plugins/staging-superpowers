( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;

	const settings = getSetting( 'sspw_test_data', {} );
	const label = decodeEntities( settings.title || 'Staging Test Gateway' );
	const Content = () => createElement( 'div', null, decodeEntities( settings.description || '' ) );

	registerPaymentMethod( {
		name: 'sspw_test',
		label: createElement( 'span', null, label ),
		ariaLabel: label,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		supports: { features: settings.supports || [ 'products' ] },
	} );
} )();
