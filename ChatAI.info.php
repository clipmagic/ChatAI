<?php namespace ProcessWire;

$info = [
    'title' => 'AI Chatbot for ProcessWire',
    'version' => "0.0.4Alpha",
    'author' => 'Clip magic',
    'summary' => 'Embeds a configurable AI chatbot powered by AgentTools model configuration on your site.',
    'autoload' => true,
    'icon' => 'comment',
    'requires' => ["PHP>=8.0", "ProcessWire>=3.0.201", "AgentTools>=7", "TextformatterEntities", "TextformatterNewlineBR"],
    'installs' => "ProcessChatAI",
    'permissions' => [
        'chatai'
    ],
    'href' => 'https://processwire.com/modules/chatai/'
];
