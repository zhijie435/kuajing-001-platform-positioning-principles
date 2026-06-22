<?php
class Response {
    public static function success($data = null, $message = 'success') {
        http_response_code(200);
        echo json_encode([
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($code, $message, $data = null, $httpCode = null) {
        if ($httpCode === null) {
            $httpCode = $code >= 1000 ? 400 : $code;
        }
        if ($httpCode < 100 || $httpCode >= 600) {
            $httpCode = 400;
        }
        http_response_code($httpCode);
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function unauthorized($message = '未授权访问') {
        self::error(401, $message);
    }

    public static function forbidden($message = '禁止访问，超出商用边界') {
        self::error(403, $message);
    }

    public static function badRequest($message = '请求参数错误') {
        self::error(400, $message);
    }

    public static function notFound($message = '资源不存在') {
        self::error(404, $message);
    }

    public static function redLineBlock($code, $message, $detail = null) {
        $httpCode = $code >= 1000 ? 403 : $code;
        self::error($code, '红线校验失败: ' . $message, [
            'block_type' => 'redline',
            'detail' => $detail
        ], $httpCode);
    }

    public static function commercialBlock($code, $message, $detail = null) {
        $httpCode = $code >= 1000 ? 402 : $code;
        self::error($code, '商用边界限制: ' . $message, [
            'block_type' => 'commercial',
            'detail' => $detail
        ], $httpCode);
    }

    public static function platformBlock($code, $message, $detail = null) {
        $httpCode = $code >= 1000 ? 403 : $code;
        self::error($code, '平台定位限制: ' . $message, [
            'block_type' => 'platform',
            'detail' => $detail
        ], $httpCode);
    }
}
