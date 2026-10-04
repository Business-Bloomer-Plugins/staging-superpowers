/* Link check: reads the database one table slice at a time and lists what it finds. */
( function () {
	var cfg = window.sspwLinkCheck, run = document.getElementById( 'sspw-link-run' );
	if ( ! cfg || ! run ) {
		return;
	}

	var status = document.getElementById( 'sspw-link-status' ), box = document.getElementById( 'sspw-link-results' ), limit = 50;

	function el( tag, text, attrs ) {
		var node = document.createElement( tag );
		if ( text ) {
			node.textContent = text;
		}
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function show( groups ) {
		box.textContent = '';
		var hosts = Object.keys( groups );
		if ( ! hosts.length ) {
			box.appendChild( el( 'p', cfg.i18n.none ) );
			return;
		}
		hosts.forEach( function ( host ) {
			var rows = groups[ host ];
			box.appendChild( el( 'h3', 1 === rows.length ? cfg.i18n.one.replace( '%s', host ) : cfg.i18n.group.replace( '%1$d', rows.length ).replace( '%2$s', host ) ) );
			var table = el( 'table', '', { 'class': 'widefat striped', style: 'max-width:1100px;margin-bottom:16px' } ), body = el( 'tbody' );
			rows.slice( 0, limit ).forEach( function ( row ) {
				var tr = el( 'tr' ), what = el( 'td', '', { style: 'width:34%' } ), where = el( 'td' ), snippet = el( 'code', '', { style: 'white-space:normal;word-break:break-all' } );
				what.appendChild( el( 'strong', row.label ) );
				if ( row.image ) {
					what.appendChild( el( 'br' ) );
					what.appendChild( document.createTextNode( cfg.i18n.image + ' ' + row.image ) );
				}
				if ( row.edit ) {
					what.appendChild( el( 'br' ) );
					what.appendChild( el( 'a', cfg.i18n.edit, { href: row.edit } ) );
				}
				snippet.appendChild( document.createTextNode( row.before ) );
				snippet.appendChild( el( 'mark', row.match ) );
				snippet.appendChild( document.createTextNode( row.after ) );
				where.appendChild( snippet );
				tr.appendChild( what );
				tr.appendChild( where );
				body.appendChild( tr );
			} );
			table.appendChild( body );
			box.appendChild( table );
			if ( rows.length > limit ) {
				box.appendChild( el( 'p', cfg.i18n.more.replace( '%d', rows.length - limit ) ) );
			}
		} );
	}

	run.addEventListener( 'click', function () {
		var groups = {}, step = 0, from = 0;
		run.disabled = true;
		box.textContent = '';

		function next() {
			if ( step >= cfg.steps.length ) {
				status.textContent = cfg.i18n.done;
				run.disabled = false;
				show( groups );
				return;
			}
			status.textContent = cfg.i18n.running;
			var data = new FormData();
			data.append( 'action', 'sspw_link_check' );
			data.append( '_ajax_nonce', cfg.nonce );
			data.append( 'step', cfg.steps[ step ] );
			data.append( 'from', from );
			fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( 'failed' );
					}
					json.data.found.forEach( function ( row ) {
						( groups[ row.host ] = groups[ row.host ] || [] ).push( row );
					} );
					if ( json.data.next ) {
						from = json.data.next;
					} else {
						step++;
						from = 0;
					}
					next();
				} )
				.catch( function () {
					status.textContent = cfg.i18n.failed;
					run.disabled = false;
				} );
		}

		next();
	} );
} )();
