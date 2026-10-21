/* People of the Word — lesson importer admin screen. */
( function () {
	'use strict';

	const $ = ( s ) => document.querySelector( s );
	const progressWrap = $( '#potw-li-progress' );
	const bar = progressWrap.querySelector( 'progress' );
	const status = $( '#potw-li-status' );
	const log = $( '#potw-li-log' );
	const summary = $( '#potw-li-summary' );
	const stopBtn = $( '[data-potw-stop]' );
	const buttons = document.querySelectorAll( '[data-potw-scan],[data-potw-resume],[data-potw-preview],[data-potw-import-one]' );
	let stopping = false;
	const counts = {};

	function post( action, data = {} ) {
		const body = new URLSearchParams( { action, nonce: POTW_LI.nonce, ...data } );
		return fetch( POTW_LI.ajax, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( r ) => r.json().catch( () => ( { success: false, data: 'Server returned ' + r.status } ) ) );
	}

	function busy( on ) {
		buttons.forEach( ( b ) => ( b.disabled = on ) );
		stopBtn.disabled = ! on;
	}

	function esc( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function addLog( r ) {
		log.hidden = false;
		counts[ r.status ] = ( counts[ r.status ] || 0 ) + 1;
		const li = document.createElement( 'li' );
		const colour = { created: '#008a20', updated: '#2271b1', unchanged: '#787c82', skipped: '#996800', partial: '#b32d2e', error: '#b32d2e' }[ r.status ] || '';
		li.innerHTML =
			'<strong style="color:' + colour + '">' + esc( r.status ) + '</strong> #' + esc( r.source_id ) + ' ' +
			( r.edit_link ? '<a href="' + esc( r.edit_link ) + '" target="_blank">' + esc( r.title || 'lesson' ) + '</a>' : esc( r.title ) ) +
			( r.log && r.log.length ? ' — ' + esc( r.log.join( ' | ' ) ) : '' );
		log.appendChild( li );
		log.scrollTop = log.scrollHeight;
	}

	async function runQueue() {
		stopping = false;
		busy( true );
		progressWrap.hidden = false;
		let errors = 0;
		while ( ! stopping ) {
			const res = await post( 'potw_li_process' );
			if ( ! res.success ) {
				errors++;
				status.textContent = 'Request failed (' + esc( res.data ) + '), retrying…';
				if ( errors > 5 ) {
					status.textContent = 'Stopped after repeated errors. Press “Resume queue” to continue.';
					break;
				}
				await new Promise( ( r ) => setTimeout( r, 3000 ) );
				continue;
			}
			errors = 0;
			const d = res.data;
			if ( d.done ) {
				bar.value = 100;
				status.textContent = 'Finished. ' + Object.entries( counts ).map( ( [ k, v ] ) => v + ' ' + k ).join( ', ' );
				break;
			}
			addLog( d );
			const total = d.total || 1;
			bar.value = Math.round( ( ( total - d.remaining ) / total ) * 100 );
			status.textContent = ( total - d.remaining ) + ' / ' + total + ' processed…';
		}
		if ( stopping ) {
			status.textContent += ' Paused — press “Resume queue” to continue.';
		}
		busy( false );
		$( '[data-potw-resume]' ).disabled = false;
	}

	document.querySelectorAll( '[data-potw-scan]' ).forEach( ( btn ) =>
		btn.addEventListener( 'click', async () => {
			const mode = btn.dataset.potwScan;
			if ( mode === 'all' && ! window.confirm( 'Re-import every lesson from the old site? Locked lessons are skipped; your Memory Verse / Podcast / Video fields are kept.' ) ) {
				return;
			}
			busy( true );
			summary.innerHTML = '<p>Checking the old site…</p>';
			const res = await post( 'potw_li_scan', { mode } );
			if ( ! res.success ) {
				summary.innerHTML = '<div class="notice notice-error inline"><p>' + esc( res.data ) + '</p></div>';
				busy( false );
				return;
			}
			const d = res.data;
			let html =
				'<div class="notice notice-info inline"><p>Old site has <strong>' + d.source_total + '</strong> posts: ' +
				'<strong>' + d.new + '</strong> new, <strong>' + d.changed + '</strong> changed, ' + d.unchanged + ' unchanged, ' + d.locked + ' locked. ' +
				'<strong>' + d.queued + '</strong> queued for import.</p>';
			if ( d.orphans.length ) {
				html += '<p>' + d.orphans.length + ' imported lesson(s) no longer exist on the old site (left untouched): ' +
					d.orphans.map( ( o ) => '<a href="' + esc( o.link ) + '">' + esc( o.title ) + '</a>' ).join( ', ' ) + '</p>';
			}
			summary.innerHTML = html + '</div>';
			log.innerHTML = '';
			Object.keys( counts ).forEach( ( k ) => delete counts[ k ] );
			if ( d.queued ) {
				runQueue();
			} else {
				busy( false );
			}
		} )
	);

	$( '[data-potw-resume]' ).addEventListener( 'click', runQueue );
	stopBtn.addEventListener( 'click', () => {
		stopping = true;
		stopBtn.disabled = true;
		status.textContent = 'Stopping after the current post…';
	} );

	async function preview( doImport ) {
		const id = $( '#potw-li-preview-id' ).value.trim();
		const out = $( '#potw-li-preview' );
		out.hidden = false;
		out.textContent = doImport ? 'Importing…' : 'Loading…';
		busy( true );
		const res = await post( 'potw_li_preview', { id, import: doImport ? 1 : '' } );
		busy( false );
		stopBtn.disabled = true;
		if ( ! res.success ) {
			out.textContent = res.data;
			return;
		}
		out.textContent = JSON.stringify( doImport ? res.data : res.data.preview || res.data, null, 2 );
		if ( doImport ) {
			addLog( res.data );
		}
	}
	$( '[data-potw-preview]' ).addEventListener( 'click', () => preview( false ) );
	$( '[data-potw-import-one]' ).addEventListener( 'click', () => preview( true ) );
} )();
