( function ( $, mw ) {
	'use strict';

	function initTable( table ) {
		var $table = $( table );
		var config = tableConfig( table );

		if ( !config ) {
			return;
		}

		if ( !$.fn.DataTable ) {
			$table.before(
				$( '<div>' )
					.addClass( 'semantic-datatables-error' )
					.text( mw.msg( 'semanticdatatables-error-missing-datatables' ) )
			);
			return;
		}

		var ajax = !!config.ajax || !!config.serverSide;
		var serverSide = ajax;
		var columnDefs = columnDefsFromColumns( config.columns );
		var lastRequest = null;

		var options = $.extend( true, {}, config.options, {
			processing: true,
			serverSide: serverSide
		} );
		var initComplete = options.initComplete;
		var drawCallback = options.drawCallback;
		options.language = $.extend( true, {}, options.language );
		if ( !options.language.processing ) {
			options.language.processing = mw.msg( 'semanticdatatables-loading' );
		}

		options.initComplete = function () {
			if ( typeof initComplete === 'function' ) {
				initComplete.apply( this, arguments );
			}
			adjustColumns( this.api() );
		};

		options.drawCallback = function () {
			if ( typeof drawCallback === 'function' ) {
				drawCallback.apply( this, arguments );
			}
			adjustColumns( this.api() );
		};

		if ( ajax ) {
			options.buttons = ajaxCsvButtons( options.buttons, config, function () {
				return lastRequest;
			} );
			options.columns = config.columns;
			options.ajax = {
				url: mw.util.wikiScript( 'api' ),
				type: 'POST',
				dataSrc: 'data',
				data: function ( request, settings ) {
					request = requestWithSearchBuilder( request, settings );
					lastRequest = $.extend( true, {}, request );

					return {
						action: 'ext.semanticdatatables.query',
						format: 'json',
						context: config.context,
						serverSide: serverSide ? '1' : '0',
						request: JSON.stringify( request )
					};
				}
			};
		} else if ( columnDefs.length ) {
			options.columnDefs = ( options.columnDefs || [] ).concat( columnDefs );
		}

		var dataTable = $table.DataTable( options );
		enhanceProcessingIndicator( $table, dataTable, options.language.processing );

		if ( ajax ) {
			attachSearchBuilderProcessingIndicator( $table, dataTable );
			attachSearchBuilderRequestMerger( $table, function ( request ) {
				lastRequest = $.extend( true, {}, request );
			} );
		}
	}

	function requestWithSearchBuilder( request, settings ) {
		var details = searchBuilderDetails( settings );

		if ( !details ) {
			return request;
		}

		request = $.extend( true, {}, request );
		request.searchBuilder = details;

		return request;
	}

	function searchBuilderDetails( settings ) {
		var api, details;

		if ( !settings || !$.fn.dataTable || !$.fn.dataTable.Api ) {
			return null;
		}

		api = new $.fn.dataTable.Api( settings );
		if (
			!api.searchBuilder ||
			typeof api.searchBuilder.getDetails !== 'function'
		) {
			return null;
		}

		details = api.searchBuilder.getDetails( true );

		return details && typeof details === 'object' ? details : null;
	}

	function enhanceProcessingIndicator( $table, dataTable, message ) {
		var settings = dataTable.settings()[ 0 ];
		var $container = $( dataTable.table().container() );
		var $processing = $container.find( 'div.dt-processing' );

		if ( !$processing.length ) {
			return;
		}

		$processing
			.empty()
			.addClass( 'semantic-datatables-processing' )
			.attr( {
				role: 'status',
				'aria-live': 'polite'
			} )
			.append(
				$( '<span>' )
					.addClass( 'semantic-datatables-processing-spinner' )
					.attr( 'aria-hidden', 'true' )
			)
			.append(
				$( '<span>' )
					.addClass( 'semantic-datatables-processing-label' )
					.text( message )
			);

		$table.on( 'processing.dt.semanticDataTables', function ( e, eventSettings, processing ) {
			if ( eventSettings !== settings ) {
				return;
			}

			$container.toggleClass( 'semantic-datatables-is-processing', !!processing );
			if ( processing ) {
				$container.attr( 'aria-busy', 'true' );
			} else {
				$container.removeAttr( 'aria-busy' );
			}
		} );
	}

	function attachSearchBuilderProcessingIndicator( $table, dataTable ) {
		if ( !dataTable || typeof dataTable.processing !== 'function' ) {
			return;
		}

		var $container = $( dataTable.table().container() );
		var fallbackTimer = null;
		var requestStarted = false;
		var searchBuilderControls = [
			'div.dtsb-searchBuilder select',
			'div.dtsb-searchBuilder input',
			'div.dtsb-searchBuilder button.dtsb-search',
			'div.dtsb-searchBuilder button.dtsb-clearAll',
			'div.dtsb-searchBuilder button.dtsb-clearGroup',
			'div.dtsb-searchBuilder button.dtsb-delete',
			'div.dtsb-searchBuilder button.dtsb-left',
			'div.dtsb-searchBuilder button.dtsb-right',
			'div.dtsb-searchBuilder button.dtsb-logic'
		].join( ', ' );

		function clearFallback() {
			clearTimeout( fallbackTimer );
			fallbackTimer = null;
		}

		function showPendingProcessing() {
			requestStarted = false;
			clearFallback();
			dataTable.processing( true );

			fallbackTimer = setTimeout( function () {
				if ( !requestStarted ) {
					dataTable.processing( false );
				}
			}, 1500 );
		}

		$table
			.on( 'preXhr.dt.semanticDataTablesSearchBuilderProcessing', function () {
				requestStarted = true;
				clearFallback();
			} )
			.on( 'draw.dt.semanticDataTablesSearchBuilderProcessing xhr.dt.semanticDataTablesSearchBuilderProcessing error.dt.semanticDataTablesSearchBuilderProcessing', function () {
				requestStarted = false;
				clearFallback();
			} )
			.on( 'destroy.dt.semanticDataTablesSearchBuilderProcessing', function () {
				clearFallback();
				$container.off( '.semanticDataTablesSearchBuilderProcessing' );
				$table.off( '.semanticDataTablesSearchBuilderProcessing' );
			} );

		$container.on(
			'change.semanticDataTablesSearchBuilderProcessing input.semanticDataTablesSearchBuilderProcessing click.semanticDataTablesSearchBuilderProcessing',
			searchBuilderControls,
			showPendingProcessing
		);
	}

	function attachSearchBuilderRequestMerger( $table, setLastRequest ) {
		setTimeout( function () {
			$table.on( 'preXhr.dt.semanticDataTables', function ( e, settings, data ) {
				var request = mergeSearchBuilderRequest( data );

				if ( request ) {
					setLastRequest( request );
				}
			} );
		}, 0 );
	}

	function mergeSearchBuilderRequest( data ) {
		var request;

		if ( !data || data.searchBuilder === undefined ) {
			return null;
		}

		if ( typeof data.request !== 'string' ) {
			delete data.searchBuilder;
			return null;
		}

		try {
			request = JSON.parse( data.request );
		} catch ( e ) {
			if ( mw.log && mw.log.error ) {
				mw.log.error( 'Unable to parse SemanticDataTables ajax request.', e );
			}
			return null;
		}

		request.searchBuilder = data.searchBuilder;
		data.request = JSON.stringify( request );
		delete data.searchBuilder;

		return request;
	}

	function ajaxCsvButtons( buttons, tableConfig, getRequest ) {
		if ( !Array.isArray( buttons ) ) {
			return buttons;
		}

		return $.map( buttons, function ( button ) {
			return ajaxCsvButton( button, tableConfig, getRequest );
		} );
	}

	function ajaxCsvButton( button, tableConfig, getRequest ) {
		var name = typeof button === 'string' ? button :
			( button && typeof button.extend === 'string' ? button.extend : '' );

		if ( name !== 'csv' && name !== 'csvHtml5' ) {
			return button;
		}

		if ( typeof button !== 'string' && typeof button.action === 'function' ) {
			return button;
		}

		button = typeof button === 'string' ? { extend: button } : $.extend( true, {}, button );
		button.action = function ( e, dataTable, node, buttonConfig, callback ) {
			exportAjaxCsv( tableConfig, getRequest, dataTable, node, buttonConfig, callback );
		};

		return button;
	}

	function exportAjaxCsv( tableConfig, getRequest, dataTable, node, buttonConfig, callback ) {
		var $button = $( node );
		var request = exportRequest( dataTable, getRequest );

		request.start = 0;
		request.length = -1;
		$button.prop( 'disabled', true ).addClass( 'disabled' );

		new mw.Api().post( {
			action: 'ext.semanticdatatables.query',
			format: 'json',
			context: tableConfig.context,
			serverSide: '1',
			exportAll: '1',
			request: JSON.stringify( request )
		} ).done( function ( response ) {
			downloadCsv(
				csvContent( response.data || [], dataTable, buttonConfig ),
				csvFilename( buttonConfig ),
				buttonConfig
			);
		} ).fail( function () {
			if ( mw.log && mw.log.error ) {
				mw.log.error( 'SemanticDataTables CSV export failed.', arguments );
			}
		} ).always( function () {
			$button.prop( 'disabled', false ).removeClass( 'disabled' );

			if ( typeof callback === 'function' ) {
				callback();
			}
		} );
	}

	function exportRequest( dataTable, getRequest ) {
		var request = getRequest();

		if ( request ) {
			return $.extend( true, {}, request );
		}

		if ( dataTable.ajax && typeof dataTable.ajax.params === 'function' ) {
			request = dataTable.ajax.params();
			if ( request && typeof request.request === 'string' ) {
				try {
					return JSON.parse( request.request );
				} catch ( e ) {
					if ( mw.log && mw.log.error ) {
						mw.log.error( 'Unable to parse SemanticDataTables ajax request.', e );
					}
				}
			}
		}

		return {};
	}

	function csvContent( rows, dataTable, buttonConfig ) {
		var columnIndexes = exportColumnIndexes( dataTable, buttonConfig );
		var lines = [];
		var newline = buttonConfig.newline || '\n';

		if ( buttonConfig.header !== false ) {
			lines.push( csvLine( exportHeaders( dataTable, columnIndexes ), buttonConfig ) );
		}

		$.each( rows, function ( rowIndex, row ) {
			lines.push( csvLine( exportRow( row, columnIndexes ), buttonConfig ) );
		} );

		if ( buttonConfig.footer ) {
			lines.push( csvLine( exportFooters( dataTable, columnIndexes ), buttonConfig ) );
		}

		return lines.join( newline );
	}

	function exportColumnIndexes( dataTable, buttonConfig ) {
		var columns = buttonConfig.exportOptions && buttonConfig.exportOptions.columns !== undefined ?
			dataTable.columns( buttonConfig.exportOptions.columns ) :
			dataTable.columns();

		return columns.indexes().toArray();
	}

	function exportHeaders( dataTable, columnIndexes ) {
		return $.map( dataTable.columns( columnIndexes ).header().toArray(), function ( header ) {
			return $( header ).text();
		} );
	}

	function exportFooters( dataTable, columnIndexes ) {
		return $.map( dataTable.columns( columnIndexes ).footer().toArray(), function ( footer ) {
			return footer ? $( footer ).text() : '';
		} );
	}

	function exportRow( row, columnIndexes ) {
		return $.map( columnIndexes, function ( columnIndex ) {
			return exportCell( row[ columnIndex ] );
		} );
	}

	function exportCell( cell ) {
		if ( cell && typeof cell === 'object' ) {
			if ( cell.filter !== undefined && cell.filter !== '' ) {
				return cell.filter;
			}

			if ( cell.display !== undefined ) {
				return $( '<div>' ).html( String( cell.display ) ).text();
			}

			if ( cell.sort !== undefined ) {
				return cell.sort;
			}
		}

		return cell === null || cell === undefined ? '' : cell;
	}

	function csvLine( values, buttonConfig ) {
		var separator = buttonConfig.fieldSeparator || ',';

		return $.map( values, function ( value ) {
			return csvField( value, buttonConfig );
		} ).join( separator );
	}

	function csvField( value, buttonConfig ) {
		var boundary = buttonConfig.fieldBoundary;
		var escape = buttonConfig.escapeChar;

		if ( boundary === undefined ) {
			boundary = '"';
		}
		if ( escape === undefined ) {
			escape = '"';
		}

		value = value === null || value === undefined ? '' : String( value );

		if ( boundary === null || boundary === '' ) {
			return value;
		}

		return boundary + value.split( boundary ).join( escape + boundary ) + boundary;
	}

	function downloadCsv( csv, filename, buttonConfig ) {
		var charset = buttonConfig.charset || 'utf-8';
		var blob = new Blob(
			[ buttonConfig.bom ? '\ufeff' : '', csv ],
			{ type: 'text/csv;charset=' + charset }
		);
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' );

		link.href = url;
		link.download = filename;
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		URL.revokeObjectURL( url );
	}

	function csvFilename( buttonConfig ) {
		var filename = buttonConfig.filename;
		var extension = buttonConfig.extension || '.csv';

		if ( typeof filename === 'function' ) {
			filename = filename();
		}
		if ( !filename || filename === '*' ) {
			filename = document.title || 'download';
		}

		filename = String( filename ).replace( /[<>:"/\\|?*\u0000-\u001f]/g, '' );

		if ( extension && filename.slice( -extension.length ).toLowerCase() !== extension.toLowerCase() ) {
			filename += extension;
		}

		return filename;
	}

	function adjustColumns( api ) {
		var run = function () {
			api.columns.adjust();

			if ( api.responsive && typeof api.responsive.recalc === 'function' ) {
				api.responsive.recalc();
			}
		};

		if ( window.requestAnimationFrame ) {
			window.requestAnimationFrame( run );
		} else {
			setTimeout( run, 0 );
		}
	}

	function tableConfig( table ) {
		var config = mw.config.get( table.id );

		if ( config ) {
			return config;
		}

		config = table.getAttribute( 'data-sdt-config' );
		if ( !config ) {
			return null;
		}

		try {
			return JSON.parse( config );
		} catch ( e ) {
			if ( mw.log && mw.log.error ) {
				mw.log.error( 'Unable to parse SemanticDataTables configuration.', e );
			}
			return null;
		}
	}

	function columnDefsFromColumns( columns ) {
		return $.map( columns || [], function ( column, index ) {
			var definition = {
				targets: index
			};

			if ( column.type ) {
				definition.type = column.type;
				return definition;
			}

			return null;
		} );
	}

	$( function () {
		$( 'table.semantic-datatables' ).each( function () {
			initTable( this );
		} );
	} );
}( jQuery, mediaWiki ) );
