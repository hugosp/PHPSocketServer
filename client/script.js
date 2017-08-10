
    websocket = new WebSocket('ws://dev.dalahest.se/daemon.php'); 
    
    // Connect , Error , Closing 
    websocket.onopen   = function(ev){$('#message_box').append('<div><span class="time">['+currentTime()+'] </span><span class="system_msg">Connected!</span></div>');}
    websocket.onerror  = function(ev){$('#message_box').append('<div><span class="time">['+currentTime()+'] </span><span class="system_error">Error Occurred - '+ev.data+'</span></div>');}; 
    websocket.onclose  = function(ev){$('#message_box').append('<div><span class="time">['+currentTime()+'] </span><span class="system_msg">Connection Closed</span></div>');}; 

    // send
    $('#message').keypress(function (e) {
        // om enter
        if (e.which == 13) {
            var msg = {
                message: $('#message').val(),
                name: $('#name').val() || 'Anonymous',
            };

            msg = JSON.stringify(msg);

            console.log(msg);
            websocket.send(msg);
        }
    });
    
    
    // Recive
    websocket.onmessage = function(ev) {

        var msg = JSON.parse(ev.data);
        var type = msg.type;
        var umsg = msg.message;
        var uname = msg.name;

        console.log(msg);

        if(type == 'usermsg') {
            $('#message_box').append('<div><span class="time">['+currentTime()+'] </span><span class="user_name" >'+uname+'</span> : <span class="user_message">'+umsg+'</span></div>');
        }
        if(type == 'system') {
            $('#message_box').append('<div><span class="time">['+currentTime()+'] </span><span class="system_msg">'+umsg+'</span></div>');
        }
        
        $('#message').val('');
    };
    
    
 function currentTime() {
     var date = new Date();
     var hour = date.getHours();
     var min = date.getMinutes();

     hour = (hour < 10 ? "0" : "") + hour;
     min = (min < 10 ? "0" : "") + min;

    return (hour + ':' + min).toString();
}
