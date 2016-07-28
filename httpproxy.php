<?php
/* rewrite config */

$g_rewrite_cfg = array(
    'http://zhifu.baidu.com/' => 'http://cp01-testing-wallet2014-04.cp01.baidu.com:8880/',
    'http://zhifu.baidu.com:80/' => 'http://cp01-testing-wallet2014-04.cp01.baidu.com:8880/',
);


date_default_timezone_set('PRC');
ini_set( "error_reporting", E_ALL );
ini_set( "display_errors", "On" );
ini_set( "log_errors", "On" );
ini_set( "error_log", "/tmp/php_error.log" );



//$ipserver = "127.0.0.1";
//$ipserver = "192.168.1.102";
$ipserver = "0.0.0.0";
$port = "8100";
$socket=stream_socket_server('tcp://'.$ipserver.':'.$port, $errno, $errstr);
if(!$socket){
    //如果创建socket失败输出内容
    echo "error\n";
    echo "$errstr ($errno)<br />n";
    die( 0 );
}

$fds = array();

while( $conn=stream_socket_accept($socket, 3600*24 ) ){
    $pid = pcntl_fork();  
    if( $pid == -1 ){
        die("fork error\n");
    }else if($pid > 0 ){
        $fds[] = $conn;
        echo "pid:$pid\n";
        continue;
    }else{
        deal_new_connection( $conn );
        echo "child exit\n";
    }

}
fclose($socket);
die( 0 );


function deal_new_connection( $fd ){
    //stream_set_blocking( $fd, 0 );

    /*
    $pid = pcntl_fork();  
    if( $pid == -1 ){
        die('could not fork');
    }else if($pid) {
        return true; 
    }
     */
    if( $fd === false ){
        return;
    } 

    $state = "INIT";
    $buffer = "";
    $conn = array(
        'conn'=> $fd,
        'state'=> 'INIT',
        'read_buf'=> '',
        'send_buf'=> '',
    );

    while( true ){
        $message=fread($fd, 4096);
        if( false !== $message ){
            $ret = deal_receive_message( $conn, $message );
            if( $conn['state'] == 'DONE' ){
                break;
            }
            if( $ret === false ){
                echo "fase .............................\n";
                break;
            }
        }else{
            echo "read get false\n";
            return false;
            break;
        }
    }
    echo posix_getpid() . ", fd exit ###################################\n";
}


function deal_receive_message( & $conn, $message ){
    $conn['read_buf'] .= $message;
    switch( $conn['state'] ){
        case 'INIT':
            if( ( $indx=strpos($conn['read_buf'], "\r\n\r\n") ) === false ){
                break;
            }
            $conn['header_len'] = $indx;
            $conn['body_offset'] = $indx + 4;

            $tmp = explode( "\r\n", substr( $conn['read_buf'], 0, $conn['header_len'] ) );
            $headers = array();
            foreach( $tmp as $k => $v ){
                if( $k == 0 ){
                    $method_uri_proto = explode( ' ', $v );
                    $headers['method'] = $method_uri_proto[0];
                    $headers['uri'] = $method_uri_proto[1];
                    $headers['http_v'] = $method_uri_proto[2];
                }else{
                    $hkv = explode( ':', $v );
                    $headers[$hkv[0]] = trim($hkv[1]);
                }
            }
            $conn['headers'] = $headers;
            if( $headers['method'] == 'GET' ){
                $conn['content_length'] = 0;
                die( 0 );
            }else if( $headers['method'] == 'CONNECT' ){
                var_dump( $headers );
            }else{
                $conn['content_length'] = isset($headers['Content-Length']) ? intval( $headers['Content-Length'] ) : 0;
                die( 0 );
            }

            $conn['state'] = 'HAVE_PARSE_HEADER';
            //var_dump( $conn );

            // to continue next stage.
        case 'HAVE_PARSE_HEADER':
            if( strlen($conn['read_buf']) < $conn['body_offset'] + $conn['content_length'] ){
                return;
            }
            $conn['body'] = substr( $conn['read_buf'], $conn['body_offset'], $conn['content_length'] );
            if( $conn['body'] === false ){
                $conn['body'] = '';
            }
            /*
            if( $conn['headers']=='POST' && strlen($conn['body']) > 0 ){
                $conn['post_param'] = $conn['body']
            }
            */
            $conn['state'] = 'HAVE_PARSE_BODY';
            unset( $conn['read_buf'] );

        case 'HAVE_PARSE_BODY':
            // before send body
            $resp_header = array(
                'HTTP/1.1 %d',
                'Content-Type: text/html',
                'Connection: close',
            );


            //$resp_body = $conn['body'];
            //var_dump( $conn['headers'] );
            
            $remote_params = get_rewrite_params( $conn );

            $resp_body = 'Proxy Error';
            //if( $remote_params['method'] == 'GET' && (strpos($remote_params['url'], 'http://') === 0) ){
            //


            if( (strpos($remote_params['url'], 'https://') === 0) ){
                echo sprintf("WARNING: ignore https request, url:\n" . $remote_params['url'] );
                return false;
            }
            if( (strpos($remote_params['url'], 'http://') === 0) ){
                echo "call curl.....................................\n";
                echo sprintf("%d, call curl, %s.\n", posix_getpid(), $remote_params['url'] );
                $curl_ret = call_curl( $remote_params );
                $resp_body = $curl_ret['body']; 
                $resp_header = $curl_ret['headers'];
            }else{
                //var_dump( $remote_params );
                echo "ERR: can't call curl.....................................\n";
                var_dump( $conn );
                return false;
            }

            // $proxy_response = get_request_by_curl( $url, $params );
            //$resp_body = $proxy_response['body'];

            /*
            $resp_header[] = sprintf("Content-Length: %d", strlen( $resp_body ) );
            $resp_header_txt_fmt = implode("\r\n", $resp_header );
            $resp_header_txt = sprintf($resp_header_txt_fmt, 200);
             */

            /*
            if( strpos($remote_params['url'], 'favicon.ico') >0 ){ 
                $resp_header_txt = sprintf($resp_header_txt_fmt, 302);
            }else{
                $resp_header_txt = sprintf($resp_header_txt_fmt, 200);
            }
            */

            //$resp_txt = $resp_header_txt . "\r\n\r\n" . $resp_body;

            $resp_txt = $resp_header . "\r\n" . $resp_body;
            $write_len = fwrite( $conn['conn'], $resp_txt, strlen($resp_txt)  );
            //var_dump( $resp_txt );
            echo sprintf("lenth,  header:%d, body:%d\n", strlen($resp_header), strlen($resp_body) );
            if( $write_len == strlen($resp_txt) ){
                echo sprintf("%d, write done, %s\n", posix_getpid(), $remote_params['url'] );
                $conn['state'] = 'DONE';
                fclose($conn['conn']);
                return true;
            }
            $conn['state'] = 'DOING_RESPONSE';
            $conn['write_buf'] = $resp_txt;
            $conn['write_len'] = $write_len;

            if( $write_len == strlen($conn['write_buf']) ){
                $conn['state'] = 'WAIT_FOR_CLOSE';
            }else{
                break;
            }

        case 'DOING_RESPONSE':
            //sending response body.
            $fd = $conn['conn'];
            $write_len = fwrite( $fd, substr($conn['write_buf'], $conn['write_len'] ) );
            if( is_int( $write_len ) ){
                $conn['write_len'] += $write_len;
            }
            if( $conn['write_len'] == strlen( $conn['write_buf'] ) ){
                $conn['state'] = 'WAIT_FOR_CLOSE';
            }else{
                break;
            }

        case 'WAIT_FOR_CLOSE':
            // wait client close.
            //if( $wait_close_by_client ){
            //    break;
            //}
        case 'CLIENT_FORCE_CLOSE':
        case 'CLIENT_NORMAL_CLOSE':
            fclose( $conn['conn'] );
            break;
        default:
            var_dump( "unexpected state" );
            // var_dump( $conn );
    }


}


