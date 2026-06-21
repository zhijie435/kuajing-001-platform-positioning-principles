<?php
class LicenseStore {
    private static $storageFile = __DIR__ . '/../storage/license.json';
    private static $cache = null;

    const EDITION_MAP = [
        'STD' => ['code' => 'standard', 'label' => '标准版'],
        'PRO' => ['code' => 'professional', 'label' => '专业版'],
        'ENT' => ['code' => 'enterprise', 'label' => '企业版']
    ];

    public static function load() {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (!file_exists(self::$storageFile)) {
            self::$cache = self::buildDefaultStore();
            self::persist();
            return self::$cache;
        }

        $content = @file_get_contents(self::$storageFile);
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['records'])) {
            self::$cache = self::buildDefaultStore();
            self::persist();
            return self::$cache;
        }

        self::$cache = $data;
        return self::$cache;
    }

    private static function buildDefaultStore() {
        $defaultKey = LICENSE_KEY;
        $editionSuffix = substr($defaultKey, -3);
        $editionInfo = self::EDITION_MAP[$editionSuffix] ?? self::EDITION_MAP['STD'];

        $record = [
            'license_key' => $defaultKey,
            'edition_code' => $editionInfo['code'],
            'edition_label' => $editionInfo['label'],
            'expire' => LICENSE_EXPIRE,
            'issued_at' => date('Y-m-d', strtotime('-1 year')),
            'max_users' => LICENSE_MAX_USERS,
            'max_clients' => LICENSE_MAX_CLIENTS,
            'features' => self::getEditionFeatures($editionInfo['code']),
            'remark' => '初始内置 License',
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        return [
            'active_key' => $defaultKey,
            'records' => [$defaultKey => $record]
        ];
    }

    public static function persist() {
        $dir = dirname(self::$storageFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = self::$storageFile . '.tmp';
        @file_put_contents($tmp, json_encode(self::$cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @rename($tmp, self::$storageFile);
    }

    public static function save($licenseKey, $extra = []) {
        $store = self::load();

        if (!self::verifySignature($licenseKey)) {
            return false;
        }

        $editionSuffix = substr($licenseKey, -3);
        $editionInfo = self::EDITION_MAP[$editionSuffix] ?? self::EDITION_MAP['STD'];

        $maxUsers = $editionSuffix === 'ENT' ? 500 : ($editionSuffix === 'PRO' ? 200 : 100);
        $maxClients = $editionSuffix === 'ENT' ? 100000 : ($editionSuffix === 'PRO' ? 50000 : 10000);

        $now = date('Y-m-d H:i:s');
        $existing = $store['records'][$licenseKey] ?? null;

        $record = [
            'license_key' => $licenseKey,
            'edition_code' => $editionInfo['code'],
            'edition_label' => $editionInfo['label'],
            'expire' => $extra['expire'] ?? date('Y-m-d', strtotime('+1 year')),
            'issued_at' => $existing['issued_at'] ?? $now,
            'max_users' => $maxUsers,
            'max_clients' => $maxClients,
            'features' => self::getEditionFeatures($editionInfo['code']),
            'remark' => $extra['remark'] ?? ($existing['remark'] ?? ''),
            'updated_at' => $now,
            'created_at' => $existing['created_at'] ?? $now
        ];

        $store['records'][$licenseKey] = $record;
        $store['active_key'] = $licenseKey;
        self::$cache = $store;
        self::persist();

        return $record;
    }

    public static function getActive() {
        $store = self::load();
        $activeKey = $store['active_key'] ?? null;
        if (!$activeKey || !isset($store['records'][$activeKey])) {
            return null;
        }
        return $store['records'][$activeKey];
    }

    public static function setActive($licenseKey) {
        $store = self::load();
        if (!isset($store['records'][$licenseKey])) {
            return false;
        }
        $store['active_key'] = $licenseKey;
        self::$cache = $store;
        self::persist();
        return true;
    }

    public static function getList() {
        $store = self::load();
        $activeKey = $store['active_key'] ?? null;

        $list = [];
        foreach ($store['records'] as $key => $record) {
            $item = $record;
            $item['is_active'] = ($key === $activeKey);
            $item['days_left'] = max(0, floor((strtotime($record['expire']) - time()) / 86400));
            $item['status'] = self::calcStatus($record, $item['is_active']);
            $list[] = $item;
        }

        usort($list, function ($a, $b) {
            $aActive = $a['is_active'] ? 1 : 0;
            $bActive = $b['is_active'] ? 1 : 0;
            if ($aActive !== $bActive) {
                return $bActive - $aActive;
            }
            return strcmp($b['updated_at'], $a['updated_at']);
        });

        return $list;
    }

    public static function getDetail($licenseKey) {
        $store = self::load();
        if (!isset($store['records'][$licenseKey])) {
            return null;
        }
        $record = $store['records'][$licenseKey];
        $record['is_active'] = ($licenseKey === ($store['active_key'] ?? null));
        $record['days_left'] = max(0, floor((strtotime($record['expire']) - time()) / 86400));
        $record['status'] = self::calcStatus($record, $record['is_active']);
        return $record;
    }

    private static function calcStatus($record, $isActive) {
        if (strtotime($record['expire']) < time()) {
            return 'expired';
        }
        return $isActive ? 'active' : 'standby';
    }

    public static function verifySignature($licenseKey) {
        return (bool)preg_match('/^CRM-LICENSE-\d{4}-(STD|PRO|ENT)$/', $licenseKey);
    }

    private static function getEditionFeatures($editionCode) {
        $boundary = COMMERCIAL_FEATURE_BOUNDARY;
        $features = [];
        $priority = ['standard', 'professional', 'enterprise'];
        foreach ($priority as $ed) {
            if (!isset($boundary[$ed])) continue;
            $features = array_merge($features, $boundary[$ed]);
            if ($ed === $editionCode) break;
        }
        return $features;
    }

    public static function getActiveLicenseKey() {
        $store = self::load();
        return $store['active_key'] ?? LICENSE_KEY;
    }
}
