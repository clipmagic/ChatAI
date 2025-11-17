<?php namespace ProcessWire;
?>
<!-- Chatbot Toggle Button -->
<button id="chatbot-toggle" class="chatbot-toggle" aria-label="Open chatbot">
    <svg class="chatbot-icon" viewBox="0 0 24 24" >
        <path d="M12 3C7 3 3 6.58 3 11c0 1.53.54 2.95 1.46 4.13L3 21l5.22-1.58C9.18 19.78 10.57 20 12 20c5 0 9-3.58 9-8s-4-9-9-9z"/>
    </svg>
</button>

<!-- Chatbot Dialog -->
<dialog id="chatbot-dialog" class="chatbot-dialog" closeby="any">
    <div class="chatbot-header">
        <div class="chatbot-header-icon">
            <svg viewBox="0 0 24 24">
                <path d="M12 3C7 3 3 6.58 3 11c0 1.53.54 2.95 1.46 4.13L3 21l5.22-1.58C9.18 19.78 10.57 20 12 20c5 0 9-3.58 9-8s-4-9-9-9z"/>
            </svg>
        </div>
        <form method="dialog">
            <button class="chatbot-close" aria-label="close model" >
                 <span class="icon">
                    <svg id="chatbot-icon-close" viewBox="0 0 384 512">
                        <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/>
                    </svg>
                </span>
            </button>
        </form>
    </div>

    <div class="chatbot-msg-wrapper">
        <div class="chatbot-welcome chatbot-msg bot">
            <?=$intro?>
        </div>
        <div id="chatbot-messages" class="chatbot-messages"></div>
    </div>

    <div class="chatbot-input-wrapper">
        <form id="chatbot-form" class="chatbot-form">
            <input type="text" id="chatbot-input" data-ln="<?=$user->language->id?>" class="chatbot-input" placeholder="<?=$placeholder?>" required aria-label="Chatbox input field">
            <button type="submit" class="chatbot-submit"><?=$button_text?></button>
        </form>

        <div class="chatbot-meta-row">
            <div id="chatbot-status"
                 class="chatbot-status"
                 aria-live="polite"
                 aria-atomic="true"
                 hidden>
                <span class="chatbot-status-sr"><?=$thinking_text?></span>
                <span class="chatbot-status-visible" aria-hidden="true">
                <?=$thinking_text?>
            </span>
            </div>

            <div class="chatbot-clear" hidden>
                <button type="button"
                        class="chatai-reset"
                        aria-label="Reset this chat and clear the current conversation">
                    <?=$reset_text?>
                </button>
            </div>
        </div>
    <footer class="chatbot-footer">
        <p class="chatbot-disclaimer"><?=$disclaimer_text?></p>

        <div class="chatbot-footer-actions">
            <?=$footer_text?>
        </div>
    </footer>
</dialog>


