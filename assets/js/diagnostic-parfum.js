/**
 * LSG Diagnostic Parfum — comportement front-end du bloc custom/diag et du
 * shortcode [lsg_diag_parfum].
 *
 * - Sur la page dédiée (shortcode), la sélection déclenche un appel à l'API
 *   REST du plugin (lsgDiagSettings.restUrl) pour ne remplacer que la grille
 *   de résultats, sans recharger toute la page.
 * - Sur une page de catégorie (bloc), aucune grille de résultats n'est
 *   gérée par ce widget : la sélection continue de naviguer vers l'URL
 *   filtrée, comme avant, pour que pre_get_posts filtre la boucle native du
 *   thème.
 * - Chaque instance de widget est isolée via son conteneur .lsg-diag-root
 *   (pas d'identifiants globaux réutilisés), pour supporter plusieurs
 *   widgets sur une même page.
 * - Sans JavaScript, le <form> se soumet nativement en GET vers la même
 *   page : le filtrage fonctionne toujours (voir le bouton .lsg-diag-submit
 *   révélé par la balise <noscript> du formulaire).
 */
( function () {
	'use strict';

	function buildParams( parfumSelect, noteSelect ) {
		var params = new URLSearchParams();
		if ( parfumSelect.value ) {
			params.set( 'diag_parfum', parfumSelect.value );
		}
		if ( noteSelect.value ) {
			params.set( 'diag_note', noteSelect.value );
		}
		params.set( 'diag', '1' );
		return params;
	}

	function initHint( root ) {
		var hint = root.querySelector( '.hint-box' );
		var parfumSelect = root.querySelector( 'select[name="diag_parfum"]' );
		var noteSelect = root.querySelector( 'select[name="diag_note"]' );

		if ( ! hint ) {
			return;
		}

		function hasDismissed() {
			return document.cookie.indexOf( 'hintdiag=' ) !== -1;
		}

		function dismiss() {
			hint.style.display = 'none';
			document.cookie = 'hintdiag=done; path=/';
		}

		if ( ! hasDismissed() ) {
			hint.style.display = 'block';
		}

		hint.addEventListener( 'click', dismiss );
		if ( parfumSelect ) {
			parfumSelect.addEventListener( 'click', dismiss );
		}
		if ( noteSelect ) {
			noteSelect.addEventListener( 'click', dismiss );
		}
	}

	function initWidget( root ) {
		var form = root.querySelector( '.lsg-diag-form' );
		var parfumSelect = root.querySelector( 'select[name="diag_parfum"]' );
		var noteSelect = root.querySelector( 'select[name="diag_note"]' );
		var resetBtn = root.querySelector( '.lsg-diag-reset' );
		var loading = root.querySelector( '.lsg-diag-loading' );
		var resultsWrap = root.querySelector( '.lsg-diag-results-wrapper' );
		var countEl = root.querySelector( '.lsg-diag-count' );
		var isAjax = !! ( resultsWrap && window.lsgDiagSettings && window.lsgDiagSettings.restUrl );

		if ( ! parfumSelect || ! noteSelect ) {
			return;
		}

		function setLoading( state ) {
			if ( loading ) {
				loading.classList.toggle( 'is-active', state );
				loading.setAttribute( 'aria-hidden', state ? 'false' : 'true' );
			}
			parfumSelect.disabled = state;
			noteSelect.disabled = state;
		}

		function reflectURL( params ) {
			var url = new URL( window.location.href );
			url.search = params.toString();
			window.history.pushState( { lsgDiag: true }, '', url.toString() );
		}

		function scrollToResults() {
			if ( ! resultsWrap ) {
				return;
			}
			var rect = resultsWrap.getBoundingClientRect();
			var inView = rect.top >= 0 && rect.top <= window.innerHeight * 0.4;
			if ( ! inView ) {
				var top = window.pageYOffset + rect.top - 24;
				window.scrollTo( { top: top, behavior: 'smooth' } );
			}
		}

		// Repli : navigation complète (utilisé pour le bloc catégorie, et en
		// secours si l'appel AJAX échoue sur la page dédiée).
		function goToURL( params ) {
			setLoading( true );
			var url = new URL( window.location.href );
			url.search = params.toString();
			if ( root.id ) {
				url.hash = root.id;
			}
			window.location.href = url.toString();
		}

		function applyResponse( data, params, shouldPushState ) {
			if ( resultsWrap && typeof data.html === 'string' ) {
				resultsWrap.innerHTML = data.html;
			}

			if ( countEl ) {
				if ( data.count > 0 ) {
					countEl.textContent = data.countLabel;
					countEl.removeAttribute( 'hidden' );
				} else {
					countEl.textContent = '';
					countEl.setAttribute( 'hidden', 'hidden' );
				}
			}

			if ( noteSelect && typeof data.noteOptions === 'string' ) {
				noteSelect.innerHTML = data.noteOptions;
				noteSelect.value = data.selectedNote || '';
			}

			if ( parfumSelect && data.selectedParfum ) {
				parfumSelect.value = data.selectedParfum;
			}

			if ( resetBtn ) {
				if ( data.hasActiveFilter ) {
					resetBtn.removeAttribute( 'hidden' );
				} else {
					resetBtn.setAttribute( 'hidden', 'hidden' );
				}
			}

			if ( shouldPushState ) {
				reflectURL( params );
			}

			setLoading( false );
			scrollToResults();
		}

		function fetchResults( params, opts ) {
			opts = opts || {};
			var shouldPushState = opts.pushState !== false;

			if ( ! isAjax ) {
				goToURL( params );
				return;
			}

			setLoading( true );

			var url = window.lsgDiagSettings.restUrl + '?' + params.toString();

			fetch( url, { headers: { Accept: 'application/json' } } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'lsg-diag: request failed' );
					}
					return response.json();
				} )
				.then( function ( data ) {
					applyResponse( data, params, shouldPushState );
				} )
				.catch( function () {
					goToURL( params );
				} );
		}

		parfumSelect.addEventListener( 'change', function () {
			noteSelect.selectedIndex = 0;
			fetchResults( buildParams( parfumSelect, noteSelect ) );
		} );

		noteSelect.addEventListener( 'change', function () {
			fetchResults( buildParams( parfumSelect, noteSelect ) );
		} );

		// Filet de sécurité (Entrée clavier, bouton de secours sans JS) :
		// rejoue la même logique plutôt que de laisser le GET natif partir,
		// tant que JS est disponible.
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				fetchResults( buildParams( parfumSelect, noteSelect ) );
			} );
		}

		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				var params = new URLSearchParams();
				params.set( 'diag', '1' );
				fetchResults( params );
			} );
		}

		if ( isAjax ) {
			window.addEventListener( 'popstate', function () {
				var params = new URLSearchParams( window.location.search );
				fetchResults( params, { pushState: false } );
			} );
		}

		initHint( root );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var roots = document.querySelectorAll( '.lsg-diag-root' );
		for ( var i = 0; i < roots.length; i++ ) {
			initWidget( roots[ i ] );
		}
	} );
} )();
