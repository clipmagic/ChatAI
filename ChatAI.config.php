<?php namespace ProcessWire;

if (!function_exists('\ProcessWire\fetchOpenAIModels')) {
    function fetchOpenAIModels($configData): array
    {
        $apiKey = trim((string) ($configData["api_key"] ?? ""));
        if ($apiKey === "") {
            return [];
        }

        $http = new WireHttp();
        $http->setHeaders([
            "Authorization" => "Bearer " . $apiKey,
        ]);

        $json = $http->get("https://api.openai.com/v1/models");
        if (!$json) {
            return [];
        }

        $data = json_decode($json, true);
        if (empty($data["data"]) || !is_array($data["data"])) {
            return [];
        }

        $models = [];

        $excludeTerms = [
            "codex",
            "realtime",
            "audio",
            "search",
            "transcribe",
            "tts",
            "image",
            "embedding",
            "moderation",
            "latest",
        ];

        foreach ($data["data"] as $item) {
            $id = $item["id"] ?? "";
            if (!$id || !str_starts_with($id, "gpt-5")) {
                continue;
            }

            // exclude dated snapshots like gpt-5-mini-2025-02-10
            if (preg_match('/-\d{4}-\d{2}-\d{2}$/', $id)) {
                continue;
            }

            // exclude unwanted variants
            foreach ($excludeTerms as $term) {
                if (str_contains($id, $term)) {
                    continue 2;
                }
            }

            $models[$id] = $id === "gpt-5-mini" ? "$id (recommended)" : $id;
        }

        ksort($models);

        return $models;
    }
}

if (!function_exists('\ProcessWire\refreshModels')) {
    function refreshModels(array $configData): array
    {
        $models = \ProcessWire\fetchOpenAIModels($configData);

        if ($models) {
            $current = $configData["model"] ?? "";
            if (!$current || !isset($models[$current])) {
                $configData["model"] = array_key_first($models);
            }
            $configData["available_models"] = $models;
            wire()->message(__("Available models refreshed from OpenAI."));
        } else {
            wire()->warning(
                __(
                    "Could not fetch models from OpenAI. Keeping existing model list.",
                ),
            );
        }
        return $configData;
    }
}

// default models if nothing fetched yet
$defaultModels = [
    "gpt-5-mini" => "gpt-5-mini (recommended)",
    "gpt-5-nano" => "gpt-5-nano (fastest / cheapest)",
    "gpt-5.2" => "gpt-5.2 (stronger reasoning)",
    "gpt-5.1" => "gpt-5.1",
    "gpt-5" => "gpt-5 (legacy)",
];

$configData = $this->getConfig("ChatAI");
$inputPost = wire("input")->post;

if (wire("input")->requestMethod("POST") && $inputPost->refresh_models !== null) {
    $postedApiKey = trim((string) ($inputPost->api_key ?? ""));
    if ($postedApiKey !== "") {
        $configData["api_key"] = $postedApiKey;
    }

    $configData = refreshModels($configData);
}
$modelList = $configData["available_models"] ?? $defaultModels;

$roles = wire("roles");
$promptRoleOptions = [];

foreach ($roles as $role) {
    if (in_array($role->name, ["guest", "superuser"], true)) {
        continue;
    }
    $promptRoleOptions[$role->name] = $role->name;
}

