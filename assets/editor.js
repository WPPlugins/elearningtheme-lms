/* eLearningTheme LMS: editor previews for the server-rendered blocks. No build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	( window.eltlmsBlocks || [] ).forEach( function ( b ) {
		wp.blocks.registerBlockType( b.name, {
			apiVersion: 3,
			title: b.title,
			category: 'elearningtheme-lms',
			icon: 'welcome-learn-more',
			edit: function ( props ) {
				var controls = null;
				if ( b.attributes.indexOf( 'perPage' ) !== -1 ) {
					controls = el( InspectorControls, null,
						el( PanelBody, { title: __( 'Settings', 'elearningtheme-lms' ) },
							el( RangeControl, { label: __( 'Courses to show', 'elearningtheme-lms' ), min: 1, max: 48, value: props.attributes.perPage, onChange: function ( v ) { props.setAttributes( { perPage: v } ); } } ),
							el( ToggleControl, { label: __( 'Show categories and search', 'elearningtheme-lms' ), checked: props.attributes.showFilters, onChange: function ( v ) { props.setAttributes( { showFilters: v } ); } } )
						)
					);
				}
				return el( 'div', useBlockProps(), controls, el( SSR, { block: b.name, attributes: props.attributes } ) );
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
