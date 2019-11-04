<?php

return [
    '127.0.0.1:80, 127.0.0.1:8080, www.dugujiujian.net, dugujiujian.net' => [
        '/' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/user/:id' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/user/:id' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/product/:id' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/product/:id/status/:status' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/product/:id/delete/:delete' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/category' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/attribute/:id' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ],
        '/manager/attribute/:id/status/:status/startDate/:startDate/endDate/:endDate' => [
            'get'    => 'Admin\\Index::get',
            'put'    => 'Admin\\Index::put',
            'post'   => 'Admin\\Index::post',
            'delete' => 'Admin\\Index::delete'
        ]
    ]
];