$config = [
    [
        "name" => "openai",
        "type" => "fieldset",
        "label" => __("OpenAI Settings"),
        "children" => [
            [
                "name" => "api_key",
                "type" => "text",
                "label" => __("OpenAI API Key"),
                "required" => true,
                "collapsed" => 5,
                "value" => "",
            ],
            [
                "name" => "model",
                "type" => "select",
                "label" => __("OpenAI Model"),
                "columnWidth" => 100,
                "options" => $modelList,
                "value" => "gpt-5-mini",
            ],
            [
                "name" => "refresh_models",
                "type" => "submit",
                "value" => __("Refresh Model List"),
                "icon" => "refresh",
                "columnWidth" => 100,
            ],
            [
                "name" => "finetune",
                "type" => "fieldset",
                "label" => __("Fine Tune Settings"),
                "children" => [
                    [
                        "name" => "verbosity",
                        "type" => "select",
                        "label" => __("Verbosity"),
                        "columnWidth" => 20,
                        "options" => [
                            "low" => "Low",
                            "medium" => "Medium",
                            "high" => "High",
                        ],
                        "value" => "medium",
                    ],
                    [
                        "name" => "reasoningEffort",
                        "type" => "select",
                        "label" => __("Reasoning Effort"),
                        "columnWidth" => 20,
                        "options" => [
                            "minimal" => "Minimal",
                            "low" => "Low",
                            "medium" => "Medium",
                            "high" => "High",
                        ],
                        "value" => "medium",
                    ],
                    [
                        "name" => "max_completion_tokens",
                        "type" => "integer",
                        "label" => __("Max tokens"),
                        "columnWidth" => 20,
                        "notes" => __("Max tokens per message"),
                        "value" => 2000,
                    ],
                    [
                        "name" => "max_messages",
                        "type" => "integer",
                        "label" => __("Maximum questions"),
                        "columnWidth" => 20,
                        "notes" => __("Max questions per conversation"),
                        "value" => 20,
                    ],
                    [
                        "name" => "max_strikes",
                        "type" => "integer",
                        "label" => __("Maximum blacklist"),
                        "columnWidth" => 20,
                        "notes" => __(
                            "Number of blacklist strikes before being blocked",
                        ),
                        "value" => 3,
                    ],
                ],
            ],
        ],
    ],
    [
        "name" => "prompt_roles",
        "type" => "checkboxes",
        "label" => __("Roles allowed to craft prompt"),
        "options" => $promptRoleOptions,
        "value" => [],
        "columnWidth" => 50,
        "notes" => __(
            "Superuser is always allowed.\r\n" .
                "If no roles are selected, only superusers can access the ChatAI dashboard tabs.\r\n" .
                "Select one or more roles (for example the 'chatai' role created on install) to allow them as well.",
        ),
    ],

    [
        "name" => "files",
        "type" => "fieldset",
        "label" => __("Files"),
        "children" => [
            [
                "name" => "widget",
                "type" => "text",
                "label" => __("Path to widget file"),
                "notes" => "Default is /site/modules/ChatAI/chatai-widget.php",
                "value" => "/site/modules/ChatAI/chatai-widget.php",
                "stripTags" => true,
            ],
            [
                "name" => "css",
                "type" => "text",
                "label" => __("Path to CSS file"),
                "notes" => "Default is /site/modules/ChatAI/chatai.css",
                "value" => "/site/modules/ChatAI/chatai.css",
                "stripTags" => true,
            ],
            [
                "name" => "js",
                "type" => "text",
                "label" => __("Path to JS file"),
                "notes" => "Default is /site/modules/ChatAI/chatai.js",
                "value" => "/site/modules/ChatAI/chatai.js",
                "stripTags" => true,
            ],
        ],
    ],

    [
        "name" => "chatai_debug",
        "type" => "fieldset",
        "label" => __("Debug helpers"),
        "collapsed" => 1,
        "description" => __(
            "Development-only tools for testing edge cases. These settings should remain OFF in production.",
        ),
        "children" => [
            [
                "name" => "debug_enabled",
                "type" => "checkbox",
                "label" => __("Enable debug helpers"),
                "value" => 0,
            ],
            [
                "name" => "debug_force_cutoff",
                "type" => "checkbox",
                "label" => __("Force cutoff response"),
                "notes" => __(
                    "Truncates the assistant response server-side, sanitises to plain text, and sets cutoff=true.",
                ),
                "showIf" => "debug_enabled=1",
                "value" => 0,
            ],
            [
                "name" => "debug_cutoff_mode",
                "type" => "select",
                "label" => __("Cutoff mode"),
                "options" => [
                    "chars" => __("Characters"),
                    "words" => __("Words"),
                    "awkward" => __("Awkward boundary (stress test)"),
                ],
                "columnWidth" => 33,
                "showIf" => "debug_enabled=1, debug_force_cutoff=1",
                "value" => "chars",
            ],
            [
                "name" => "debug_cutoff_value",
                "type" => "integer",
                "label" => __("Cutoff value"),
                "notes" => __(
                    "For Characters: number of chars. For Words: number of words. For Awkward: base char count before stress text is appended.",
                ),
                "columnWidth" => 33,
                "showIf" => "debug_enabled=1, debug_force_cutoff=1",
                "value" => 200,
            ],
            [
                "name" => "debug_latency_ms",
                "type" => "select",
                "label" => __("Artificial delay"),
                "notes" => __(
                    "Adds a server-side delay (superuser only) to test busy guards and UX states.",
                ),
                "options" => [
                    "0" => __("None"),
                    "250" => __("250 ms"),
                    "1000" => __("1 s"),
                    "3000" => __("3 s"),
                    "8000" => __("8 s"),
                ],
                "columnWidth" => 33,
                "showIf" => "debug_enabled=1",
                "value" => "0",
            ],
        ],
    ],
];
