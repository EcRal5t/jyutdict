<?php
/**
 * 公共輔助函數
 */

/**
 * 輸出 JSON 響應並退出
 */
function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * API 根目錄信息
 */
function printApiRootJson() {
    echo json_encode([
        "name" => "泛粵大典 API",
        "version" => [0, 6, 7],
        "description" => "泛粵大典數據查詢接口",
        "endpoints" => [
            "v1.0" => [
                "detail" => [
                    "url" => "/api/v1.0/detail",
                    "description" => "字元詳情（查字、檢音）",
                    "help" => "/api/v1.0/detail?help"
                ],
                "sheet" => [
                    "url" => "/api/v1.0/sheet",
                    "description" => "泛粵字表查詢",
                    "help" => "/api/v1.0/sheet?help"
                ],
                "articles" => [
                    "url" => "/api/v1.0/articles",
                    "description" => "文章列表"
                ],
                "comments" => [
                    "url" => "/api/v1.0/comments/...",
                    "description" => "評論系統"
                ]
            ],
            "v0.9" => [
                "detail" => "/api/v0.9/detail",
                "sheet" => "/api/v0.9/sheet",
                "status" => "deprecated"
            ]
        ],
        "help" => "各端點附加 ?help 參數獲取詳細說明"
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
