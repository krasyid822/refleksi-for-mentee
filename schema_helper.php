<?php
// File: schema_helper.php
// Menemukan varian tabel yang ada di database, menyusun katalog untuk AI,
// memvalidasi hasil ekstraksi, dan melakukan INSERT dinamis dengan whitelist
// nama tabel/kolom (bukan SQL mentah dari AI).

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/app_helper.php';

if (!function_exists('schema_cache_file')) {
    function schema_cache_file(): string
    {
        return app_cache_dir() . DIRECTORY_SEPARATOR . 'schema.json';
    }
}

if (!function_exists('schema_discover')) {
    /**
     * @return array{tables:array,fetched:int}
     */
    function schema_discover(bool $force = false): array
    {
        $cacheFile = schema_cache_file();
        $cached = app_json_read($cacheFile, []);
        if (!$force && isset($cached['tables'], $cached['fetched']) && (time() - (int) $cached['fetched']) < 300) {
            return $cached;
        }

        global $conn;
        if (!($conn instanceof mysqli)) {
            return ['tables' => [], 'fetched' => 0];
        }

        $tables = [];
        $res = @$conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        if (!$res) {
            return ['tables' => [], 'fetched' => 0];
        }

        $skip = ['information_schema', 'mysql', 'performance_schema', 'sys'];
        $internalNames = ['users', 'user', 'admin', 'admins', 'sessions', 'session', 'tokens', 'token', 'logs', 'log', 'cache', 'migrations', 'settings', 'password_resets'];
        while ($row = $res->fetch_row()) {
            $table = (string) $row[0];
            $lowerTable = strtolower($table);
            if ($table === '' || in_array($table, $skip, true) || str_starts_with($table, '_') || in_array($lowerTable, $internalNames, true)) {
                continue;
            }

            $columns = [];
            $safeTable = str_replace('`', '``', $table);
            $colRes = @$conn->query("SHOW FULL COLUMNS FROM `{$safeTable}`");
            if (!$colRes) {
                continue;
            }

            while ($col = $colRes->fetch_assoc()) {
                $type = strtolower((string) ($col['Type'] ?? ''));
                $extra = strtolower((string) ($col['Extra'] ?? ''));
                $nullable = strtoupper((string) ($col['Null'] ?? 'NO')) === 'YES';
                $auto = str_contains($extra, 'auto_increment');
                $default = array_key_exists('Default', $col) ? $col['Default'] : null;

                $enum = [];
                if (preg_match('/^(?:enum|set)\((.*)\)$/i', $type, $m)) {
                    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $em);
                    $enum = array_map(static fn($v) => stripcslashes($v), $em[1] ?? []);
                }

                $columns[] = [
                    'name'     => (string) ($col['Field'] ?? ''),
                    'type'     => $type,
                    'nullable' => $nullable,
                    'default'  => $default,
                    'auto'     => $auto,
                    'required' => (!$nullable && $default === null && !$auto),
                    'enum'     => $enum,
                    'comment'  => (string) ($col['Comment'] ?? ''),
                ];
            }

            $tables[] = ['table' => $table, 'columns' => $columns];
        }

        // Prioritaskan tabel yang namanya mengandung "refleksi", tetapi tetap
        // sertakan varian lain yang bukan tabel internal.
        usort($tables, static function (array $a, array $b): int {
            $aRef = str_contains(strtolower((string) ($a['table'] ?? '')), 'refleksi');
            $bRef = str_contains(strtolower((string) ($b['table'] ?? '')), 'refleksi');
            return ($bRef <=> $aRef) ?: strcmp((string) $a['table'], (string) $b['table']);
        });

        $data = ['tables' => $tables, 'fetched' => time()];
        app_json_write($cacheFile, $data);
        return $data;
    }
}

if (!function_exists('schema_table_map')) {
    /** @return array<string,array> */
    function schema_table_map(array $discovered): array
    {
        $map = [];
        foreach (($discovered['tables'] ?? []) as $t) {
            if (!empty($t['table'])) {
                $map[(string) $t['table']] = $t;
            }
        }
        return $map;
    }
}

