<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/supabase_client.php';
require_once __DIR__ . '/../Components/notification_view.php';

if (!function_exists('roleNotificationRest')) {
    function roleNotificationRest(string $endpoint, string $method = 'GET', ?array $body = null): array
    {
        if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
            return ['success' => false, 'data' => null, 'error' => 'Supabase configuration or PHP cURL is unavailable.'];
        }

        $headers = [
            'apikey: ' . SUPABASE_SECRET_KEY,
            'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init(rtrim((string) SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            return ['success' => false, 'data' => null, 'error' => $curlError ?: 'Could not connect to Supabase.'];
        }
        $decoded = json_decode($raw, true);
        $success = $status >= 200 && $status < 300;
        $error = $success ? null : (is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['details'] ?? $raw)
            : $raw);

        return ['success' => $success, 'data' => $decoded, 'error' => $error];
    }
}

if (!function_exists('roleNotificationLoad')) {
    function roleNotificationLoad(string $role): array
    {
        $role = mtNormalizeRole($role);
        if ($role === '' || mtRoleContext() !== $role || mtNormalizeRole($_SESSION['role'] ?? '') !== $role) {
            http_response_code(403);
            return ['items' => [], 'error' => 'You are not authorized to view these notifications.'];
        }

        $userId = trim((string) ($_SESSION['user_id'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId)) {
            $username = trim((string) ($_SESSION['username'] ?? $_SESSION['user'] ?? ''));
            if ($username === '') {
                http_response_code(401);
                return ['items' => [], 'error' => 'Your session has expired. Please sign in again.'];
            }
            if ($role === 'regular' && $username === 'user') $username = 'psantos';
            $lookup = roleNotificationRest(
                'users?username=eq.' . rawurlencode($username) . '&select=user_id,role,status&limit=1'
            );
            $user = is_array($lookup['data'] ?? null) ? ($lookup['data'][0] ?? null) : null;
            if (!$lookup['success'] || !$user) {
                http_response_code(401);
                return ['items' => [], 'error' => 'Unable to identify your account. Please sign in again.'];
            }
            $userId = (string) ($user['user_id'] ?? '');
            $_SESSION['user_id'] = $userId;
        }

        $userResult = roleNotificationRest(
            'users?user_id=eq.' . rawurlencode($userId) . '&select=user_id,role,status&limit=1'
        );
        $user = is_array($userResult['data'] ?? null) ? ($userResult['data'][0] ?? null) : null;
        if (!$userResult['success'] || !$user
            || strtolower((string) ($user['role'] ?? '')) !== $role
            || strtolower((string) ($user['status'] ?? '')) !== 'active') {
            http_response_code(403);
            return ['items' => [], 'error' => 'Your account is not authorized to view these notifications.'];
        }

        if (in_array($role, ['regular', 'maintenance'], true)) {
            $overdueSync = roleNotificationRest(
                'rpc/sync_overdue_borrow_notifications',
                'POST',
                ['p_borrower_user_id' => $userId]
            );
            if (!$overdueSync['success']) {
                return ['items' => [], 'error' => 'Unable to check overdue borrow notices. '
                    . (string) ($overdueSync['error'] ?? '')];
            }
        }

        $result = roleNotificationRest(
            'notifications?recipient_user_id=eq.' . rawurlencode($userId)
            . '&select=notification_id,title,message,related_request_id,is_read,created_at'
            . '&order=created_at.desc&limit=500'
        );
        if (!$result['success'] || !is_array($result['data'])) {
            return ['items' => [], 'error' => 'Unable to load your notifications. ' . (string) ($result['error'] ?? '')];
        }

        $manila = new DateTimeZone('Asia/Manila');
        $items = [];
        foreach ($result['data'] as $row) {
            if (!is_array($row) || empty($row['notification_id'])) continue;
            $createdAt = (string) ($row['created_at'] ?? '');
            try {
                $time = $createdAt !== ''
                    ? (new DateTime($createdAt))->setTimezone($manila)->format('M j, Y · g:i A')
                    : 'Time unavailable';
            } catch (Throwable $exception) {
                $time = 'Time unavailable';
            }

            $title = trim((string) ($row['title'] ?? 'Notification'));
            $text = trim((string) ($row['message'] ?? ''));
            $lowerTitle = strtolower($title);
            $tone = (str_contains($lowerTitle, 'reject') || str_contains($lowerTitle, 'overdue'))
                ? 'red'
                : ((str_contains($lowerTitle, 'complete') || str_contains($lowerTitle, 'return'))
                    ? 'orange'
                    : ((str_contains($lowerTitle, 'approve') || str_contains($lowerTitle, 'request'))
                        ? 'green'
                        : 'blue'));
            $category = str_contains($lowerTitle, 'borrow') || str_contains($lowerTitle, 'item')
                ? 'borrow'
                : (str_contains($lowerTitle, 'assign') ? 'assignment'
                    : (str_contains($lowerTitle, 'stock') || str_contains($lowerTitle, 'inventory') ? 'inventory'
                        : (str_contains($lowerTitle, 'account') ? 'account' : 'status')));

            $items[] = [
                'id' => (string) $row['notification_id'],
                'tone' => $tone,
                'title' => $title,
                'text' => $text,
                'time' => $time,
                'unread' => ($row['is_read'] ?? false) !== true,
                'category' => $category,
                'related_request_id' => (string) ($row['related_request_id'] ?? ''),
            ];
        }

        if ($role === 'admin') {
            $inventoryResult = roleNotificationRest(
                'inventory_items?select=item_id,name,current_qty,reorder_level&limit=1000'
            );
            if (!$inventoryResult['success'] || !is_array($inventoryResult['data'])) {
                return ['items' => [], 'error' => 'Unable to load stock alerts. '
                    . (string) ($inventoryResult['error'] ?? '')];
            }
            foreach ($inventoryResult['data'] as $item) {
                if (!is_array($item) || !isset($item['current_qty'], $item['reorder_level'])) continue;
                $quantity = (int) $item['current_qty'];
                $reorder = (int) $item['reorder_level'];
                if ($quantity > $reorder) continue;
                $title = $quantity === 0 ? 'Out of Stock' : 'Low Stock';
                $message = (string) ($item['name'] ?? 'An inventory item') . ' has '
                    . $quantity . ' available; reorder level is ' . $reorder . '.';
                $alreadyNotified = false;
                foreach ($items as $existingItem) {
                    if (($existingItem['title'] ?? '') === $title
                        && ($existingItem['text'] ?? '') === $message) {
                        $alreadyNotified = true;
                        break;
                    }
                }
                if ($alreadyNotified) continue;
                $items[] = [
                    'id' => notificationItemId('stock', (string) ($item['item_id'] ?? ''), $title),
                    'tone' => $quantity === 0 ? 'red' : 'orange',
                    'title' => $title,
                    'text' => $message,
                    'time' => 'Current Storage Unit alert',
                    'unread' => true,
                    'category' => 'inventory',
                    'related_request_id' => '',
                ];
            }
        }

        return ['items' => $items, 'error' => null];
    }
}

if (!function_exists('renderRoleNotificationPage')) {
    function renderRoleNotificationPage(
        string $role,
        ?string $subtitle = null,
        array $filters = [],
        array $extraItems = [],
        string $extraError = ''
    ): void {
        $loaded = roleNotificationLoad($role);
        $pageError = (string) ($loaded['error'] ?? '') ?: $extraError;
        $items = $loaded['items'];
        if (empty($loaded['error'])) {
            foreach ($extraItems as $extra) {
                $duplicate = false;
                foreach ($items as $item) {
                    if (($item['title'] ?? '') === ($extra['title'] ?? '')
                        && ($item['text'] ?? '') === ($extra['text'] ?? '')
                        && ($item['related_request_id'] ?? '') === ($extra['related_request_id'] ?? '')) {
                        $duplicate = true;
                        break;
                    }
                }
                if (!$duplicate) $items[] = $extra;
            }
        } else {
            $items = [];
        }

        if (isset($_GET['feed'])) {
            if ($pageError !== '') {
                if (http_response_code() < 400) http_response_code(502);
                header('Content-Type: application/json');
                header('Cache-Control: no-store');
                echo json_encode(['error' => $pageError]);
                exit;
            }
            notificationFeedRespond($items);
        }

        renderHead('Notifications', 'assets/css/NotificationShared.css', $role);
        if ($pageError !== '') {
            echo '<div class="tr-alert error" role="alert">' . e($pageError) . '</div>';
        }
        renderNotificationPage($role, $subtitle, $items, $filters);
        renderFoot();
    }
}
