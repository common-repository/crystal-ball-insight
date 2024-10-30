'use strict';

jQuery( function( $ ) {
	var CBI = {
		$wrapper: {},
		$container: {},
		conter: 0,

		init: function () {
			var _this = this;

			CBI.$wrapper = $( ".cbi-notifier-settings" );
            CBI.$container = $( "ul", CBI.$wrapper );

			CBI.counter = CBI.$container.children().length;

            if ( 1 === CBI.counter ) {
                var $temp_el = CBI.$container.children().first();
                if ( 0 === $temp_el.find( ".cbi-value option" ).length ) {
                    setTimeout( function () {
                        $temp_el.find( ".cbi-category" ).change();
                    }, 300 );
                }
            }

            CBI.$container.on( 'click', '.cbi-new-rule', function ( e ) {
				e.preventDefault();
				_this.addRule( $( this ).closest( 'li' ) );
			});

            CBI.$container.on( 'click', '.cbi-delete-rule', function ( e ) {
				e.preventDefault();

                if ( 1 === CBI.$container.children().length ) {
                    return;
                }

				_this.deleteRule( $( this ).closest( 'li' ) );
			});

            CBI.$container.on( 'change', '.cbi-category', function ( e ) {
				e.preventDefault();

				var $select = $( this ),
					$siblings = $select.siblings( "select" );

				$siblings.filter( "select" ).prop( 'disabled', true );

				var data = _this.getData( $select.val(), function ( d ) {
					var $target = $siblings.filter( '.cbi-value' );
					$target.empty();

					$.each( d.data, function ( k, v ) {
						$target.append( $( "<option/>", {
							text: v,
							value: k
						} ) );
					});

					$siblings.filter( "select" ).prop( 'disabled', false );
				});
			});

		},
		addRule: function ( $el ) {
			this.counter++;
			var $copy = $el.clone(),
				curID = parseInt( $el.data( 'id' ), null ),
				newID = this.counter;

			$copy.find( '[name]' ).each( function() {
				$( this ).attr( 'name', $( this ).attr( 'name' ).replace( curID, newID ) );
			});

			$copy.attr( 'data-id', newID );
			$el.after( $copy );
		},
		deleteRule: function ( $el ) {
			$el.remove();
		},
		getData: function ( type, cb ) {
			var payload = {
				action: 'cbi_get_properties',
				action_category: type
			};
			$.getJSON( window.ajaxurl, payload, cb );
		}
	};

	CBI.init();

	window.CBI = CBI;

	/**
	 * Form serialization helper
	 */
	$.fn.CBISerializeObject = function() {
		var o = {};
		var a = this.serializeArray();
		$.each( a, function() {
			if ( o[this.name] !== undefined ) {
				if ( !o[this.name].push ) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push( this.value || '' );
			} else {
				o[this.name] = this.value || '';
			}
		} );
		return o;
	};
});
