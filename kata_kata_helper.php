<?php
// File: kata_kata_helper.php
// Cache pertanyaan pemicu refleksi di kata-kata.json.
//
// Siklus:
//   - Cache dipakai terus selama masih segar (default 7 hari) dan jumlahnya cukup.
//   - Saat kedaluwarsa, halaman tetap memakai cache lama lalu memicu refresh
//     di belakang layar (kata-kata-refresh.php). AI hanya dipanggil saat perlu.
//   - Jika AI gagal, cache lama dipertahankan dan percobaan berikutnya ditunda.

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/app_helper.php';
require_once __DIR__ . '/openrouter_helper.php';

if (!function_exists('kata_kata_defaults')) {
    function kata_kata_defaults(): array
    {
        return [
            'Apa yang paling kamu pelajari minggu ini?',
            'Momen kecil apa yang membuatmu tersenyum?',
            'Apa yang ingin kamu lakukan berbeda minggu depan?',
            'Hal apa yang paling menguras energimu?',
            'Siapa yang ingin kamu ajak bicara lebih sering?',
            'Apa satu langkah kecil yang bisa kamu mulai hari ini?',
            'Apa yang sudah kamu syukuri hari ini?',
            'Bagian mana dari dirimu yang ingin kamu rawat?',
            'Apa yang membuatmu merasa cukup akhir-akhir ini?',
            'Tantangan apa yang sedang mengajarkanmu sesuatu?',
            'Apa yang ingin kamu lepaskan minggu ini?',
            'Bagaimana perasaanmu saat ini, tanpa perlu dijelaskan panjang?',
        ];
    }
}

if (!function_exists('kata_kata_file')) {
    function kata_kata_file(): string
    {
        return __DIR__ . '/kata-kata.json';
    }
}

if (!function_exists('kata_kata_ttl')) {
    function kata_kata_ttl(): int
    {
        return 7 * 24 * 3600;
    }
}

if (!function_exists('kata_kata_load')) {
    function kata_kata_load(): array
    {
        $file = kata_kata_file();
        $data = app_json_read($file, []);
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (is_string($item)) {
                    $item = trim($item);
                    if ($item !== '' && !in_array($item, $items, true)) {
                        $items[] = $item;
                    }
                }
            }
        }
        if ($items === []) {
            $items = kata_kata_defaults();
            $data = [
                'version'      => 1,
                'generated_at' => 0,
                'items'        => $items,
                'source'       => 'seed',
            ];
            app_json_write($file, $data);
        }

        return [
            'version'      => (int) ($data['version'] ?? 1),
            'generated_at' => (int) ($data['generated_at'] ?? 0),
            'retry_after'  => (int) ($data['retry_after'] ?? 0),
            'source'       => (string) ($data['source'] ?? 'cache'),
            'items'        => $items,
        ];
    }
}

if (!function_exists('kata_kata_needs_refresh')) {
    function kata_kata_needs_refresh(?array $data = null): bool
    {
        $data = $data ?? kata_kata_load();
        if (count($data['items']) < 8) {
            return true;
        }
        if ((int) $data['retry_after'] > time()) {
            return false;
        }
        return (time() - (int) $data['generated_at']) >= kata_kata_ttl();
    }
}

if (!function_exists('kata_kata_pick')) {
    function kata_kata_pick(array $items, int $count): array
    {
        $count = max(1, $count);
        if (count($items) <= $count) {
            return array_values($items);
        }
        $keys = array_rand($items, $count);
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        $out = [];
        foreach ($keys as $k) {
            $out[] = $items[$k];
        }
        shuffle($out);
        return $out;
    }
}

if (!function_exists('kata_kata_snapshot')) {
    /**
     * Data untuk halaman: item cache + penanda apakah perlu refresh.
     */
    function kata_kata_snapshot(int $count = 8): array
    {
        $data = kata_kata_load();
        return [
            'items'         => kata_kata_pick($data['items'], $count),
            'all'           => $data['items'],
            'needs_refresh' => kata_kata_needs_refresh($data),
            'generated_at'  => $data['generated_at'],
            'source'        => $data['source'],
        ];
    }
}

