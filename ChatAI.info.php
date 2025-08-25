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
    'version' => "0.0.1Alpha",
    'author' => 'Clip magic',
    'summary' => 'Embeds a configurable AI chatbot powered by OpenAI on your site.',
    'autoload' => false,
    'icon' => 'comment',
    'requires' => ["PHP>=8.0", "ProcessWire>=3.0.201","TextformatterEntities", "TextformatterNewlineBR"],
    'installs' => "ProcessChatAI",
    'href' => 'https://processwire.com/modules/chatai/'
];
