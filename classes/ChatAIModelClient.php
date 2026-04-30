<?php namespace ProcessWire;

/**
 *
 */
class ChatAIModelClient extends Wire
{
    /**
     * @return array
     * @throws WireException
     */
    public function availableModels(): array
    {
        $at = $this->wire("at");
        if (!$at || !method_exists($at, "getAgents")) {
            return [];
        }

        $models = [];
        foreach ($at->getAgents() as $agent) {
            if (!is_object($agent)) {
                continue;
            }

            $entry = $this->entryFromAgent($agent);
            $id = $this->modelId($entry);
            if ($id === "") {
                continue;
            }

            $models[$id] = $entry;
        }

        return $models;
    }

    /**
     * @return array
     * @throws WireException
     */
    public function modelOptions(): array
    {
        $options = [];
        foreach ($this->availableModels() as $id => $entry) {
            $options[$id] = $this->modelLabel($entry);
        }

        return $options;
    }

    /**
     * @param string $id
     * @return array|null
     * @throws WireException
     */
    public function modelEntry(string $id): ?array
    {
        $models = $this->availableModels();
        if ($id !== "" && isset($models[$id])) {
            return $models[$id];
        }

        if ($id !== "") {
            foreach ($models as $entry) {
                if ($this->legacyModelId($entry) === $id) {
                    return $entry;
                }
            }
        }

        if ($id === "") {
            foreach ($models as $entry) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param string $id
     * @return string
     * @throws WireException
     */
    public function resolveModelId(string $id): string
    {
        $models = $this->availableModels();
        if ($id !== "" && isset($models[$id])) {
            return $id;
        }

        if ($id !== "") {
            foreach ($models as $stableId => $entry) {
                if ($this->legacyModelId($entry) === $id) {
                    return $stableId;
                }
            }
        }

        if ($id === "") {
            foreach ($models as $stableId => $entry) {
                return $stableId;
            }
        }

        return "";
    }

    /**
     * @param array $entry
     * @return string
     */
    public function modelLabel(array $entry): string
    {
        $label = trim((string) ($entry["label"] ?? ""));
        if ($label !== "") {
            return $label;
        }

        $model = trim((string) ($entry["model"] ?? ""));
        $provider = trim((string) ($entry["provider"] ?? ""));

        return $provider !== "" ? $model . " (" . $provider . ")" : $model;
    }

    /**
     * @param string $id
     * @return string
     * @throws WireException
     */
    public function selectedLabel(string $id): string
    {
        $entry = $this->modelEntry($id);

        return $entry ? $this->modelLabel($entry) : $this->_("No AgentTools model selected");
    }

    /**
     * @param array $messages
     * @param string $modelId
     * @param array $options
     * @return \stdClass
     * @throws WireException
     */
    public function call(array $messages, string $modelId, array $options = []): \stdClass
    {
        $entry = $this->modelEntry($modelId);
        if (!$entry) {
            return (object) [
                "ok" => false,
                "error" => "model_unavailable",
                "message" => $this->_("The selected AgentTools model is not available."),
            ];
        }

        if (trim((string) ($entry["key"] ?? "")) === "") {
            return (object) [
                "ok" => false,
                "error" => "missing_api_key",
                "model" => (string) ($entry["model"] ?? ""),
                "message" => $this->_("The selected AgentTools model does not have an API key configured."),
            ];
        }

        [$systemPrompt, $requestMessages] = $this->splitSystemPrompt($messages);

        try {
            $request = new AgentToolsRequest($entry["agent"]);
            $request->systemPrompt = $systemPrompt;
            $request->messages = $requestMessages;
            $request->options = $this->requestOptions($entry, $options);
            $response = $entry["agent"]->sendProviderRequest($request);
        } catch (\Throwable $e) {
            return $this->failure("exception", $e->getMessage(), [], (string) ($entry["model"] ?? ""), "");
        }

        return $this->normalizeAgentResponse($entry, is_array($response) ? $response : []);
    }

    /**
     * @param array $entry
     * @return string
     */
    protected function modelId(array $entry): string
    {
        $model = trim((string) ($entry["model"] ?? ""));
        if ($model === "") {
            return "";
        }

        $keyHash = trim((string) ($entry["key_hash"] ?? ""));
        if ($keyHash === "" && !empty($entry["agent"]) && is_object($entry["agent"]) && method_exists($entry["agent"], "getHash")) {
            $keyHash = (string) $entry["agent"]->getHash();
        }

        return sha1(implode("|", [
            (string) ($entry["provider"] ?? ""),
            $model,
            (string) ($entry["endpoint"] ?? ""),
            $keyHash,
        ]));
    }

    /**
     * @param array $entry
     * @return string
     */
    protected function legacyModelId(array $entry): string
    {
        $model = trim((string) ($entry["model"] ?? ""));
        if ($model === "") {
            return "";
        }

        return sha1(implode("|", [
            (string) ($entry["provider"] ?? ""),
            $model,
            (string) ($entry["endpoint"] ?? ""),
            (string) ($entry["label"] ?? ""),
        ]));
    }

    /**
     * @param object $agent
     * @return array
     */
    protected function entryFromAgent(object $agent): array
    {
        return [
            "agent" => $agent,
            "provider" => (string) ($agent->provider ?? ""),
            "model" => (string) ($agent->model ?? ""),
            "key" => (string) ($agent->apiKey ?? ""),
            "key_hash" => (string) (method_exists($agent, "getHash") ? $agent->getHash() : md5(((string) ($agent->model ?? "")) . "|" . ((string) ($agent->apiKey ?? "")))),
            "endpoint" => (string) ($agent->endpointUrl ?? ""),
            "label" => (string) ($agent->label ?? ""),
        ];
    }

    /**
     * @param array $messages
     * @return array
     */
    protected function splitSystemPrompt(array $messages): array
    {
        $systemParts = [];
        $requestMessages = [];
        foreach ($this->normalizeMessages($messages) as $message) {
            if (($message["role"] ?? "") === "system") {
                $systemParts[] = (string) ($message["content"] ?? "");
                continue;
            }

            $requestMessages[] = $message;
        }

        if (!$requestMessages) {
            $requestMessages[] = ["role" => "user", "content" => ""];
        }

        return [trim(implode("\n\n", $systemParts)), $requestMessages];
    }

    /**
     * @param array $entry
     * @param array $options
     * @return array
     */
    protected function requestOptions(array $entry, array $options): array
    {
        $requestOptions = [];
        $provider = (string) ($entry["provider"] ?? "");
        $model = (string) ($entry["model"] ?? "");
        $endpoint = (string) ($entry["endpoint"] ?? "");

        $timeout = (int) ($options["timeout"] ?? 0);
        if ($timeout > 0) {
            $requestOptions["timeout"] = $timeout;
        }

        if ($provider === "openai") {
            $openai = [];
            $isResponses = $this->isResponsesEndpoint($endpoint);

            $maxTokens = (int) ($options["max_tokens"] ?? 0);
            if ($maxTokens > 0) {
                $tokenParam = $isResponses
                    ? "max_output_tokens"
                    : (stripos($model, "gpt-5") === 0 ? "max_completion_tokens" : "max_tokens");
                $openai[$tokenParam] = $maxTokens;
            }

            if ($isResponses) {
                $reasoningEffort = trim((string) ($options["reasoning_effort"] ?? ""));
                if ($reasoningEffort !== "") {
                    if ($reasoningEffort === "minimal") {
                        $reasoningEffort = "none";
                    }
                    $openai["reasoning"] = [
                        "effort" => $reasoningEffort,
                    ];
                }

                $verbosity = trim((string) ($options["verbosity"] ?? ""));
                if ($verbosity !== "") {
                    $openai["text"] = [
                        "verbosity" => $verbosity,
                    ];
                }
            }

            if ($openai) {
                $requestOptions["openai"] = $openai;
            }
        }

        return $requestOptions;
    }

    /**
     * @param array $entry
     * @param array $data
     * @return \stdClass
     */
    protected function normalizeAgentResponse(array $entry, array $data): \stdClass
    {
        $model = (string) ($entry["model"] ?? "");
        $provider = (string) ($entry["provider"] ?? "");
        $entryEndpoint = (string) ($entry["endpoint"] ?? "");
        $endpoint = $provider === "anthropic"
            ? "/v1/messages"
            : ($this->isResponsesEndpoint($entryEndpoint) ? "/v1/responses" : "/v1/chat/completions");

        if (isset($data["error"])) {
            $message = is_array($data["error"])
                ? (string) ($data["error"]["message"] ?? json_encode($data["error"]))
                : (string) $data["error"];
            $code = is_array($data["error"])
                ? strtolower((string) ($data["error"]["code"] ?? "provider_error"))
                : "provider_error";
            if (in_array($code, ["rate_limit_exceeded", "rate_limit", "insufficient_quota", "throttled"], true)) {
                $code = "rate_limit_exceeded";
            }

            return $this->failure($code, $message, $data, $model, $endpoint);
        }

        if ($provider === "anthropic") {
            return $this->normalizeAnthropicResponse($data, $model);
        }

        if ($this->isResponsesEndpoint($entryEndpoint)) {
            return $this->normalizeOpenAIResponsesResponse($data, $model);
        }

        return $this->normalizeOpenAIResponse($data, $model);
    }

    /**
     * @param array $data
     * @param string $model
     * @return \stdClass
     */
    protected function normalizeOpenAIResponse(array $data, string $model): \stdClass
    {
        $content = $this->openAICompatibleContent($data);
        if ($content === "") {
            return $this->failure("unexpected_response", $this->_("AgentTools model returned an empty response."), $data, $model, "/v1/chat/completions");
        }

        $usage = (array) ($data["usage"] ?? []);

        return (object) [
            "ok" => true,
            "text" => $content,
            "model" => $model,
            "endpoint" => "/v1/chat/completions",
            "input_tokens" => (int) ($usage["prompt_tokens"] ?? 0),
            "output_tokens" => (int) ($usage["completion_tokens"] ?? 0),
            "raw" => $data,
        ];
    }

    /**
     * @param array $data
     * @param string $model
     * @return \stdClass
     */
    protected function normalizeOpenAIResponsesResponse(array $data, string $model): \stdClass
    {
        $content = $this->openAIResponsesContent($data);
        if ($content === "") {
            return $this->failure("unexpected_response", $this->_("OpenAI Responses API returned an empty response."), $data, $model, "/v1/responses");
        }

        $usage = (array) ($data["usage"] ?? []);

        return (object) [
            "ok" => true,
            "text" => $content,
            "model" => $model,
            "endpoint" => "/v1/responses",
            "input_tokens" => (int) ($usage["input_tokens"] ?? $usage["prompt_tokens"] ?? 0),
            "output_tokens" => (int) ($usage["output_tokens"] ?? $usage["completion_tokens"] ?? 0),
            "raw" => $data,
        ];
    }

    /**
     * @param array $data
     * @param string $model
     * @return \stdClass
     */
    protected function normalizeAnthropicResponse(array $data, string $model): \stdClass
    {
        $content = "";
        foreach (($data["content"] ?? []) as $block) {
            if (!is_array($block) || ($block["type"] ?? "") !== "text") {
                continue;
            }
            $content .= (string) ($block["text"] ?? "");
        }

        if (trim($content) === "") {
            return $this->failure("unexpected_response", $this->_("Anthropic returned an empty response."), $data, $model, "/v1/messages");
        }

        $usage = (array) ($data["usage"] ?? []);

        return (object) [
            "ok" => true,
            "text" => trim($content),
            "model" => $model,
            "endpoint" => "/v1/messages",
            "input_tokens" => (int) ($usage["input_tokens"] ?? 0),
            "output_tokens" => (int) ($usage["output_tokens"] ?? 0),
            "raw" => $data,
        ];
    }

    /**
     * @param string $error
     * @param string $message
     * @param array $raw
     * @param string $model
     * @param string $endpoint
     * @return \stdClass
     */
    protected function failure(string $error, string $message, array $raw, string $model, string $endpoint): \stdClass
    {
        return (object) [
            "ok" => false,
            "error" => $error,
            "message" => $message,
            "model" => $model,
            "endpoint" => $endpoint,
            "raw" => $raw,
        ];
    }

    /**
     * @param array $messages
     * @return array|array[]
     */
    protected function normalizeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = (string) ($message["role"] ?? "user");
            $content = $message["content"] ?? "";
            $out[] = [
                "role" => in_array($role, ["system", "user", "assistant"], true) ? $role : "user",
                "content" => is_scalar($content) ? (string) $content : json_encode($content),
            ];
        }

        return $out ?: [["role" => "user", "content" => ""]];
    }

