/* global sppData, jQuery */
( function ( $ ) {
	'use strict';

	/* ── DOM refs ── */
	var $form     = $( '#spp-rule-form' );
	var $ruleId   = $( '#spp-rule-id' );
	var $products = $( '#spp-products' );
	var $cost     = $( '#spp-cost' );
	var $label    = $( '#spp-label' );
	var $submit   = $( '#spp-submit' );
	var $cancel   = $( '#spp-cancel-edit' );
	var $msg      = $( '#spp-msg' );
	var $table    = $( '#spp-rules-table' );

	/* ── Init Select2 with AJAX search ── */
	function initSelect2() {
		$products.select2( {
			ajax: {
				url:      sppData.ajaxUrl,
				type:     'GET',
				dataType: 'json',
				delay:    300,
				data: function ( params ) {
					return {
						action: 'spp_search_products',
						nonce:  sppData.nonces.search,
						q:      params.term  || '',
						page:   params.page  || 1,
					};
				},
				processResults: function ( data, params ) {
					params.page = params.page || 1;
					return {
						results:    data.results    || [],
						pagination: data.pagination || { more: false },
					};
				},
				cache: true,
			},
			placeholder:        'Search for products…',
			minimumInputLength: 0,
			allowClear:         true,
			width:              '100%',
		} );
	}

	/* ── Show feedback message ── */
	function showMsg( text, type ) {
		$msg
			.removeClass( 'spp-msg--success spp-msg--error' )
			.addClass( 'spp-msg--' + type )
			.text( text )
			.slideDown( 180 );

		if ( type === 'success' ) {
			setTimeout( function () { $msg.slideUp( 180 ); }, 3500 );
		}
	}

	/* ── Reset form to "add" state ── */
	function resetForm() {
		$ruleId.val( '' );
		$products.val( null ).trigger( 'change' );
		$cost.val( '' );
		$label.val( '' );
		setSubmitLabel( sppData.i18n.save );
		$cancel.hide();
		$msg.hide();
	}

	function setSubmitLabel( text ) {
		$submit.html(
			'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
			' stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>' +
			'<polyline points="17 21 17 13 7 13 7 21"/>' +
			'<polyline points="7 3 7 8 15 8"/></svg> ' + escHtml( text )
		);
	}

	/* ── Edit rule — populate form ── */
	$( document ).on( 'click', '.spp-edit-rule', function () {
		var $row = $( this ).closest( 'tr' );
		var ids  = JSON.parse( $row.attr( 'data-product-ids' ) || '[]' );
		var cost = $row.attr( 'data-cost' );
		var lbl  = $row.attr( 'data-label' );
		var rid  = $row.attr( 'data-rule-id' );

		$ruleId.val( rid );
		$cost.val( parseFloat( cost ).toFixed( 2 ) );
		$label.val( lbl );

		// Re-populate Select2: create <option> nodes for each existing product.
		$products.empty();
		var $tags = $row.find( '.spp-tag:not(.spp-tag--missing)' );
		ids.forEach( function ( id, index ) {
			var name = ( $tags.eq( index ).length ? $tags.eq( index ).text().trim() : 'Product' );
			var opt  = new Option( name + ' (#' + id + ')', id, true, true );
			$products.append( opt );
		} );
		$products.trigger( 'change' );

		setSubmitLabel( 'Update Rule' );
		$cancel.show();

		$( 'html, body' ).animate( { scrollTop: $form.offset().top - 80 }, 300 );
	} );

	/* ── Cancel edit ── */
	$cancel.on( 'click', resetForm );

	/* ── Delete rule ── */
	$( document ).on( 'click', '.spp-delete-rule', function () {
		if ( ! window.confirm( sppData.i18n.confirmDelete ) ) {
			return;
		}

		var $row   = $( this ).closest( 'tr' );
		var ruleId = $row.attr( 'data-rule-id' );

		$.post( sppData.ajaxUrl, {
			action:  'spp_delete_rule',
			nonce:   sppData.nonces.delete,   // action-specific nonce
			rule_id: ruleId,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				$row.addClass( 'spp-row-removing' );
				setTimeout( function () {
					$row.remove();
					updateCount();
					if ( $table.find( 'tbody tr' ).length === 0 ) {
						location.reload();
					}
				}, 260 );
			} else {
				window.alert( ( res.data && res.data.message ) || 'Error deleting rule.' );
			}
		} )
		.fail( function () {
			window.alert( sppData.i18n.networkError );
		} );
	} );

	/* ── Submit form ── */
	$form.on( 'submit', function ( e ) {
		e.preventDefault();

		var ids = $products.val();
		if ( ! ids || ! ids.length ) {
			showMsg( sppData.i18n.selectProduct, 'error' );
			return;
		}

		var costVal = parseFloat( $cost.val() );
		if ( isNaN( costVal ) || costVal < 0 ) {
			showMsg( sppData.i18n.invalidCost, 'error' );
			return;
		}

		var isEdit = !! $ruleId.val();
		$submit.prop( 'disabled', true ).text( sppData.i18n.saving );

		$.post( sppData.ajaxUrl, {
			action:      'spp_save_rule',
			nonce:       sppData.nonces.save,   // action-specific nonce
			product_ids: ids,
			cost:        costVal,
			label:       $label.val(),
			rule_id:     $ruleId.val(),
		} )
		.done( function ( res ) {
			$submit.prop( 'disabled', false );
			setSubmitLabel( isEdit ? 'Update Rule' : sppData.i18n.save );

			if ( ! res.success ) {
				showMsg( ( res.data && res.data.message ) || 'An error occurred.', 'error' );
				return;
			}

			showMsg( res.data.message, 'success' );

			if ( isEdit ) {
				updateRow( res.data );
			} else {
				// Reload to render the new DB row with correct IDs.
				setTimeout( function () { location.reload(); }, 900 );
			}

			resetForm();
		} )
		.fail( function () {
			$submit.prop( 'disabled', false );
			setSubmitLabel( isEdit ? 'Update Rule' : sppData.i18n.save );
			showMsg( sppData.i18n.networkError, 'error' );
		} );
	} );

	/* ── Update an existing table row after a successful edit ── */
	function updateRow( data ) {
		var $row = $table.find( 'tr[data-rule-id="' + data.rule_id + '"]' );
		if ( ! $row.length ) {
			return;
		}

		$row.attr( 'data-cost',        data.cost );
		$row.attr( 'data-label',       data.label );
		$row.attr( 'data-product-ids', JSON.stringify( data.product_ids ) );

		var labelHtml = data.label
			? '<span class="spp-rule-label">'            + escHtml( data.label ) + '</span>'
			: '<span class="spp-rule-label spp-rule-label--empty">&mdash;</span>';
		$row.find( '.spp-td-label' ).html( labelHtml );

		var tagsHtml = '';
		if ( data.product_names && data.product_names.length ) {
			data.product_names.forEach( function ( name ) {
				tagsHtml += '<span class="spp-tag">' + escHtml( name ) + '</span>';
			} );
		} else {
			tagsHtml = '<span class="spp-tag spp-tag--missing">Product not found</span>';
		}
		$row.find( '.spp-product-tags' ).html( tagsHtml );

		$row.find( '.spp-td-cost strong' ).text(
			data.currency + parseFloat( data.cost ).toFixed( 2 )
		);
	}

	/* ── Keep rule count badge accurate ── */
	function updateCount() {
		var count = $table.find( 'tbody tr' ).length;
		$( '.spp-count' ).text( count + ( count === 1 ? ' rule' : ' rules' ) );
	}

	/* ── Safe HTML escaping for dynamic content ── */
	function escHtml( str ) {
		return $( '<div>' ).text( String( str ) ).html();
	}

	/* ── Boot ── */
	initSelect2();

} )( jQuery );
