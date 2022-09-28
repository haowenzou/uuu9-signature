<?php

//当前项目的 API 客户端 key 和 endpoint 设置, secret 可以在组件配置中读取

//###############################################//
//请把此文件拷贝到项目 config 目录,并且正确填写以下信息//
//###############################################//

return array(

    'client_keys' => [
        //php API
        'api_config' => [
            'key' => '@todo 请填写当前端被分配的 key',        //当前项目分配的 API key
            'endpoint' => env('ENDPOINT_API', 'http://api.uuu9.com')      //api 接口地址
        ],

        //java API
        'gateway_config' => [
            'key' => '@todo 请填写当前端被分配的 key',               //当前项目分配的 API key
            'endpoint' => env('ENDPOINT_GATEWAY', 'http://underlord.uuu9.com')    //api 接口地址
        ]
    ]

);
