<?php

class SocketServer {

    private $host;
    private $port; 
    
    private $socket; 
    private $clients = []; 

    /**
     * Construct with parameters
     *
     * @param String  $host
     * @param Integer $port
     */
    public function __construct($host=NULL, $port=NULL) {
        $this->host = $host ?? 'localhost';
        $this->port = $port ?? '4000';
    }

    /**
     * Destroy Socket on closing
     * 
     */
    public function __destruct() {
        socket_close($this->socket);
    }

    /**
     * Start up socket and set all the crazy settings 
     *
     * @return void
     */
    private function init() {

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, 0, $this->port);
        socket_listen($this->socket);

        //Add main socket to clientlist
        $this->clients[] = $this->socket;
    }

    public function run() {
        
        $null = NULL;

        $this->init();

        //endless loop
        while (true) {
            //manage multipal connections
            $changed = $this->clients;

            //returns the socket resources in $changed array
            socket_select($changed, $null, $null, 0, 10);
            
            $changed = $this->check_for_new_clients($changed);
            
            //loop through all connected sockets
            foreach ($changed as $changed_socket) {	
                
                while(socket_recv($changed_socket, $buf, 1024, 0) >= 1) {
                
                    $received_text = $this->unmask($buf); 

                    echo $received_text . PHP_EOL;

                    $msg = json_decode($received_text);
                    $user_name    = $msg->name;
                    $user_message = $msg->message;

                    
                    //prepare data to be sent to client
                    $response_text = $this->mask(json_encode([
                        'type'    => 'usermsg',
                        'name'    => $user_name, 
                        'message' => $user_message
                    ]));

                    $this->send_message($response_text);
                    
                    break 2;
                }
            
                $this->check_for_disconnected_clients($changed_socket);
            }
        }

    }


    private function check_for_disconnected_clients($socket) {
        
        $buf = @socket_read($socket, 1024, PHP_NORMAL_READ);
        
        if ($buf === false) {
            $found_socket = array_search($socket, $this->clients);
            unset($this->clients[$found_socket]);
            
            //notify all users about disconnected connection
            $response = $this->mask(json_encode([
                'type'    => 'system', 
                'message' => 'A User disconnected'
            ]));

            $this->send_message($response);

            echo 'Client Disconnected' . PHP_EOL;
        }

    }

    private function check_for_new_clients($array) {
            
        if (in_array($this->socket, $array)) {

            $socket_new = socket_accept($this->socket);
            $this->clients[] = $socket_new;
            
            $header = socket_read($socket_new, 1024);
            $this->perform_handshaking($header, $socket_new, $this->host, $this->port);
            
            socket_getpeername($socket_new, $ip); //get ip address of connected socket
            $response = $this->mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //prepare json data
            $this->send_message($response); //notify all users about new connection
            
            //make room for new socket
            $found_socket = array_search($this->socket, $array);
            unset($array[$found_socket]);

            echo 'New Connection From : ' . $ip . PHP_EOL;
        }

        return $array;
    }

    private function send_message($msg) {

        foreach($this->clients as $changed_socket) {
            @socket_write($changed_socket,$msg,strlen($msg));
        }
        return true;

    }

    //Unmask incoming framed message
    private function unmask($text) {
        $length = ord($text[1]) & 127;
        if($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }

        $text = "";
        
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }
        
        return $text;
    }

    //Encode message for transfer to client.
    private function mask($text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        
        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        
        return $header.$text;
    }

    //handshake new client.
    function perform_handshaking($receved_header,$client_conn, $host, $port) {
        $headers = array();
        $lines = preg_split("/\r\n/", $receved_header);

        foreach($lines as $line) {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        //hand shaking header
        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "WebSocket-Origin: $host\r\n" .
        "WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";

        socket_write($client_conn,$upgrade,strlen($upgrade));
    } 


}




