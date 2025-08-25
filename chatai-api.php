<?php namespace ProcessWire;

use stdClass;

$result = new stdClass;
$chatai = $modules->get('ChatAI');
if(!$modules->isInstalled("ChatAI")){
    $result->error = "ChatAI is not installed.";
    return json_encode($result);
}

$result->reply = '';

// Unsure if this is needed but so what?
$ip = $session->getIP();
$session->setFor('chatai', 'ip', $ip);

$post = trim(file_get_contents('php://input'));
if(!$session->getFor('chatai', 'count')) {
    // First message submitted
    $session->setFor('chatai', 'count', 1);
}

// Check the message count
$count = $session->getFor('chatai', 'count');
$result->count = $count;

if($count > $chatai->max_messages) {
    $result->stop = true;
    $result->error = $chatai->getErrorMessage(4);
    $result->html = $files->render($config->paths->root . $chatai->snippets . "/last-message.php");
    return json_encode($result);
}

if (!empty($post) && empty($result->error)) {
    $data = \json_decode($post, null, 512, 0);

    // clean the submitted question
    $userMessage = $sanitizer->text($data->msg ?? '');

    if ($userMessage === '') {
        // blank user message submitted
        $result->error = $chatai->getErrorMessage(2);
    }

    // One more message allowed
    if($count === $chatai->max_messages - 1) {
        $result->html = $files->render($config->paths->root . $chatai->snippets . "/one-message-left.php");
    }

    // Last message submitted, so stop the chatbot
    if($count === $chatai->max_messages) {
        $result->html = $files->render($config->paths->root . $chatai->snippets . "/last-message.php");
        $result->stop = true;
    }

    // Send the message to OpenAI (if not blacklisted)
    $result = $chatai->sendMessage($userMessage);

    if(empty($result->blacklisted) && !empty($result->reply) ) {
        $botanswer = $sanitizer->unentities($result->reply);
        $answer = !empty($result->html) ? $sanitizer->text($result->html) . $botanswer : $botanswer;
        $result->reply = new stdClass;
        $result->reply->msg = $answer;
    }


    // Add one to the session message counter
    $count = $session->getFor('chatai', 'count') + 1;
    $session->setFor('chatai', 'count', $count);

    // Save the messages to the session
    $chatai->saveToTranscript('user', $userMessage);
    if(!empty($answer)) {
        $chatai->saveToTranscript('assistant', $answer);
    } elseif(!empty($result->html) && empty($answer)) {
        $chatai->saveToTranscript('assistant', $result->html);
    }

    return json_encode($result);
}