if (!function_exists('schema_catalog_for_prompt')) {
    /**
     * Katalog ringkas untuk prompt. Tanda ! = wajib diisi, AUTO = auto increment.
     */
    function schema_catalog_for_prompt(array $discovered, int $maxTables = 24): string
    {
        $lines = [];
        $count = 0;
        foreach (($discovered['tables'] ?? []) as $table) {
            if ($count++ >= $maxTables) {
                $lines[] = '... (tabel lain disembunyikan)';
                break;
            }
            $name = (string) ($table['table'] ?? '');
            if ($name === '') {
                continue;
            }
            $lines[] = 'TABLE ' . $name;
            foreach (($table['columns'] ?? []) as $col) {
                $flags = [];
                if (!empty($col['required'])) {
                    $flags[] = 'WAJIB';
                }
                if (!empty($col['auto'])) {
                    $flags[] = 'AUTO';
                }
                if ($col['default'] !== null && $col['default'] !== '') {
                    $flags[] = 'default=' . (is_scalar($col['default']) ? (string) $col['default'] : '');
                }
                if (!empty($col['enum'])) {
                    $flags[] = 'pilihan=' . implode('|', $col['enum']);
                }
                $lines[] = '  - ' . $col['name'] . ' : ' . $col['type'] . ($flags ? ' [' . implode(', ', $flags) . ']' : '');
            }
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('schema_default_for_column')) {
    function schema_default_for_column(array $col): mixed
    {
        $name = strtolower((string) ($col['name'] ?? ''));
        $type = strtolower((string) ($col['type'] ?? ''));

        $known = [
            'nama_mentee'      => 'Mentee',
            'nama_mentor'      => 'Mentor',
            'nama_disamarkan'  => 0,
            'sandi_nama'       => null,
            'periode_minggu'   => 1,
            'periode_tanggal'  => date('d M Y'),
            'skor_energi'      => 5,
            'kalimat_epik'     => '',
        ];
        if (array_key_exists($name, $known)) {
            return $known[$name];
        }

        if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return 0;
        }
        if (str_contains($type, 'date') || str_contains($type, 'timestamp')) {
            return date('Y-m-d H:i:s');
        }
        if (str_contains($type, 'time')) {
            return date('H:i:s');
        }
        if (!empty($col['enum'])) {
            return $col['enum'][0] ?? '';
        }
        return '';
    }
}

if (!function_exists('schema_normalize_value')) {
    function schema_normalize_value(mixed $value, array $col): mixed
    {
        $name = strtolower((string) ($col['name'] ?? ''));
        $type = strtolower((string) ($col['type'] ?? ''));
        $nullable = !empty($col['nullable']);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            if ($nullable) {
                return null;
            }
            if (str_contains($type, 'date') || str_contains($type, 'timestamp')) {
                return date('Y-m-d H:i:s');
            }
            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                return 0;
            }
            return '';
        }

        if (!empty($col['enum'])) {
            $s = is_scalar($value) ? trim((string) $value) : '';
            if (in_array($s, $col['enum'], true)) {
                return $s;
            }
            return $col['enum'][0] ?? '';
        }

        if (preg_match('/^tinyint\(1\)/', $type)) {
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            $s = strtolower(trim((string) $value));
            if (in_array($s, ['1', 'true', 'ya', 'yes', 'y'], true)) {
                return 1;
            }
            if (in_array($s, ['0', 'false', 'tidak', 'no', 'n', ''], true)) {
                return 0;
            }
            return is_numeric($value) ? (int) $value : 0;
        }

        if (str_contains($type, 'int')) {
            return is_numeric($value) ? (int) $value : 0;
        }
        if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return is_numeric($value) ? (float) $value : 0;
        }
        if (str_contains($type, 'date') || str_contains($type, 'timestamp')) {
            $s = strtolower(trim((string) $value));
            if (in_array($s, ['now', 'sekarang', 'hari ini'], true)) {
                return date('Y-m-d H:i:s');
            }
            $ts = strtotime((string) $value);
            return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        }
        if (str_contains($type, 'time')) {
            return is_string($value) ? substr(trim($value), 0, 8) : date('H:i:s');
        }
        if (str_contains($type, 'json')) {
            return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        }

        $s = is_scalar($value) ? trim((string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);
        if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
            $max = (int) $m[1];
            if ($max > 0 && function_exists('mb_substr')) {
                $s = mb_substr($s, 0, $max);
            } elseif ($max > 0) {
                $s = substr($s, 0, $max);
            }
        }
        return $s;
    }
}