function get_rewrite_params( $conn ){
    global $g_rewrite_cfg;
    $header = $conn['headers'];

    $method = $header['method'];
    $url = $header['uri']; 
    $body = $conn['body'];


    foreach( $g_rewrite_cfg as $f => $t ){
        if( strpos($url, $f) === 0 ){
            $url = $t . substr($url, strlen($f));   
            echo "new url is:$url\n";
            break;
        }    
    }

    if( strpos($url, 'baidu.com') > 0 ){
        echo "rewrite: $url\n";
    }

    $ret = array(
        'method'=>$method,
        'url'=>$url,
        'body'=>$body,
    ); 
    return $ret;
}

function call_curl( $params ){
    $method = $params['method'];
    $url = $params['url'];
    $body = $params['body'];

    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_HEADER,1);                      //output header.

    if( 'POST' == $method ){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        
        var_dump( $body );
    }

    $output = curl_exec($ch);
    if($output === FALSE ){
        echo "CURL Error:".curl_error($ch);
    }
    curl_close($ch);
    if( strpos($url, "getpay") ){
        var_dump( $output );
    }else{
        // return false;
    }

    $ret = array(
        'status'=>false,
        'url'=>$url,
        'headers'=>'',
        'body'=>false,
    );

    $tmp_indx = strpos($output , "\r\n\r\n");
    if( $tmp_indx === false ){
        return $ret;
    }

    $header_raw = substr($output, 0, $tmp_indx);
    $ret['body'] = substr($output, $tmp_indx + 4);
    $ret['status'] = true;

    $header_kv = explode("\r\n", $header_raw);
    $spec = '';
    $headers = array();
    foreach( $header_kv as $t ){
        $hkv = explode( ':', $t );
        if( count($hkv) == 1 ){
            $spec = $hkv[0];
            continue;
        }

        $tmp_indx = strpos( $t, ':' );
        $hkv[1] = substr( $t, $tmp_indx + 1 );
        $headers[$hkv[0]] = trim($hkv[1]);
    }

    //var_dump( $headers );

    $headers['Connection'] = 'close';
    $headers['Proxy-Connection'] = 'close';
    unset( $headers['P3P'] );
    if( isset($headers['Transfer-Encoding']) ){
        unset( $headers['Transfer-Encoding'] );
        if( ! isset($headers['Location']) ){
            $headers['Content-length'] = strlen( $ret['body'] );
        }else{
        }
    }

    $new_header_txt = $spec . "\r\n";
    foreach( $headers as $k => $v ){
           $new_header_txt .= sprintf("%s: %s\r\n", $k, $v);  
    }
    
    //echo "new_header_txt:" . $new_header_txt ;

    $ret['headers'] = $new_header_txt;
    return $ret;
}



