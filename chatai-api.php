<?php namespace ProcessWire;
use stdClass;

/*
This endpoint intentionally returns raw JSON; ProcessWire bootstrapping and
headers are handled by index.php.
*/

$chatai = $modules->get('ChatAI');
$res = new stdClass();

if(!$modules->isInstalled('ChatAI')) {
    $res->error = (object)['msg' => 'ChatAI is not installed.'];
    return json_encode($res);
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
    return json_encode(['ok' => true]);
}

$session->setFor('chatai', 'ip', $session->getIP());

$post = trim(file_get_contents('php://input'));
if(!$post) return json_encode($res);;

$data = json_decode($post);
$userMessage = $sanitizer->text($data->msg ?? '');
if($userMessage === '') {
    $res->error = $chatai->getErrorMessage(2);
    return json_encode($res);
}

$res = $chatai->sendMessage(
    $userMessage,
    $sanitizer->int($data->ln ?? null) ?: null,
    $sanitizer->int($data->pid ?? null) ?: null,
    $sanitizer->url($data->url ?? '')
);

return json_encode($res);
