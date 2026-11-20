<?php
// includes/whatsapp.php - WhatsApp Notification Helper (Fonnte, Wablas, and Direct wa.me)

function format_wa_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) === '62') {
        // already 62
    } elseif (substr($phone, 0, 1) === '8') {
        $phone = '62' . $phone;
    }
    return $phone;
}

function build_wa_message($template, $data) {
    $replacements = [
        '{nama}' => $data['customer_name'] ?? 'Pelanggan',
        '{no_nota}' => $data['order_code'] ?? '',
        '{total}' => 'Rp ' . number_format($data['total_amount'] ?? 0, 0, ',', '.'),
        '{link_tracking}' => $data['link_tracking'] ?? '',
        '{status}' => $data['status_label'] ?? '',
        '{laundry_name}' => $data['laundry_name'] ?? 'Clean&Fresh Laundry'
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

function get_wa_direct_link($phone, $message) {
    $formatted_phone = format_wa_phone($phone);
    return "https://wa.me/" . $formatted_phone . "?text=" . rawurlencode($message);
}

function send_wa_notification($conn, $phone, $message) {
    $set = get_settings($conn);
    $provider = $set['wa_gateway_provider'] ?? 'manual';
    $apiKey = $set['wa_api_key'] ?? '';
    $formatted_phone = format_wa_phone($phone);

    if (empty($apiKey) || $provider === 'manual') {
        return [
            'success' => false,
            'message' => 'Mode Manual / API Key belum diatur',
            'direct_url' => get_wa_direct_link($phone, $message)
        ];
    }

    if ($provider === 'fonnte') {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.fonnte.com/send_message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'target' => $formatted_phone,
                'message' => $message,
                'countryCode' => '62',
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $apiKey
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return [
                'success' => false,
                'message' => 'Curl Error: ' . $err,
                'direct_url' => get_wa_direct_link($phone, $message)
            ];
        }

        $resData = json_decode($response, true);
        $status = ($resData['status'] ?? false) === true;
        return [
            'success' => $status,
            'response' => $resData,
            'direct_url' => get_wa_direct_link($phone, $message)
        ];
    } elseif ($provider === 'wablas') {
        $domain = !empty($set['wa_domain']) ? rtrim($set['wa_domain'], '/') : 'https://phone.wablas.com';
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $domain . '/api/send-message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query([
                'phone' => $formatted_phone,
                'message' => $message,
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $apiKey
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return [
                'success' => false,
                'message' => 'Curl Error: ' . $err,
                'direct_url' => get_wa_direct_link($phone, $message)
            ];
        }

        $resData = json_decode($response, true);
        $status = ($resData['status'] ?? false) === true || ($resData['status'] ?? '') === 'success';
        return [
            'success' => $status,
            'response' => $resData,
            'direct_url' => get_wa_direct_link($phone, $message)
        ];
    }

    return [
        'success' => false,
        'message' => 'Provider tidak didukung',
        'direct_url' => get_wa_direct_link($phone, $message)
    ];
}
?>
