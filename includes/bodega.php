<?php
/* ------------------------------------------------------------
   BODEGA (WAREHOUSE) DATA LAYER - Treasurer role
   ------------------------------------------------------------
   Talks to Supabase REST (inventory_items, inventory_transactions,
   procurements, facility_rooms, users) and Supabase Storage
   (BODEGA_BUCKET: outsider letters and purchase receipts).

   Ground floor only: the bodega is the facility_rooms record whose
   name contains "bodega" (first match). Every item carries a room_id,
   so a second bodega later only needs its own room record.

   Stock rules:
   - consumable  -> released use is final (stock goes down, not returned)
   - returnable  -> released borrow goes down, return brings it back up
   - procurement -> stock goes up and a stock_in row is logged
   ------------------------------------------------------------ */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../Config/supabase.php';

if (!defined('BODEGA_BUCKET')) {
    define('BODEGA_BUCKET', 'bodega-documents');
}
if (!defined('BODEGA_MAX_UPLOAD')) {
    define('BODEGA_MAX_UPLOAD', 5 * 1024 * 1024);
}

/* Last error from a list call, shown by the page as an alert. */
if (!function_exists('bodegaSetError')) {
    function bodegaSetError(?string $message = null): ?string
    {
        static $last = null;
        if ($message !== null) {
            $last = $message;
        }
        return $last;
    }
}

if (!function_exists('bodegaHttp')) {
    function bodegaHttp(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (!defined('SUPABASE_URL') || !defined('SUPABASE_SECRET_KEY') || !function_exists('curl_init')) {
            return ['success' => false, 'status' => 0, 'data' => [], 'error' => 'Supabase configuration or PHP cURL is unavailable.'];
        }
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);
        $raw    = curl_exec($ch);
        $error  = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'status' => $status, 'data' => [], 'error' => $error ?: 'Could not connect to Supabase.'];
        }
        $decoded = json_decode((string) $raw, true);
        $ok = $status >= 200 && $status < 300;
        $message = null;
        if (!$ok) {
            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['msg'] ?? $decoded['error'] ?? $raw)
                : (string) $raw;
        }
        return ['success' => $ok, 'status' => $status, 'data' => is_array($decoded) ? $decoded : [], 'error' => $message];
    }
}

/* PostgREST call: $endpoint is e.g. "inventory_items?select=*". */
if (!function_exists('bodegaRest')) {
    function bodegaRest(string $method, string $endpoint, ?array $body = null, string $prefer = 'return=representation'): array
    {
        $key = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
        $base = defined('SUPABASE_URL') ? rtrim((string) SUPABASE_URL, '/') : '';
        $headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
            'Prefer: ' . $prefer,
        ];
        $payload = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return bodegaHttp($method, $base . '/rest/v1/' . ltrim($endpoint, '/'), $headers, $payload);
    }
}

/* Supabase Storage call: $path is e.g. "object/bucket/file.pdf". */
if (!function_exists('bodegaStorage')) {
    function bodegaStorage(string $method, string $path, ?string $body = null, array $extraHeaders = []): array
    {
        $key = defined('SUPABASE_SECRET_KEY') ? (string) SUPABASE_SECRET_KEY : '';
        $base = defined('SUPABASE_URL') ? rtrim((string) SUPABASE_URL, '/') : '';
        $headers = array_merge([
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ], $extraHeaders);
        return bodegaHttp($method, $base . '/storage/v1/' . ltrim($path, '/'), $headers, $body);
    }
}

/* Rows of a list query, or [] with the error stored for the page. */
if (!function_exists('bodegaList')) {
    function bodegaList(string $endpoint): array
    {
        $result = bodegaRest('GET', $endpoint);
        if (!$result['success']) {
            bodegaSetError('Unable to load bodega data. ' . (string) $result['error']);
            return [];
        }
        return $result['data'];
    }
}

if (!function_exists('bodegaRoomId')) {
    function bodegaRoomId(): string
    {
        static $roomId = null;
        if ($roomId !== null) {
            return $roomId;
        }
        $rows = bodegaList('facility_rooms?select=room_id,name&name=ilike.*bodega*&order=name.asc&limit=1');
        $roomId = (string) ($rows[0]['room_id'] ?? '');
        return $roomId;
    }
}

