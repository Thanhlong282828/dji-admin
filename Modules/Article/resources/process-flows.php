<?php

$content = static fn (string $current): array => [
    'id' => 'article.content-authoring', 'category' => 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['articles', 'article-create']),
];

return ['articles' => $content('articles'), 'article-create' => $content('article-create')];
