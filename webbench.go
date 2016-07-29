package main

import (
    "fmt"
//    "io"
    "net/http"
    "net"
    "os"
//    "io/ioutil"
    "time"
)

func main( ) {
    req_num := 2000
    concurrency_max := 100
    c := make(chan int, 100)
    send_cnt := 0;
    concurrency := 0 
    start := time.Now().Unix()

    for i:=0; i< 3; i++{
        concurrency += 1
        send_cnt += 1
        go curl( c )
    }
    
    cnt := 0
    status := 0
    for {
        if cnt == req_num {
            fmt.Println( status, cnt, send_cnt )
            now := time.Now().Unix()
            diff := now - start
            fmt.Println( "qps is:", int64(req_num)/diff )
            break
        }

        status = <- c 
        cnt += 1
        concurrency --
        // fmt.Println( status, cnt, send_cnt )

        if send_cnt == req_num {
            fmt.Println( status, cnt, send_cnt )
            continue
        }
        
        send_cnt += 1
        concurrency ++ 
        go curl( c )

        if concurrency < concurrency_max && send_cnt < req_num {
            send_cnt += 1
            concurrency ++ 
            go curl( c )
        }
    }
}


func curl( c chan int ){
//    defer fmt.Println("OK\n")
    //url := "http://zhifu.baidu.com:8200/"
    url := "http://zhifu.baidu.com/proxy/req/newcashier"
    request, err := http.NewRequest("GET", url, nil)
    if err != nil {
        fmt.Println("request return nil\n")
        c <- 500 
        return
        panic( err )
        return
    }

    DefaultClient := http.Client{
        Transport: &http.Transport{
            Dial: func(netw, addr string) (net.Conn, error) {
                deadline := time.Now().Add(30 * time.Second)
                c, err := net.DialTimeout(netw, addr, time.Second*30)
                if err != nil {
                    fmt.Println("Timeout ... ...\n")
                    return nil, err
                }
                c.SetDeadline(deadline)
                return c, nil
            },
        },
    }

    response, err := DefaultClient.Do( request )

    //处理返回结果
    //client := &http.Client{}
    //response, err := client.Do( request )
    if err != nil {
        fmt.Println("client.Do return nil\n")
        c <- 500 
        return
        panic(err)
        os.Exit(0)
    }
    //stdout := os.Stdout
    //_, err = io.Copy(stdout, response.Body)
     
    //body, _ := ioutil.ReadAll( response.Body )
    //bodystr := string(body);
    //fmt.Println("bodystr is:", bodystr)

    status := response.StatusCode
    //fmt.Println(status)
    //fmt.Println("END\n")
    c <- status
    defer response.Body.Close()
}

