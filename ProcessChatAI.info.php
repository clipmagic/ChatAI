<?php namespace ProcessWire;
/*
 * Copyright (c) 2025.
 * Clip Magic - Prue Rowland
 * Web: www.clipmagic.com.au
 * Email: admin@clipmagic.com.au
 *
 * ProcessWire 3.x
 * Copyright (C) 2014 by R
 * Licensed under GNU/GPL
 *
 * https://processwire.com
 */


$info = [
    'title' => 'AI Chatbot for ProcessWire',
    'version' => "0.0.1Beta",
    'summary' => 'Manages ChatAI chats in admin',
    'autoload' => 'admin',
    'singular' => true,
    'requires' => "ProcessWire>=3.0, ChatAI",
    'href' => 'https://processwire.com/modules/chatai/',
    'icon' => 'comment',
    'page' => [
        'name' => 'manage-chats',
        'parent' => 'setup',
        'title' => 'ChatAI'
    ]
];
