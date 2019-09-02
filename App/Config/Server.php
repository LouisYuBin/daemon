<?php

return [
    [
        'type'             => 'web',
        'host'             => '0.0.0.0',
        'port'             => 4433,
        'workerNum'        => 4,
        'reactorNum'       => 4,
        'backlog'          => 20000,
        'pipeBufferSize'   => 1024*1024*200,
        'socketBufferSize' =>  1024*1024*200,
        'maxRequest'       => 100000,
        'maxCoroutine'     => 50000,
        'maxContentLength' => 20889600,
        'enableStaticHandler' => true,
        'sslCertFile'      => APP_PATH.'/Runtime/Ssl/dugujiujian.net_bundle.crt',
        'sslKeyFile'       => APP_PATH.'/Runtime/Ssl/dugujiujian.net.key',
        'documentRoot'     => APP_PATH.'/Static/Web',
        '404'              => APP_PATH.'/Static/Web/404.html'
    ],
    [
        'type'             => 'webSocket',
        'host'             => '0.0.0.0',
        'port'             => 8081,
        'workerNum'        => 4,
        'reactorNum'       => 4,
        'backlog'          => 20000,
        'pipeBufferSize'   => 1024*1024*200,
        'socketBufferSize' =>  1024*1024*200,
        'maxRequest'       => 100000,
        'maxCoroutine'     => 50000,
        'maxContentLength' => 20889600,
        'enableStaticHandler' => true,
        'documentRoot' => APP_PATH.'/Static/Web',
        '404'          => APP_PATH.'/Static/Web/404.html',
        'handler'      => [
            'open'    => 'WebSocket::Open',
            'message' => 'WebSocket::Message',
            'close'   => 'WebSocket::Close'
        ],
        'components' => [
            'db' => [
                'default' => 10
            ]
        ]
    ],
    [
        'type'             => 'tcp',
        'host'             => '0.0.0.0',
        'port'             => 9505,
        'workerNum'        => 4,
        'reactorNum'       => 4,
        'backlog'          => 20000,
        'pipeBufferSize'   => 1024*1024*200,
        'socketBufferSize' =>  1024*1024*200,
        'maxRequest'       => 100000,
        'maxCoroutine'     => 50000,
        'maxContentLength' => 20889600,
        'heartbeatCheckInterval' => 30,
        'heartbeatIdleTime' => 60,
        'openEofCheck'      => false,
        'packageEof'        => '\r\n',
        'openEofSplit'      => false,
        'handler'           => [
            'connect' => 'Tcp::Connect',
            'receive' => 'Tcp::Receive',
            'close'   => 'Tcp::Close'
        ]
    ],
/*
    [
        'type'             => 'udp',
        'host'             => '0.0.0.0',
        'port'             => 9506,
        'workerNum'        => 8,
        'reactorNum'       => 4,
        'backlog'          => 20000,
        'pipeBufferSize'   => 1024*1024*200,
        'socketBufferSize' =>  1024*1024*200,
        'maxRequest'       => 100000,
        'maxCoroutine'     => 50000,
        'maxContentLength' => 20889600,
        'handler'          => [
            'connect' => 'Tcp::Connect',
            'receive' => 'Tcp::Receive',
            'close'   => 'Tcp::Close'
        ]
    ]
*/

];
