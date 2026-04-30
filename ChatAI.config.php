<?php namespace ProcessWire;

require_once __DIR__ . "/classes/ChatAIModelClient.php";

$configData = $this->getConfig("ChatAI");
$modelClient = new ChatAIModelClient();
$modelClient->setWire(wire());
$modelList = $modelClient->modelOptions();
$selectedModel = $modelClient->resolveModelId((string) ($configData["agenttools_model_id"] ?? ""));
$selectedEmbeddingModel = $modelClient->resolveModelId((string) ($configData["agenttools_embedding_model_id"] ?? ""));

if (!$selectedModel || ($modelList && !isset($modelList[$selectedModel]))) {
    $selectedModel = (string) (array_key_first($modelList) ?? "");
}

if (!$selectedEmbeddingModel || ($modelList && !isset($modelList[$selectedEmbeddingModel]))) {
    $selectedEmbeddingModel = (string) (array_key_first($modelList) ?? "");
}

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
        "name" => "agenttools",
        "type" => "fieldset",
        "label" => __("AgentTools LLM"),
        "description" => __("ChatAI uses AgentTools for chat model credentials, provider, endpoint, and model selection."),
        "children" => [
            [
                "name" => "agenttools_model_id",
                "type" => "select",
                "label" => __("Model"),
                "columnWidth" => 100,
                "options" => $modelList ?: ["" => __("No AgentTools models configured")],
                "value" => $selectedModel,
                "notes" => $modelList
                    ? __("Models are configured in AgentTools. Re-save ChatAI after changing AgentTools models.")
                    : __("Configure at least one Engineer model/API key in AgentTools first."),
            ],
            [
                "name" => "finetune",
                "type" => "fieldset",
                "label" => __("Generation Settings"),
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
                        "name" => "reasoning_effort",
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
                        "name" => "llm_timeout",
                        "type" => "integer",
                        "label" => __("Timeout seconds"),
                        "columnWidth" => 20,
                        "value" => 25,
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
        "name" => "agenttools_embeddings",
        "type" => "fieldset",
        "label" => __("RAG Embeddings"),
        "description" => __("Embeddings use a separately selected AgentTools model entry. Configure a dedicated embeddings-capable entry in AgentTools if needed. Changing embedding settings requires a full reindex of vectors."),
        "collapsed" => 5,
        "children" => [
            [
                "name" => "agenttools_embedding_model_id",
                "type" => "select",
                "label" => __("Embedding model"),
                "columnWidth" => 100,
                "options" => $modelList ?: ["" => __("No AgentTools models configured")],
                "value" => $selectedEmbeddingModel,
                "notes" => $modelList
                    ? __("Select an AgentTools entry for embeddings. This can be different from the chat model and should point to an embeddings-capable endpoint/model.")
                    : __("Configure at least one suitable model/API key in AgentTools first."),
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
            [
                "name" => "rag_view",
                "type" => "text",
                "label" => __("Path to indexing view file"),
                "notes" => "Default is /site/modules/ChatAI/classes/RAG/chatai-rag.php",
                "value" => "/site/modules/ChatAI/classes/RAG/chatai-rag.php",
                "stripTags" => true,
            ],
        ],
    ],

];
