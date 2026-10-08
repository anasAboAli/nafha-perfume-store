/**
 * Fragrance quiz: one question at a time over the plain GET form rendered by
 * Nafha_Quiz. Single-choice questions advance on tap; the multi-choice one
 * allows up to data-multiple answers.
 */
( function () {
	'use strict';

	const form = document.querySelector( '.nafha-quiz' );
	if ( ! form ) {
		return;
	}

	const steps = [ ...form.querySelectorAll( '.nafha-quiz__step' ) ];
	const bar = form.querySelector( '.nafha-quiz__progress span' );
	let current = 0;

	function answered( step ) {
		return step.querySelector( 'input:checked' ) !== null;
	}

	function show( index ) {
		current = Math.max( 0, Math.min( steps.length - 1, index ) );
		steps.forEach( ( step, i ) => {
			step.hidden = i !== current;
		} );
		bar.style.inlineSize = ( ( current + 1 ) / steps.length ) * 100 + '%';
		updateNext();
		const first = steps[ current ].querySelector( 'input:checked, input' );
		if ( first && document.activeElement !== document.body ) {
			first.focus( { preventScroll: true } );
		}
		form.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
	}

	function updateNext() {
		const next = steps[ current ].querySelector( '.nafha-quiz__next' );
		next.disabled = ! answered( steps[ current ] );
	}

	form.classList.add( 'is-stepped' );

	form.addEventListener( 'change', ( event ) => {
		const step = event.target.closest( '.nafha-quiz__step' );
		const max = Number( step.dataset.multiple );

		if ( max > 1 ) {
			const checked = step.querySelectorAll( 'input:checked' );
			if ( checked.length > max ) {
				event.target.checked = false; // Keep at most `max` choices.
			}
			updateNext();
			return;
		}

		updateNext();
		// Single choice: move on after a short pause so the selection is visible.
		if ( current < steps.length - 1 ) {
			setTimeout( () => show( current + 1 ), 250 );
		}
	} );

	form.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '.nafha-quiz__back' ) ) {
			show( current - 1 );
		} else if ( event.target.closest( '.nafha-quiz__next' ) && event.target.type === 'button' ) {
			if ( answered( steps[ current ] ) ) {
				show( current + 1 );
			}
		}
	} );

	form.addEventListener( 'submit', ( event ) => {
		const missing = steps.findIndex( ( step ) => ! answered( step ) );
		if ( missing !== -1 ) {
			event.preventDefault();
			show( missing );
		}
	} );

	show( 0 );
}() );
