/* global sppData, jQuery */
( function ( $ ) {
	'use strict';

	// =========================================================================
	// TAB 1 — Per-Product Rules
	// =========================================================================

	var $form     = $( '#spp-rule-form' );
	var $ruleId   = $( '#spp-rule-id' );
	var $products = $( '#spp-products' );
	var $cost     = $( '#spp-cost' );
	var $label    = $( '#spp-label' );
	var $submit   = $( '#spp-submit' );
	var $cancel   = $( '#spp-cancel-edit' );
	var $msg      = $( '#spp-msg' );
	var $table    = $( '#spp-rules-table' );

	// ── Select2 AJAX product search ──────────────────────────────────────────
	function initSelect2() {
		if ( ! $products.length ) return;

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

	// ── Feedback message ─────────────────────────────────────────────────────
	function showMsg( $el, text, type ) {
		$el.removeClass( 'spp-msg--success spp-msg--error' )
			.addClass( 'spp-msg--' + type )
			.text( text )
			.slideDown( 180 );
		if ( type === 'success' ) setTimeout( function () { $el.slideUp( 180 ); }, 3500 );
	}

	// ── Reset per-product form ────────────────────────────────────────────────
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
			'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
			' stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>' +
			'<polyline points="17 21 17 13 7 13 7 21"/>' +
			'<polyline points="7 3 7 8 15 8"/></svg> ' + escHtml( text )
		);
	}

	// ── Edit existing per-product rule ────────────────────────────────────────
	$( document ).on( 'click', '.spp-edit-rule', function () {
		var $row = $( this ).closest( 'tr' );
		var ids  = JSON.parse( $row.attr( 'data-product-ids' ) || '[]' );
		var cost = $row.attr( 'data-cost' );
		var lbl  = $row.attr( 'data-label' );
		var rid  = $row.attr( 'data-rule-id' );

		$ruleId.val( rid );
		$cost.val( parseFloat( cost ).toFixed( 2 ) );
		$label.val( lbl );
		$products.empty();
		var $tags = $row.find( '.spp-tag:not(.spp-tag--missing)' );
		ids.forEach( function ( id, idx ) {
			var name = ( $tags.eq( idx ).length ? $tags.eq( idx ).text().trim() : 'Product' );
			$products.append( new Option( name + ' (#' + id + ')', id, true, true ) );
		} );
		$products.trigger( 'change' );

		setSubmitLabel( 'Update Rule' );
		$cancel.show();
		$( 'html, body' ).animate( { scrollTop: $form.offset().top - 80 }, 300 );
	} );

	$cancel.on( 'click', resetForm );

	// ── Delete per-product rule ───────────────────────────────────────────────
	$( document ).on( 'click', '.spp-delete-rule', function () {
		if ( ! window.confirm( sppData.i18n.confirmDelete ) ) return;
		var $row   = $( this ).closest( 'tr' );
		var ruleId = $row.attr( 'data-rule-id' );

		$.post( sppData.ajaxUrl, { action: 'spp_delete_rule', nonce: sppData.nonces.delete, rule_id: ruleId } )
			.done( function ( res ) {
				if ( res.success ) {
					$row.addClass( 'spp-row-removing' );
					setTimeout( function () {
						$row.remove();
						updateProductCount();
						if ( $table.find( 'tbody tr' ).length === 0 ) location.reload();
					}, 260 );
				} else {
					window.alert( ( res.data && res.data.message ) || 'Error.' );
				}
			} )
			.fail( function () { window.alert( sppData.i18n.networkError ); } );
	} );

	// ── Submit per-product form ───────────────────────────────────────────────
	if ( $form.length ) {
		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			var ids = $products.val();
			if ( ! ids || ! ids.length ) { showMsg( $msg, sppData.i18n.selectProduct, 'error' ); return; }

			var costVal = parseFloat( $cost.val() );
			if ( isNaN( costVal ) || costVal < 0 ) { showMsg( $msg, sppData.i18n.invalidCost, 'error' ); return; }

			var isEdit = !! $ruleId.val();
			$submit.prop( 'disabled', true ).text( sppData.i18n.saving );

			$.post( sppData.ajaxUrl, {
				action:      'spp_save_rule',
				nonce:       sppData.nonces.save,
				product_ids: ids,
				cost:        costVal,
				label:       $label.val(),
				rule_id:     $ruleId.val(),
			} )
			.done( function ( res ) {
				$submit.prop( 'disabled', false );
				setSubmitLabel( isEdit ? 'Update Rule' : sppData.i18n.save );
				if ( ! res.success ) { showMsg( $msg, ( res.data && res.data.message ) || 'Error.', 'error' ); return; }
				showMsg( $msg, res.data.message, 'success' );
				if ( isEdit ) { updateProductRow( res.data ); } else { setTimeout( function () { location.reload(); }, 900 ); }
				resetForm();
			} )
			.fail( function () {
				$submit.prop( 'disabled', false );
				setSubmitLabel( isEdit ? 'Update Rule' : sppData.i18n.save );
				showMsg( $msg, sppData.i18n.networkError, 'error' );
			} );
		} );
	}

	function updateProductRow( data ) {
		var $row = $table.find( 'tr[data-rule-id="' + data.rule_id + '"]' );
		if ( ! $row.length ) return;
		$row.attr( { 'data-cost': data.cost, 'data-label': data.label, 'data-product-ids': JSON.stringify( data.product_ids ) } );
		$row.find( 'td:nth-child(1)' ).html( data.label
			? '<span class="spp-rule-label">' + escHtml( data.label ) + '</span>'
			: '<span class="spp-rule-label spp-rule-label--empty">&mdash;</span>' );
		var tags = ( data.product_names && data.product_names.length )
			? data.product_names.map( function ( n ) { return '<span class="spp-tag">' + escHtml( n ) + '</span>'; } ).join( '' )
			: '<span class="spp-tag spp-tag--missing">Not found</span>';
		$row.find( '.spp-product-tags' ).html( tags );
		$row.find( '.spp-td-cost strong, td:nth-child(3) strong' )
			.text( data.currency + parseFloat( data.cost ).toFixed( 2 ) );
	}

	function updateProductCount() {
		var c = $table.find( 'tbody tr' ).length;
		$( '.spp-count' ).first().text( c + ( c === 1 ? ' rule' : ' rules' ) );
	}

	// =========================================================================
	// TAB 2 — Weight-Based Rules
	// =========================================================================

	var $weightRows    = $( '#spp-weight-rows' );
	var $addRowBtn     = $( '#spp-add-weight-row' );
	var $saveWeightBtn = $( '#spp-save-weight-rules' );
	var $weightMsg     = $( '#spp-weight-msg' );

	// Row template — strip the outer <script> wrapper and get the HTML inside.
	function getRowTemplate() {
		var raw = $( '#spp-weight-row-template' ).html() || '';
		return raw.trim();
	}

	// ── Add blank row ─────────────────────────────────────────────────────────
	if ( $addRowBtn.length ) {
		$addRowBtn.on( 'click', function () {
			var $row = $( getRowTemplate() );
			// New rows have no DB id yet.
			$row.attr( 'data-id', '0' ).find( '.spp-wr-id' ).val( '0' );
			$weightRows.append( $row );
			$row.find( '.spp-wr-label' ).trigger( 'focus' );
		} );
	}

	// ── Duplicate row ─────────────────────────────────────────────────────────
	$( document ).on( 'click', '.spp-wr-duplicate', function () {
		var $src  = $( this ).closest( '.spp-weight-row' );
		var $copy = $src.clone();
		// Reset the id so it inserts as new.
		$copy.attr( 'data-id', '0' ).find( '.spp-wr-id' ).val( '0' );
		$src.after( $copy );
	} );

	// ── Delete row ────────────────────────────────────────────────────────────
	$( document ).on( 'click', '.spp-wr-delete', function () {
		var $row  = $( this ).closest( '.spp-weight-row' );
		var rowId = parseInt( $row.attr( 'data-id' ) || '0', 10 );

		if ( rowId > 0 ) {
			// Row exists in DB — confirm before removing from UI.
			if ( ! window.confirm( sppData.i18n.confirmDelete ) ) return;
		}

		$row.addClass( 'spp-row-removing' );
		setTimeout( function () { $row.remove(); }, 250 );
	} );

	// ── Save all weight rows (bulk) ───────────────────────────────────────────
	if ( $saveWeightBtn.length ) {
		$saveWeightBtn.on( 'click', function () {
			var rows = [];
			var valid = true;

			$weightRows.find( '.spp-weight-row' ).each( function ( i ) {
				var $r   = $( this );
				var min  = parseFloat( $r.find( '.spp-wr-min' ).val()  || 0 );
				var max  = parseFloat( $r.find( '.spp-wr-max' ).val()  || 0 );
				var cost = parseFloat( $r.find( '.spp-wr-cost' ).val() || 0 );
				var lbl  = $r.find( '.spp-wr-label' ).val() || '';

				// Validate max > min when max is non-zero.
				if ( max > 0 && max <= min ) {
					showMsg( $weightMsg,
						'Row ' + ( i + 1 ) + ': ' + sppData.i18n.invalidWeight,
						'error' );
					valid = false;
					return false; // break each()
				}

				rows.push( { min: min, max: max, cost: cost, label: lbl } );
			} );

			if ( ! valid ) return;

			$saveWeightBtn.prop( 'disabled', true ).text( sppData.i18n.saving );

			$.post( sppData.ajaxUrl, {
				action: 'spp_save_weight_rules',
				nonce:  sppData.nonces.saveWeightRules,
				rows:   rows,
			} )
			.done( function ( res ) {
				$saveWeightBtn.prop( 'disabled', false )
					.html( '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> ' + escHtml( sppData.i18n.saveWeightRules ) );

				if ( res.success ) {
					showMsg( $weightMsg, res.data.message, 'success' );
					// Reload so newly inserted rows get their real DB ids.
					setTimeout( function () { location.reload(); }, 1200 );
				} else {
					showMsg( $weightMsg, ( res.data && res.data.message ) || 'Error.', 'error' );
				}
			} )
			.fail( function () {
				$saveWeightBtn.prop( 'disabled', false );
				showMsg( $weightMsg, sppData.i18n.networkError, 'error' );
			} );
		} );
	}

	// =========================================================================
	// Shared utilities
	// =========================================================================

	function escHtml( str ) {
		return $( '<div>' ).text( String( str ) ).html();
	}

	// ── Boot ──────────────────────────────────────────────────────────────────
	initSelect2();

} )( jQuery );
