<?php
// File: clock_helper.php
// Membaca waktu server PHP dan server MySQL untuk kartu "Waktu Sekarang".
// Tujuannya membandingkan jam PHP, MySQL, dan pengguna (selisih dalam detik).

if (!function_exists('baca_waktu_server')) {
    /**
     * @param mysqli|null $conn Koneksi database dari env.php.
     * @return array Data waktu PHP & MySQL (epoch + label zona waktu).
     */
    function baca_waktu_server($conn = null)
    {
        $epoch = microtime(true);

        $data = [
            'php' => [
                'epoch'    => $epoch,
                'epoch_ms' => (int) round($epoch * 1000),
                'time'         => date('H:i:s', (int) $epoch),
                'iso'          => date('c', (int) $epoch),
                'tz'           => date_default_timezone_get(),
                'offset'       => (int) date('Z'),
                'offset_label' => date('P'),
            ],
            'mysql'         => null,
            'mysql_selisih' => null,
        ];

        if ($conn instanceof mysqli) {
            $res = @$conn->query(
                "SELECT NOW() AS now,
                        @@session.time_zone AS tz,
                        UNIX_TIMESTAMP() AS epoch,
                        TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS utc_offset"
            );

            if ($res) {
                $row    = $res->fetch_assoc();
                $offset = (int) $row['utc_offset'];

                $data['mysql'] = [
                    'epoch'      => (int) $row['epoch'],
                    'now'        => $row['now'],
                    'time'       => substr($row['now'], 11, 8),
                    'tz'         => $row['tz'],
                    'utc_offset' => $offset,
                    'tz_label'   => sprintf(
                        'UTC%s%02d:%02d',
                        $offset < 0 ? '-' : '+',
                        intdiv(abs($offset), 3600),
                        intdiv(abs($offset) % 3600, 60)
                    ),
                ];

                // Selisih jam MySQL terhadap PHP (detik).
                $data['mysql_selisih'] = $data['mysql']['epoch'] - (int) floor($epoch);
            }
        }

        return $data;
    }
}

if (!function_exists('format_selisih')) {
    /** Format selisih detik menjadi teks, mis. "+2 dtk" atau "-1.5 mnt". */
    function format_selisih($detik)
    {
        if ($detik === null) {
            return '—';
        }

        $d     = (int) round($detik);
        $abs   = abs($d);
        $sign  = $d > 0 ? '+' : ($d < 0 ? '-' : '');

        if ($abs < 60) {
            return ($d === 0 ? '0' : $sign . $abs) . ' dtk';
        }

        if ($abs < 3600) {
            return $sign . rtrim(rtrim(number_format($abs / 60, 1, '.', ''), '0'), '.') . ' mnt';
        }

        return $sign . rtrim(rtrim(number_format($abs / 3600, 1, '.', ''), '0'), '.') . ' jam';
    }
}

if (!function_exists('kelas_selisih')) {
    /** Kelas warna badge berdasarkan besar selisih (detik). */
    function kelas_selisih($detik)
    {
        if ($detik === null) {
            return '';
        }

        $abs = abs((int) round($detik));
        if ($abs <= 2) {
            return 'ok';
        }
        if ($abs <= 10) {
            return 'warn';
        }

        return 'bad';
    }
}
