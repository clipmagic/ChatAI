<?php namespace ProcessWire;

use stdClass;

$chatai = $modules->get('ChatAI');
$res = new stdClass();

if (!$modules->isInstalled('ChatAI')) {
    $res->error = (object)['msg' => 'ChatAI is not installed.'];
    echo json_encode($res);
    return;
}

// optional: light-rate signal
$session->setFor('chatai', 'ip', $session->getIP());

$post = trim(file_get_contents('php://input'));
if (!$post) { echo json_encode($res); return; }

$data = \json_decode($post, null, 512, 0);
$userMessage = $sanitizer->text($data->msg ?? '');

if ($userMessage === '') {
    $res->error = (object)['msg' => $chatai->getErrorMessage(2)];
    echo json_encode($res);
    return;
}

$out = $chatai->sendMessage($userMessage, $data->ln ?? null);
echo json_encode($out);
