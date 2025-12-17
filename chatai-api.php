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

// Reset request: clears PHP session namespace used by ChatAI
if (($input->get->text('action') === 'reset') || ($input->post->text('action') === 'reset')) {

    // Clear all session vars stored via $session->setFor('chatai', ...)
    if (method_exists($session, 'removeAllFor')) {
        $session->removeAllFor('chatai');
    } else {
        // Fallback: remove common keys (keep this list aligned with your module)
        foreach ([
                     'count',
                     'blacklist_strikes',
                     'system_prompt',
                     'history',
                     'chat_id',
                     'ip',
                 ] as $k) {
            if (method_exists($session, 'removeFor')) {
                $session->removeFor('chatai', $k);
            } else {
                // last resort: overwrite
                $session->setFor('chatai', $k, null);
            }
        }
    }
    return $respond(['ok' => true]);
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

$res = $chatai->sendMessage($userMessage, $data->ln ?? null, $sanitizer->int($data->pid) ?? null, $data->url ?? '');
return $respond($res);
