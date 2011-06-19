/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
jQuery(document).ready(function(){
    var j=jQuery;
//append a loading box
j("input#signup_username").wrap("<div id='username_checker'></div> ");
j("#username_checker").append("<span class='loading' style='display:none'></span>")
j("#username_checker").append("<span id='name-info'></span> ");
    j("input#signup_username").bind("blur",function(){
		j("#username_checker #name-info").empty();//hhide the message
		//show loading icon
		j("#username_checker .loading").css({display:'block'});
		
        var user_name=j("input#signup_username").val();
        j.post( ajaxurl, {
			action: 'check_username',
			'cookie': encodeURIComponent(document.cookie),
			'user_name':user_name
			},
		function(response){
                    var resp=JSON.parse(response);
					if(resp.code=='success')
						show_message(resp.message,0);
					else
					show_message(resp.message,1);
				}
     
    );
});
function show_message(msg,is_error)
    {//hide ajax loader
	j("#username_checker #name-info").removeClass();
	j("#username_checker .loading").css({display:'none'});
     j("#username_checker #name-info").empty().html(msg);
      if(is_error)
       j("#username_checker #name-info").addClass("error");
	   else
	   j("#username_checker #name-info").addClass("available");
    }
});