if (!function_exists('bodegaItems')) {
    function bodegaItems(): array
    {
        $room = bodegaRoomId();
        if ($room === '') {
            bodegaSetError('The ground floor bodega is not set up yet. Add a facility room whose name contains "Bodega".');
            return [];
        }
        return bodegaList('inventory_items?select=*&room_id=eq.' . rawurlencode($room) . '&order=name.asc');
    }
}

/* Transactions for the bodega's items, newest first. */
if (!function_exists('bodegaTransactions')) {
    function bodegaTransactions(array $itemMap, int $limit = 300): array
    {
        if (!$itemMap) {
            return [];
        }
        $rows = bodegaList('inventory_transactions?select=*&order=created_at.desc&limit=' . $limit);
        return array_values(array_filter($rows, fn($t) => isset($itemMap[(string) ($t['item_id'] ?? '')])));
    }
}

if (!function_exists('bodegaProcurements')) {
    function bodegaProcurements(array $itemMap, int $limit = 300): array
    {
        if (!$itemMap) {
            return [];
        }
        $rows = bodegaList('procurements?select=*&order=purchase_date.desc,created_at.desc&limit=' . $limit);
        return array_values(array_filter($rows, fn($p) => isset($itemMap[(string) ($p['item_id'] ?? '')])));
    }
}

/* Full names keyed by user_id. Only well-formed UUIDs are queried. */
if (!function_exists('bodegaUserNames')) {
    function bodegaUserNames(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if (preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
                $clean[$id] = true;
            }
        }
        if (!$clean) {
            return [];
        }
        $rows = bodegaList('users?select=user_id,full_name&user_id=in.(' . implode(',', array_keys($clean)) . ')');
        $names = [];
        foreach ($rows as $row) {
            $names[(string) $row['user_id']] = (string) ($row['full_name'] ?? '');
        }
        return $names;
    }
}

/* Active maintenance workers, for the staff borrower picker. */
if (!function_exists('bodegaStaffList')) {
    function bodegaStaffList(): array
    {
        return bodegaList('users?select=user_id,full_name&role=ilike.maintenance&status=ilike.active&order=full_name.asc');
    }
}

if (!function_exists('bodegaItemMap')) {
    function bodegaItemMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $map[(string) $item['item_id']] = $item;
        }
        return $map;
    }
}

if (!function_exists('bodegaActorId')) {
    /* The signed-in treasurer's user_id, or null if it is not a UUID. */
    function bodegaActorId(): ?string
    {
        $id = (string) ($_SESSION['user_id'] ?? '');
        return preg_match('/^[0-9a-fA-F-]{36}$/', $id) ? $id : null;
    }
}

if (!function_exists('bodegaDefaultType')) {
    /* Default item type by category. The treasurer can change it per item. */
    function bodegaDefaultType(string $category): string
    {
        return stripos($category, 'electrical') !== false ? 'consumable' : 'returnable';
    }
}

if (!function_exists('bodegaIsLow')) {
    function bodegaIsLow(array $item): bool
    {
        return (int) ($item['current_qty'] ?? 0) <= (int) ($item['reorder_level'] ?? 0);
    }
}

if (!function_exists('bodegaIsOverdue')) {
    /* A released borrow of a returnable item whose due date has passed. */
    function bodegaIsOverdue(array $txn, array $item): bool
    {
        if (($txn['txn_type'] ?? '') !== 'borrow' || ($txn['status'] ?? '') !== 'released') {
            return false;
        }
        if (($item['item_type'] ?? '') !== 'returnable' || empty($txn['due_date'])) {
            return false;
        }
        return (string) $txn['due_date'] < date('Y-m-d');
    }
}

