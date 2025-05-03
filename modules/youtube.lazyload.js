$( () => {
	const $videos = $( '.ext-YouTube-video--lazy' );

	$videos.on( 'click', function () {
		let $elm = $( this ),
			$conts = $elm.contents(),
			le = $conts.length,
			ifr = null,
			i;

		$elm.removeClass( 'ext-YouTube-video--lazy' );

		for ( i = 0; i < le; i++ ) {
			if ( $conts[ i ].nodeType === 8 ) {
				ifr = $conts[ i ].textContent;
			}
		}

		$elm.addClass( 'player' ).html( ifr );
		$elm.off( 'click' );
	} );
} );
