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
 *
 *
 *
 *   $('#element').WireTabs({ // tabs will be prepended to #element
    items: $(".WireTab"), // items that it should tab (REQUIRED)
    rememberTabs: true, // whether it should remember current tab across requests
    skipRememberTabIDs: ['DeleteTab'], // array of tab IDs it should not remember between requests
    id: 'PageEditTabs', // id attribute for generated tabbed navigation (optional)
    itemsParent: null, // parent element for items (better to omit when possible)
    cookieName: 'WireTab', // Name of cookie it uses to remember tabs
  });
 */


$(document).ready(function() {
    // instantiate WireTabs if defined
    if (typeof ProcessWire.config.JqueryWireTabs === 'object') {
        $('body.ProcessChatAI #pw-content-body').WireTabs({
            items: $(".WireTab"),
            id: 'chatai-tabs'
        });
    } else {
        console.log(typeof ProcessWire.config.JqueryWireTabs)
    }
 });