if (!function_exists('bodegaPeso')) {
    function bodegaPeso($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}

if (!function_exists('bodegaDate')) {
    function bodegaDate(?string $value): string
    {
        if (!$value) {
            return '';
        }
        try {
            return (new DateTime($value, new DateTimeZone('Asia/Manila')))->format('M j, Y');
        } catch (Throwable $t) {
            return '';
        }
    }
}

/* Changes stock by $delta. Returns an error message, or null on success. */
if (!function_exists('bodegaAdjustStock')) {
    function bodegaAdjustStock(string $itemId, int $delta): ?string
    {
        $result = bodegaRest('GET', 'inventory_items?select=item_id,current_qty&item_id=eq.' . rawurlencode($itemId) . '&limit=1');
        if (!$result['success'] || empty($result['data'][0])) {
            return 'Item not found.';
        }
        $current = (int) $result['data'][0]['current_qty'];
        $next = $current + $delta;
        if ($next < 0) {
            return 'Not enough stock. Only ' . $current . ' left.';
        }
        $patch = bodegaRest('PATCH', 'inventory_items?item_id=eq.' . rawurlencode($itemId), ['current_qty' => $next], 'return=minimal');
        return $patch['success'] ? null : (string) $patch['error'];
    }
}

if (!function_exists('bodegaInsert')) {
    function bodegaInsert(string $table, array $row): ?string
    {
        $result = bodegaRest('POST', $table, $row, 'return=minimal');
        return $result['success'] ? null : (string) $result['error'];
    }
}

if (!function_exists('bodegaUpdate')) {
    function bodegaUpdate(string $table, string $filter, array $fields): ?string
    {
        $result = bodegaRest('PATCH', $table . '?' . $filter, $fields, 'return=minimal');
        return $result['success'] ? null : (string) $result['error'];
    }
}

/* Validates an optional or required upload. Returns ['ok','error','tmp','mime','ext'] */
if (!function_exists('bodegaCheckFile')) {
    function bodegaCheckFile(array $file, bool $required): array
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) {
            return $required
                ? ['ok' => false, 'error' => 'A scanned copy or photo of the letter is required.']
                : ['ok' => true, 'tmp' => '', 'mime' => '', 'ext' => ''];
        }
        if ($code !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'The file could not be uploaded. Please try again.'];
        }
        if ((int) ($file['size'] ?? 0) > BODEGA_MAX_UPLOAD) {
            return ['ok' => false, 'error' => 'The file is larger than 5 MB.'];
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? '';
        if ($ext === '') {
            return ['ok' => false, 'error' => 'Only JPG, PNG or PDF files are allowed.'];
        }
        return ['ok' => true, 'tmp' => (string) $file['tmp_name'], 'mime' => $mime, 'ext' => $ext];
    }
}

/* Uploads a checked file into BODEGA_BUCKET. Returns [objectPath or null, error or null]. */
if (!function_exists('bodegaUpload')) {
    function bodegaUpload(string $folder, array $checked): array
    {
        if ($checked['tmp'] === '') {
            return [null, null];
        }
        $bytes = @file_get_contents($checked['tmp']);
        if ($bytes === false) {
            return [null, 'Could not read the uploaded file.'];
        }
        $objectPath = $folder . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $checked['ext'];
        $result = bodegaStorage('POST', 'object/' . BODEGA_BUCKET . '/' . $objectPath, $bytes, [
            'Content-Type: ' . $checked['mime'],
            'x-upsert: false',
        ]);
        return $result['success'] ? [$objectPath, null] : [null, 'The file could not be saved: ' . $result['error']];
    }
}

/* Short-lived link to a stored file, or '' if it cannot be made. */
if (!function_exists('bodegaSignedUrl')) {
    function bodegaSignedUrl(string $objectPath, int $seconds = 300): string
    {
        $result = bodegaStorage('POST', 'object/sign/' . BODEGA_BUCKET . '/' . $objectPath, json_encode(['expiresIn' => $seconds]), [
            'Content-Type: application/json',
        ]);
        if (!$result['success']) {
            return '';
        }
        $signed = (string) ($result['data']['signedURL'] ?? $result['data']['signedUrl'] ?? '');
        if ($signed === '') {
            return '';
        }
        return rtrim((string) SUPABASE_URL, '/') . '/storage/v1' . $signed;
    }
}

if (!function_exists('bodegaFlash')) {
    function bodegaFlash(string $type, string $text): void
    {
        $_SESSION['bodega_flash'] = ['type' => $type, 'text' => $text];
    }
}

if (!function_exists('bodegaTakeFlash')) {
    function bodegaTakeFlash(): ?array
    {
        $flash = $_SESSION['bodega_flash'] ?? null;
        unset($_SESSION['bodega_flash']);
        return is_array($flash) ? $flash : null;
    }
}

/* Post/Redirect/Get back to the same page (keeps the query string if given). */
if (!function_exists('bodegaRedirectHere')) {
    function bodegaRedirectHere(string $query = ''): never
    {
        $page = basename(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? 'treasurer_dashboard.php'));
        header('Location: ' . $page . ($query !== '' ? '?' . $query : ''));
        exit;
    }
}

if (!function_exists('bodegaRedirectUrl')) {
    function bodegaRedirectUrl(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!defined('BODEGA_CATEGORIES')) {
    define('BODEGA_CATEGORIES', ['Tools', 'Furniture', 'Electrical parts', 'Others']);
}
