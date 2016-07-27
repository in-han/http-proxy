package main

import "fmt"
import "net"
import "os"
import "strings"
import "runtime/debug"




func main(){
    service := ":8200"
    tcpAddr, err := net.ResolveTCPAddr("tcp4", service)
    checkError(err)
    listener, err := net.ListenTCP("tcp", tcpAddr)
    checkError(err)
    //if err != nil {
        //fmt.Println("Listen fail, 监听失败.")
    //}
   for {
        conn, err := listener.Accept()
        if err != nil {
            // handle error
            fmt.Println("Accept fail, 监听失败.")
        }
        go handleConnection( conn )
   }
}


func handleConnection( conn  net.Conn ){
    defer conn.Close() 
    buf := make([]byte, 2048)  
    var server net.Conn
    for{
        n, err:= conn.Read( buf )
        if checkClose(err) == false {
            return;
        }
        fmt.Println( fmt.Sprintf("Receive: %d, %s", n, buf[:n]) );
        //resp_ok := "HTTP/1.1 200 Connection Established\r\nProxy-Connection: Close\r\n\r\n"
        req := string( buf[:n] )
        begin := strings.Index( req, "CONNECT ") + 8;
        end := strings.Index( req, " HTTP/");
        
        if begin != 8 || end < 0 {
            return 
        }

        host := string( buf[begin:end] );
        fmt.Println( fmt.Sprintf("Host is: %s", host ));
        
        server, err = net.Dial( "tcp", host )
        checkError(err)
        defer server.Close()

        resp_ok := "HTTP/1.1 200 Connection Established\r\nProxy-Connection: Close\r\n\r\n"
        //resp_ok := "HTTP/1.1 404 Connection Established\r\nProxy-Connection: Close\r\n\r\n"
        conn.Write( []byte(resp_ok) )

        break;
    }

    client_req := make( chan []byte,10000 )
    server_req := make( chan []byte,10000 )

    go func(conn net.Conn, channel chan []byte ){
        for{
            buf := make([]byte, 2048)  
            n, err:= conn.Read( buf )
            //checkError(err)
            if false == checkClose(err)  {
                break
            }
            //checkClose(err)
            channel <- buf[:n]
        }
        close( channel )
    }(conn, client_req)

    go func(conn net.Conn, channel chan []byte ){
        for{
            buf := make([]byte, 2048)  
            n, err:= conn.Read( buf )
            //checkError(err)
            //checkClose(err) || break
            if false == checkClose(err)  {
                break
            }
            channel <- buf[:n]
        }
    }(server, server_req)

    var client_str, server_str []byte
    for {
        select {
            case client_str = <- client_req :
                /*
                if false == checkClose(err)  {
                    break
                }
                */

                //fmt.Println("receive client request,", client_str )
                fmt.Println("receive client request" );
                n, err := server.Write([]byte(client_str) )
                fmt.Println("write length is:%d", n);
                if false == checkClose(err) || len(client_str) == 0 {
                    return

                    break
                }

            case server_str = <- server_req :
                //    checkClose(err) || break
                /*
                if false == checkClose(err)  {
                    break
                }
                */

                //fmt.Println("receive server response,", server_str )
                fmt.Println("receive server response" );
                n, err := conn.Write([]byte(server_str) )
                fmt.Println("write length is:%d", n);
                if false == checkClose(err)  {
                    break
                }

            default:
        }
    }
}


func checkError(err error) {
        if err != nil {
            fmt.Fprintf(os.Stderr, "Fatal error: %s \n", err )
            debug.PrintStack( )
            os.Exit(1)
        }
}

func checkClose( err error ) bool {
        if err != nil {
            fmt.Fprintf(os.Stderr, "Fatal error: %s \n", err )
            return false;
        }
        return true
}