if (!function_exists('schema_prepare_payload')) {
    /**
     * Validasi tabel + kolom, normalisasi nilai, dan kumpulkan field wajib
     * yang belum terisi.
     * @return array{ok:bool,table?:string,data?:array,missing?:array,warnings?:array,error?:string}
     */
    function schema_prepare_payload(array $payload, array $discovered): array
    {
        $map = schema_table_map($discovered);
        $table = trim((string) ($payload['table'] ?? ''));
        if ($table === '' || !isset($map[$table])) {
            return ['ok' => false, 'error' => 'Tabel hasil pilihan AI tidak ada di database.'];
        }

        $raw = $payload['data'] ?? [];
        if (!is_array($raw)) {
            return ['ok' => false, 'error' => 'Field "data" harus berupa objek.'];
        }

        $cols = [];
        foreach (($map[$table]['columns'] ?? []) as $col) {
            $cols[(string) $col['name']] = $col;
        }

        // Jangan pernah menyimpan cerita mentee ke tabel yang tidak terlihat
        // seperti tabel refleksi (mis. tabel user/admin yang ikut terdeteksi).
        $looksLikeReflection = str_contains(strtolower($table), 'refleksi');
        foreach (['nama_mentee', 'nama_mentor', 'tanggal_submit', 'kalimat_epik', 'periode_minggu'] as $marker) {
            if (isset($cols[$marker])) {
                $looksLikeReflection = true;
                break;
            }
        }
        if (!$looksLikeReflection) {
            return ['ok' => false, 'error' => 'Tabel yang dipilih AI bukan tabel refleksi yang aman untuk diisi.'];
        }

        $data = [];
        $warnings = [];
        foreach ($raw as $key => $value) {
            $key = (string) $key;
            if (!isset($cols[$key])) {
                // Model kadang salah ketik satu-dua huruf (mis. momen_beresan).
                // Koreksi hanya bila sangat dekat dengan nama kolom yang ada.
                $best = null;
                $bestDistance = 3;
                foreach (array_keys($cols) as $candidate) {
                    $distance = levenshtein(strtolower($key), strtolower((string) $candidate));
                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $best = (string) $candidate;
                    }
                }
                if ($best !== null) {
                    $warnings[] = 'Kolom "' . $key . '" dikoreksi menjadi "' . $best . '".';
                    $key = $best;
                } else {
                    $warnings[] = 'Kolom tidak dikenal diabaikan: ' . $key;
                    continue;
                }
            }
            if (!empty($cols[$key]['auto'])) {
                continue;
            }
            $data[$key] = schema_normalize_value($value, $cols[$key]);
        }

        $missing = [];
        foreach ($cols as $name => $col) {
            if (!empty($col['required']) && !array_key_exists($name, $data)) {
                $missing[] = $name;
            }
        }

        return [
            'ok'       => true,
            'table'    => $table,
            'data'     => $data,
            'missing'  => $missing,
            'warnings' => $warnings,
        ];
    }
}

if (!function_exists('schema_fill_defaults')) {
    function schema_fill_defaults(array $data, array $missing, array $discovered, string $table): array
    {
        $map = schema_table_map($discovered);
        $cols = [];
        foreach (($map[$table]['columns'] ?? []) as $col) {
            $cols[(string) $col['name']] = $col;
        }
        foreach ($missing as $name) {
            if (isset($cols[$name]) && !array_key_exists($name, $data)) {
                $data[$name] = schema_default_for_column($cols[$name]);
            }
        }
        return $data;
    }
}

if (!function_exists('schema_insert')) {
    /**
     * INSERT dinamis dengan whitelist kolom dari skema.
     * @return array{ok:bool,id?:int,error?:string}
     */
    function schema_insert(mysqli $conn, string $table, array $data, array $discovered): array
    {
        $map = schema_table_map($discovered);
        if (!isset($map[$table])) {
            return ['ok' => false, 'error' => 'Tabel tidak dikenal.'];
        }

        $allowed = [];
        foreach (($map[$table]['columns'] ?? []) as $col) {
            $allowed[(string) $col['name']] = $col;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if (isset($allowed[$key]) && empty($allowed[$key]['auto'])) {
                $clean[$key] = $value;
            }
        }
        if ($clean === []) {
            return ['ok' => false, 'error' => 'Tidak ada kolom yang bisa disimpan.'];
        }

        $columns = array_keys($clean);
        $quoted = array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Prepare gagal: ' . $conn->error];
        }

        $types = '';
        $values = [];
        foreach ($columns as $c) {
            $col = $allowed[$c];
            $type = strtolower((string) $col['type']);
            $value = $clean[$c];
            if ($value === null) {
                $types .= 's';
            } elseif (str_contains($type, 'int')) {
                $types .= 'i';
            } elseif (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $bind = [$types];
        foreach ($values as $i => $_) {
            $bind[] = &$values[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'error' => 'Gagal menyimpan: ' . $err];
        }

        $id = (int) $conn->insert_id;
        $stmt->close();
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('schema_table_names')) {
    function schema_table_names(array $discovered): array
    {
        return array_values(array_map(static fn($t) => (string) $t['table'], $discovered['tables'] ?? []));
    }
}
