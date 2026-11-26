/* eLearningTheme LMS admin: course builder and quiz editor. Uses jQuery UI Sortable from WordPress. */
( function ( $ ) {
	'use strict';

	/* Course builder ------------------------------------------------------ */
	var $wrap = $( '#eltlms-sections' );
	if ( $wrap.length ) {
		var serialize = function () {
			var data = [];
			$wrap.find( '.eltlms-b-section' ).each( function () {
				var items = [];
				$( this ).find( '.eltlms-b-item' ).each( function () { items.push( parseInt( $( this ).data( 'id' ), 10 ) ); } );
				data.push( { title: $( this ).find( '.eltlms-b-title' ).val(), items: items } );
			} );
			$( '#eltlms-curriculum' ).val( JSON.stringify( data ) );
		};
		var sortItems = function () {
			$wrap.find( '.eltlms-b-items' ).sortable( { connectWith: '.eltlms-b-items', handle: '.eltlms-b-handle', placeholder: 'eltlms-b-placeholder', update: serialize } );
		};
		$wrap.sortable( { handle: '.eltlms-b-shandle', items: '> .eltlms-b-section', update: serialize } );
		sortItems();

		$wrap.on( 'input', '.eltlms-b-title', serialize );
		$wrap.on( 'click', '.eltlms-b-remove', function () { $( this ).closest( '.eltlms-b-item' ).remove(); serialize(); } );
		$wrap.on( 'click', '.eltlms-b-sremove', function () {
			var $s = $( this ).closest( '.eltlms-b-section' );
			if ( $s.find( '.eltlms-b-item' ).length && ! window.confirm( window.eltlmsAdmin.removeSection ) ) { return; }
			$s.remove(); serialize();
		} );
		$( '#eltlms-b-addsection' ).on( 'click', function () {
			var n = $wrap.find( '.eltlms-b-section' ).length + 1;
			var $tpl = $wrap.find( '.eltlms-b-section' ).first().clone();
			if ( ! $tpl.length ) { return; }
			$tpl.find( '.eltlms-b-items' ).empty().removeClass( 'ui-sortable' );
			$tpl.find( '.eltlms-b-title' ).val( window.eltlmsAdmin.section.replace( '%d', n ) );
			$wrap.append( $tpl ); sortItems(); serialize();
			$tpl.find( '.eltlms-b-title' ).trigger( 'focus' ).select();
		} );
		$( '#eltlms-b-additem' ).on( 'click', function () {
			var $opt = $( '#eltlms-b-pick option:selected' );
			var id = parseInt( $opt.val(), 10 );
			if ( ! id ) { return; }
			if ( $wrap.find( '.eltlms-b-item[data-id="' + id + '"]' ).length ) { window.alert( window.eltlmsAdmin.already ); return; }
			$wrap.find( '.eltlms-b-items' ).last().append( $( $opt.attr( 'data-row' ) ) );
			serialize();
		} );
		serialize();
	}

	/* Quiz editor --------------------------------------------------------- */
	var $qs = $( '#eltlms-questions' );
	if ( $qs.length ) {
		var number = function () { $qs.find( '.eltlms-q-n' ).each( function ( i ) { $( this ).text( i + 1 ); } ); };
		var next = $qs.find( '.eltlms-q' ).length;
		$( '#eltlms-q-add' ).on( 'click', function () {
			var html = document.getElementById( 'eltlms-q-template' ).innerHTML.split( '__i__' ).join( 'n' + ( next++ ) );
			var $q = $( html );
			$q.find( 'input[type="radio"]' ).first().prop( 'checked', true );
			$qs.append( $q ); number();
			$q.find( '.large-text' ).trigger( 'focus' );
		} );
		$qs.on( 'click', '.eltlms-q-remove', function () { $( this ).closest( '.eltlms-q' ).remove(); number(); } );
		// Question type: answer lines are only for choice questions.
		$qs.on( 'change', '.eltlms-q-type', function () {
			var $f = $( this ).closest( '.eltlms-q' );
			$f.attr( 'class', $f.attr( 'class' ).replace( /\bis-[a-z_-]+/g, '' ).trim() + ' is-' + this.value );
		} );
		number();
	}
} )( jQuery );
