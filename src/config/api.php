<?php

return array(

    'available_keys' => [
        //以下是 php 项目的配置

        //sso 客户端
        '' => ['secret' => '', 'type' => 101, 'alias' => 'SSO Api'],

        //yii-webapi 客户端
        '' => ['secret' => '', 'type' => 102, 'alias' => 'yii-webapi'],

        //webapi 客户端
        '' => ['secret' => '', 'type' => 103, 'alias' => 'webapi'],

        //国际站 客户端
        '' => ['secret' => '', 'type' => 104, 'alias' => 'dota2guess'],

        //临时给页面使用
        '' => ['secret' => '', 'type' => 105, 'alias' => 'dota2guess_page'],

        //iOS
        '' => ['secret' => '', 'type' => 106, 'alias' => 'iOS'],

        //android
        '' => ['secret' => '', 'type' => 107, 'alias' => 'Android'],

        //后台
        '' => ['secret' => '', 'type' => 108, 'alias' => 'Management'],

        //yii-passport-sso
        '' => ['secret' => '', 'type' => 109, 'alias' => 'yii-passport-sso'],

        //java 端使用
        '' => ['secret' => '', 'type' => 110, 'alias' => 'java_server'],

        //roll node server (update @2017.11.1)
        '' => ['secret' => '', 'type' => 111, 'alias' => 'roll_node_server'],

        //Lumen Roll Project
        '' => ['secret' => '', 'type' => 112, 'alias' => 'lumen_roll_project'],

        //Lumen Item Project
        '' => ['secret' => '', 'type' => 113, 'alias' => 'lumen_item_project'],

        //Lumen Missions Project
        '' => ['secret' => '', 'type' => 114, 'alias' => 'lumen_missions_project'],

        //H5 node server
        '' => ['secret' => '', 'type' => 115, 'alias' => 'h5_node_server'],

        // 梦幻联赛
        '' => ['secret' => '', 'type' => 116, 'alias' => 'fantasy_server'],

        //开箱子 node 服务端使用
        '' => ['secret' => '', 'type' => 117, 'alias' => 'box_node_server'],

        //shanghai
        '' => ['secret' => '', 'type' => 118, 'alias' => 'shanghai_admin'],
        
        //主场线下支付
        ''=>['secret'=>'','type' => 119, 'alias' => 'shanghai_offline'],
        
        //以下是 java api gateway 的配置 for PHP client

        //java_message_server java 消息服
        '' => ['secret' => '', 'type' => 301, 'alias' => 'java_message_server'],


        /*********** PPSkins ***********/

        //前端使用
        '' => ['secret' => '', 'type' => 901, 'alias' => 'ppskins_node'],

        //java 端使用
        '' => ['secret' => '', 'type' => 902, 'alias' => 'ppskins_java'],

        //pp 内部调用
        '' => ['secret' => '', 'type' => 903, 'alias' => 'ppskins_php'],

        //调用 java 使用
        '' => ['secret' => '', 'type' => 911, 'alias' => 'ppskins_java_gateway'],

        /*********** PPSkins ***********/
        


    ]

);

//已经弃用
//roll node server
//'' => ['secret' => '', 'type' => 111, 'alias' => 'roll_node_server'],