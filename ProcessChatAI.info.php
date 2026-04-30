<?php namespace ProcessWire;

$info = [
    'title' => 'AI Chatbot for ProcessWire',
    'version' => "0.0.4Alpha",
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
    ],
    'permission' => 'chatai',
];