if (!function_exists('kata_kata_generate')) {
    /**
     * Minta AI membuat pertanyaan baru.
     * @return array{ok:bool,items?:array,model?:string,error?:string}
     */
    function kata_kata_generate(int $count = 12): array
    {
        if (openrouter_api_key() === '') {
            return ['ok' => false, 'error' => 'Kunci OpenRouter belum diatur.'];
        }

        $system = 'Kamu adalah fasilitator refleksi untuk mentee dewasa muda di Indonesia.'
            . ' Tugasmu membuat pertanyaan pemicu refleksi yang singkat, hangat, tidak menghakimi,'
            . ' dan bisa dijawab dalam 2-4 kalimat. Hindari pertanyaan medis/diagnosis.'
            . ' Balas HANYA dengan JSON: {"items":["pertanyaan 1","pertanyaan 2"]}.';

        $user = 'Buat ' . max(4, min(24, $count)) . ' pertanyaan refleksi baru dalam Bahasa Indonesia.'
            . ' Setiap pertanyaan maksimal 16 kata, diakhiri tanda tanya, tidak boleh duplikat,'
            . ' dan mencakup area: pembelajaran, emosi, relasi, energi, syukur, serta langkah kecil.';

        $r = openrouter_generate_json($system, $user, null, 4);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error']];
        }

        $data = $r['data'];
        $rawItems = [];
        if (isset($data['items']) && is_array($data['items'])) {
            $rawItems = $data['items'];
        } elseif (array_keys($data) === range(0, count($data) - 1)) {
            $rawItems = $data;
        }

        $items = [];
        foreach ($rawItems as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim(preg_replace('/\s+/', ' ', $item));
            if ($item === '' || strlen($item) > 180) {
                continue;
            }
            if (!str_ends_with($item, '?')) {
                $item = rtrim($item, '.! ') . '?';
            }
            if (!in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        if (count($items) < 4) {
            return ['ok' => false, 'error' => 'AI tidak menghasilkan cukup pertanyaan valid.'];
        }

        return ['ok' => true, 'items' => $items, 'model' => $r['model'] ?? ''];
    }
}

if (!function_exists('kata_kata_refresh')) {
    /**
     * Refresh cache. Memakai lock agar tidak dobel saat beberapa request masuk.
     * @return array{ok:bool,added?:int,total?:int,error?:string}
     */
    function kata_kata_refresh(bool $force = false): array
    {
        $lockFile = app_cache_dir() . DIRECTORY_SEPARATOR . 'kata-kata.lock';
        $lock = @fopen($lockFile, 'c');
        if (!$lock) {
            return ['ok' => false, 'error' => 'Tidak bisa membuat lock file.'];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['ok' => false, 'error' => 'Refresh lain sedang berjalan.'];
        }

        try {
            $data = kata_kata_load();
            if (!$force && !kata_kata_needs_refresh($data)) {
                return ['ok' => true, 'added' => 0, 'total' => count($data['items']), 'skipped' => true];
            }

            $gen = kata_kata_generate(12);
            if (!$gen['ok']) {
                $data['retry_after'] = time() + 24 * 3600;
                $data['last_error'] = $gen['error'];
                app_json_write(kata_kata_file(), $data);
                return ['ok' => false, 'error' => $gen['error']];
            }

            $items = $data['items'];
            $added = 0;
            foreach ($gen['items'] as $item) {
                if (!in_array($item, $items, true)) {
                    $items[] = $item;
                    $added++;
                }
            }
            // Jaga agar file tidak membengkak; simpan maksimal 80 item terbaru.
            if (count($items) > 80) {
                $items = array_slice($items, -80);
            }

            $data['items'] = array_values($items);
            $data['generated_at'] = time();
            $data['retry_after'] = 0;
            $data['source'] = 'ai';
            $data['last_model'] = $gen['model'] ?? '';
            unset($data['last_error']);
            app_json_write(kata_kata_file(), $data);

            return ['ok' => true, 'added' => $added, 'total' => count($items)];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
