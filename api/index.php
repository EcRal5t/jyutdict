<?php
/**
 * API 根目录 - 返回可用端点列表
 */
header('Content-type: application/json');
include_once(__DIR__ . '/core/helpers.php');

printApiRootJson();

