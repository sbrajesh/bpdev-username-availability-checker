jQuery( document ).ready( function() {
	
	var jq=jQuery;
	
	//append a loading box
	
	//create_wrapper( 'input#signup_username' );
	//create_wrapper( 'input#signup_username' );
	
    jq( document).on( 'blur', _BDUAChecker.selectors, function() {
		var $wrapper = jq( this ).parent('.username_checker');
		
		if( ! $wrapper.get(0) ) {
			$wrapper = create_wrapper( this );
		}
		
		jq( '.name-info', $wrapper ).empty();//hhide the message
		//show loading icon
		jq( '.loading', $wrapper ).css( {display:'block'} );
		
        var user_name = jq( this ).val();
        
		jq.post( ajaxurl, {
			action: 'check_username',
			cookie: encodeURIComponent(document.cookie),
			user_name: user_name
			},
			function( resp ) {
				
				if( resp && resp.code != undefined && resp.code == 'success' ) {
						show_message( $wrapper, resp.message, 0 );
				} else {
					show_message( $wrapper, resp.message, 1 );
				}
				},
			'json'	
     
		);
	});//end of onblur
	
	function show_message( $wrapper, msg, is_error ) {//hide ajax loader
		
		jq( '.name-info', $wrapper ).removeClass('available error');
		
		jq( '.loading', $wrapper ).css( {display:'none'} );
		
		jq( '.name-info', $wrapper ).html( msg );
      
		if( is_error ) {
			jq( '.name-info', $wrapper ).addClass( 'error' );
		} else {
			jq( '.name-info', $wrapper ).addClass( 'available' );
		}
    }
	
	function create_wrapper( element ) {
		var $wrapper = jq( element ).parent('.username_checker');
		
		if( ! $wrapper.get(0) ) {
			
			jq( element ).wrap( "<div class='username_checker'></div>" );
			
			$wrapper = jq( element ).parent('.username_checker');
			$wrapper.append( "<span class='loading' style='display:none'></span>" );
			$wrapper.append( "<span class='name-info'></span>" );
		}
		
		return $wrapper;
	}
});//end of dom ready