    /**
     * @param array $data
     * @return string
     */
    protected function openAICompatibleContent(array $data): string
    {
        $content = $data["choices"][0]["message"]["content"] ?? "";
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $text = "";
            foreach ($content as $part) {
                if (is_string($part)) {
                    $text .= $part;
                } elseif (is_array($part)) {
                    $text .= (string) ($part["text"] ?? $part["content"] ?? "");
                }
            }

            return trim($text);
        }

        return trim((string) ($data["output_text"] ?? ""));
    }

    /**
     * @param array $data
     * @return string
     */
    protected function openAIResponsesContent(array $data): string
    {
        $outputText = trim((string) ($data["output_text"] ?? ""));
        if ($outputText !== "") {
            return $outputText;
        }

        $text = "";
        foreach (($data["output"] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (($item["content"] ?? []) as $part) {
                if (!is_array($part)) {
                    continue;
                }

                if (($part["type"] ?? "") === "output_text" || ($part["type"] ?? "") === "text") {
                    $text .= (string) ($part["text"] ?? "");
                }
            }
        }

        return trim($text);
    }

    /**
     * @param string $endpoint
     * @return bool
     */
    protected function isResponsesEndpoint(string $endpoint): bool
    {
        return stripos(trim($endpoint), "/responses") !== false;
    }
}
