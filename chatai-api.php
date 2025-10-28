<?php namespace ProcessWire;
use stdClass;

$respond = function($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit; // stop any further output
};

$chatai = $modules->get('ChatAI');
$res = new stdClass();

if(!$modules->isInstalled('ChatAI')) {
    $res->error = (object)['msg' => 'ChatAI is not installed.'];
    return $respond($res);   // your habitual "return", helper does the echo+exit
}

$session->setFor('chatai', 'ip', $session->getIP());

$post = trim(file_get_contents('php://input'));
if(!$post) return $respond($res);

$data = json_decode($post);
$userMessage = $sanitizer->text($data->msg ?? '');
if($userMessage === '') {
    $res->error = $chatai->getErrorMessage(2);
    return $respond($res);
}

$res = $chatai->sendMessage($userMessage, $data->ln ?? null);

/* Optional decoration kept in the module */
$res = $chatai->finalizeApiResult($res, [
    'ln'       => $data->ln ?? null,
    'page_url' => $data->page_url ?? '',
    'intent'   => $data->intent ?? 'general',
]);

return $respond($res);
