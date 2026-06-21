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

    public static function error($code, $message, $data = null) {
        $httpCode = $code >= 1000 ? 400 : $code;
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
        self::error(4500 + $code, '红线校验失败: ' . $message, [
            'block_type' => 'redline',
            'detail' => $detail
        ]);
    }

    public static function commercialBlock($code, $message, $detail = null) {
        self::error($code, '商用边界限制: ' . $message, [
            'block_type' => 'commercial',
            'detail' => $detail
        ]);
    }

    public static function platformBlock($code, $message, $detail = null) {
        self::error($code, '平台定位限制: ' . $message, [
            'block_type' => 'platform',
            'detail' => $detail
        ]);
    }
}
