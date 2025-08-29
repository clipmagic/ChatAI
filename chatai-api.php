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

    $result = $chatai->sendMessage($userMessage, $data->ln);

    if(empty($result->blacklisted) && !empty($result->reply) ) {
        $botanswer = $sanitizer->unentities($result->reply);
        $answer = !empty($result->html) ? $sanitizer->text($result->html) . $botanswer : $botanswer;
        $result->reply = new stdClass;
        $result->reply->msg = $answer;
    }

    return json_encode($result);
}
