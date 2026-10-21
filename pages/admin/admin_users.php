<?php

declare(strict_types=1);



require_once __DIR__ . '/../../includes/session.php';



require_once __DIR__ . '/../../Components/layout.php';

require_once __DIR__ . '/../../Config/supabase.php';



/*

 * MaintainTrack - User & Role Management

 * Features: List, Add, Edit, Email Update, Delete

 * Keep SUPABASE_SECRET_KEY on the PHP server only.

 */



function usersJsonResponse(array $payload, int $status = 200): never

{

    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;

}



function usersSupabaseRequest(

    string $endpoint,

    string $method = 'GET',

    ?array $data = null

): array {

    $secret = defined('SUPABASE_SECRET_KEY')

        ? (string) SUPABASE_SECRET_KEY

        : (string) (getenv('SUPABASE_SECRET_KEY') ?: '');



    if ($secret === '') {

        return [

            'success' => false,

            'status' => 0,

            'data' => null,

            'error' => 'SUPABASE_SECRET_KEY is not configured.'

        ];

    }



    if (!function_exists('curl_init')) {

        return [

            'success' => false,

            'status' => 0,

            'data' => null,

            'error' => 'PHP cURL extension is not enabled.'

        ];

    }



    if (

        !str_starts_with($endpoint, '/') ||

        str_starts_with($endpoint, '//') ||

        str_contains($endpoint, "\r") ||

        str_contains($endpoint, "\n")

    ) {

        return [

            'success' => false,

            'status' => 0,

            'data' => null,

            'error' => 'Invalid API endpoint.'

        ];

    }



    $headers = [

        'apikey: ' . $secret,

        'Authorization: Bearer ' . $secret,

        'Accept: application/json',

        'Content-Type: application/json',

        'Prefer: return=representation'

    ];



    $options = [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CUSTOMREQUEST => strtoupper($method),

        CURLOPT_HTTPHEADER => $headers,

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 30

    ];



    if ($data !== null) {

        $json = json_encode(

            $data,

            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE

        );



        if ($json === false) {

            return [

                'success' => false,

                'status' => 0,

                'data' => null,

                'error' => 'Could not encode request data.'

            ];

        }



        $options[CURLOPT_POSTFIELDS] = $json;

    }



    $ch = curl_init(rtrim(SUPABASE_URL, '/') . $endpoint);



    if ($ch === false) {

        return [

            'success' => false,

            'status' => 0,

            'data' => null,

            'error' => 'Unable to initialize cURL.'

        ];

    }



    curl_setopt_array($ch, $options);



    $raw = curl_exec($ch);

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);



    curl_close($ch);



    if ($raw === false) {

        return [

            'success' => false,

            'status' => $status,

            'data' => null,

            'error' => $curlError ?: 'Could not connect to Supabase.'

        ];

    }



    $decoded = json_decode($raw, true);

    $success = $status >= 200 && $status < 300;

    $error = null;



    if (!$success) {

        $error = is_array($decoded)

            ? (

                $decoded['message'] ??

                $decoded['error_description'] ??

                $decoded['error'] ??

                $decoded['hint'] ??

                $raw

            )

            : ($raw ?: 'Supabase returned an HTTP error.');

    }



    return [

        'success' => $success,

        'status' => $status,

        'data' => $decoded ?? $raw,

        'error' => is_string($error) ? $error : null

    ];

}



function usersIsAdmin(): bool

{

    return isset($_SESSION['role'])

        && strtolower((string) $_SESSION['role']) === 'admin';

}



function userRoleLabel(string $role): string

{

    return match ($role) {

        'admin' => 'Admin',

        'maintenance' => 'Maintenance Staff',

        default => 'Regular User'

    };

}



function userLastActiveLabel(?string $date): string

{

    if (!$date) {

        return '—';

    }



    $timestamp = strtotime($date);



    return $timestamp ? date('M j, Y', $timestamp) : '—';

}



/* =========================================================

   API HANDLERS

   ========================================================= */



if (isset($_GET['users_api']) || isset($_POST['users_api'])) {

    if (!usersIsAdmin()) {

        usersJsonResponse([

            'success' => false,

            'message' => 'Unauthorized. Please sign in using an Admin account.'

        ], 403);

    }



    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? 'list');



    /* LIST USERS */

    if ($action === 'list') {

        $result = usersSupabaseRequest(

            '/rest/v1/users?select=user_id,display_id,full_name,email,role,status,specialization_category,specialization_type,last_active,created_at,updated_at&order=created_at.desc'

        );



        if (!$result['success']) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Could not load users: ' . $result['error']

            ], 502);

        }



        usersJsonResponse([

            'success' => true,

            'users' => is_array($result['data']) ? $result['data'] : []

        ]);

    }



    /* UPDATE USER */

    if ($action === 'update') {

        $userId = trim((string) ($_POST['user_id'] ?? ''));

        $name = trim((string) ($_POST['full_name'] ?? ''));

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        $role = trim((string) ($_POST['role'] ?? ''));

        $status = trim((string) ($_POST['status'] ?? ''));
        $specializationCategory = trim((string) ($_POST['specialization_category'] ?? ''));
        $specializationType = trim((string) ($_POST['specialization_type'] ?? ''));



        if (!preg_match(

            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',

            $userId

        )) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Invalid user ID.'

            ], 422);

        }



        if ($name === '' || mb_strlen($name) > 100) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Enter a valid full name.'

            ], 422);

        }



        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Enter a valid email address.'

            ], 422);

        }



        if (!in_array($role, ['admin', 'regular', 'maintenance'], true)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Select a valid role.'

            ], 422);

        }



        if (!in_array($status, ['Active', 'Inactive'], true)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Select a valid status.'

            ], 422);

        }



        $allowedSpecializations = [
            'Electrical' => ['Computer Technician', 'Network Technician', 'Electrician', 'Aircon Technician'],
            'Non-Electrical' => ['Plumber', 'Pipefitter', 'Carpenter', 'Mason', 'Painter', 'General Maintenance Technician']
        ];
        if ($role === 'maintenance') {
            if (!isset($allowedSpecializations[$specializationCategory]) ||
                !in_array($specializationType, $allowedSpecializations[$specializationCategory], true)) {
                usersJsonResponse([
                    'success' => false,
                    'message' => 'Select a valid specialization category and technician type.'
                ], 422);
            }
        } else {
            $specializationCategory = '';
            $specializationType = '';
        }

        $currentResult = usersSupabaseRequest(

            '/rest/v1/users?user_id=eq.' . rawurlencode($userId) .

            '&select=user_id,email,auth_user_id&limit=1'

        );



        $current = is_array($currentResult['data'] ?? null)

            ? ($currentResult['data'][0] ?? null)

            : null;



        if (!$currentResult['success'] || !$current) {

            usersJsonResponse([

                'success' => false,

                'message' => 'User not found. Refresh the page and try again.'

            ], 404);

        }



        $oldEmail = strtolower(trim((string) ($current['email'] ?? '')));

        $authUserId = trim((string) ($current['auth_user_id'] ?? ''));

        $emailChanged = $email !== $oldEmail;



        if ($emailChanged && $authUserId === '') {

            usersJsonResponse([

                'success' => false,

                'message' => 'This profile is not linked to Supabase Auth yet. The email cannot be changed.'

            ], 409);

        }



        if ($emailChanged) {

            $authUpdate = usersSupabaseRequest(

                '/auth/v1/admin/users/' . rawurlencode($authUserId),

                'PUT',

                [

                    'email' => $email,

                    'email_confirm' => true

                ]

            );



            if (!$authUpdate['success']) {

                usersJsonResponse([

                    'success' => false,

                    'message' => 'Could not update Auth email: ' . $authUpdate['error']

                ], 502);

            }

        }



        $result = usersSupabaseRequest(

            '/rest/v1/users?user_id=eq.' . rawurlencode($userId) .

            '&select=user_id,display_id,full_name,email,role,status,specialization_category,specialization_type,last_active,created_at,updated_at',

            'PATCH',

            [

                'full_name' => $name,

                'email' => $email,

                'role' => $role,

                'status' => $status,

                'specialization_category' => $role === 'maintenance' ? $specializationCategory : null,

                'specialization_type' => $role === 'maintenance' ? $specializationType : null,

                'updated_at' => gmdate('c')

            ]

        );



        if (!$result['success']) {

            if ($emailChanged && $authUserId !== '' && $oldEmail !== '') {

                usersSupabaseRequest(

                    '/auth/v1/admin/users/' . rawurlencode($authUserId),

                    'PUT',

                    [

                        'email' => $oldEmail,

                        'email_confirm' => true

                    ]

                );

            }



            usersJsonResponse([

                'success' => false,

                'message' => 'Could not update user: ' . $result['error']

            ], 502);

        }



        $updated = is_array($result['data'])

            ? ($result['data'][0] ?? null)

            : null;



        if (!$updated) {

            usersJsonResponse([

                'success' => false,

                'message' => 'No user was updated.'

            ], 404);

        }



        usersJsonResponse([

            'success' => true,

            'message' => 'User updated successfully.',

            'user' => $updated

        ]);

    }



    /* DELETE USER */

    if ($action === 'delete') {

        $userId = trim((string) ($_POST['user_id'] ?? ''));



        if (!preg_match(

            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',

            $userId

        )) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Invalid user ID.'

            ], 422);

        }



        $sessionUserId = (string) ($_SESSION['user_id'] ?? '');



        if ($sessionUserId !== '' && hash_equals($sessionUserId, $userId)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'You cannot delete the account you are currently using.'

            ], 403);

        }



        $lookup = usersSupabaseRequest(

            '/rest/v1/users?user_id=eq.' . rawurlencode($userId) .

            '&select=user_id,full_name,email,auth_user_id&limit=1'

        );



        $target = is_array($lookup['data'] ?? null)

            ? ($lookup['data'][0] ?? null)

            : null;



        if (!$lookup['success'] || !$target) {

            usersJsonResponse([

                'success' => false,

                'message' => 'User not found. Refresh the page and try again.'

            ], 404);

        }



        $authUserId = trim((string) ($target['auth_user_id'] ?? ''));



        /* Delete the linked Supabase Auth account first. */

        if ($authUserId !== '') {

            $authDelete = usersSupabaseRequest(

                '/auth/v1/admin/users/' . rawurlencode($authUserId),

                'DELETE'

            );



            if (!$authDelete['success'] && $authDelete['status'] !== 404) {

                usersJsonResponse([

                    'success' => false,

                    'message' => 'Could not delete the Supabase Auth account: ' .

                        (string) $authDelete['error']

                ], 502);

            }

        }



        /* Delete the corresponding public.users profile. */

        $profileDelete = usersSupabaseRequest(

            '/rest/v1/users?user_id=eq.' . rawurlencode($userId),

            'DELETE'

        );



        if (!$profileDelete['success']) {

            usersJsonResponse([

                'success' => false,

                'message' => 'The Auth account may have been deleted, but the database profile could not be deleted: ' .

                    (string) $profileDelete['error']

            ], 502);

        }



        usersJsonResponse([

            'success' => true,

            'message' => 'User account deleted successfully.',

            'user_id' => $userId

        ]);

    }



    /* CREATE USER */

    if ($action === 'create') {

        $name = trim((string) ($_POST['full_name'] ?? ''));

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        $username = strtolower(trim((string) ($_POST['username'] ?? '')));

        $password = (string) ($_POST['password'] ?? '');

        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $role = trim((string) ($_POST['role'] ?? ''));

        $status = trim((string) ($_POST['status'] ?? 'Active'));
        $specializationCategory = trim((string) ($_POST['specialization_category'] ?? ''));
        $specializationType = trim((string) ($_POST['specialization_type'] ?? ''));



        if ($name === '' || mb_strlen($name) > 100) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Enter a valid full name.'

            ], 422);

        }



        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Enter a valid email address.'

            ], 422);

        }



        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Username must be 3–50 characters using lowercase letters, numbers, dots, underscores, or hyphens.'

            ], 422);

        }



        if (strlen($password) < 8) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Password must contain at least 8 characters.'

            ], 422);

        }



        if ($password !== $confirmPassword) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Passwords do not match.'

            ], 422);

        }



        if (!in_array($role, ['admin', 'regular', 'maintenance'], true)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Select a valid role.'

            ], 422);

        }



        if (!in_array($status, ['Active', 'Inactive'], true)) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Select a valid status.'

            ], 422);

        }



        $allowedSpecializations = [
            'Electrical' => ['Computer Technician', 'Network Technician', 'Electrician', 'Aircon Technician'],
            'Non-Electrical' => ['Plumber', 'Pipefitter', 'Carpenter', 'Mason', 'Painter', 'General Maintenance Technician']
        ];
        if ($role === 'maintenance') {
            if (!isset($allowedSpecializations[$specializationCategory]) ||
                !in_array($specializationType, $allowedSpecializations[$specializationCategory], true)) {
                usersJsonResponse([
                    'success' => false,
                    'message' => 'Select a valid specialization category and technician type.'
                ], 422);
            }
        } else {
            $specializationCategory = '';
            $specializationType = '';
        }

        $authResult = usersSupabaseRequest(

            '/auth/v1/admin/users',

            'POST',

            [

                'email' => $email,

                'password' => $password,

                'email_confirm' => true,

                'user_metadata' => [

                    'full_name' => $name,

                    'username' => $username

                ]

            ]

        );



        if (

            !$authResult['success'] ||

            !is_array($authResult['data']) ||

            empty($authResult['data']['id'])

        ) {

            usersJsonResponse([

                'success' => false,

                'message' => 'Supabase Auth could not create the account: ' .

                    (string) ($authResult['error'] ?? 'Unexpected Auth response.')

            ], 502);

        }



        $authUserId = (string) $authResult['data']['id'];

        $displayId = 'MT-' . date('Y') . '-' .

            strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));



        $profileResult = usersSupabaseRequest(

            '/rest/v1/users?select=user_id,display_id,full_name,email,role,status,specialization_category,specialization_type,last_active,created_at,updated_at',

            'POST',

            [

                'display_id' => $displayId,

                'full_name' => $name,

                'username' => $username,

                'email' => $email,

                'password_hash' => password_hash($password, PASSWORD_DEFAULT),

                'role' => $role,

                'status' => $status,

                'specialization_category' => $role === 'maintenance' ? $specializationCategory : null,

                'specialization_type' => $role === 'maintenance' ? $specializationType : null,

                'auth_user_id' => $authUserId,

                'updated_at' => gmdate('c')

            ]

        );



        if (

            !$profileResult['success'] ||

            !is_array($profileResult['data']) ||

            empty($profileResult['data'][0])

        ) {

            usersSupabaseRequest(

                '/auth/v1/admin/users/' . rawurlencode($authUserId),

                'DELETE'

            );



            usersJsonResponse([

                'success' => false,

                'message' => 'Could not save the profile in public.users. The Auth account creation was rolled back. Database response: ' .

                    (string) ($profileResult['error'] ?? 'No profile returned.')

            ], 502);

        }



        usersJsonResponse([

            'success' => true,

            'message' => 'User account created successfully.',

            'user' => $profileResult['data'][0]

        ], 201);

    }



    usersJsonResponse([

        'success' => false,

        'message' => 'Unknown action.'

    ], 400);

}



/* =========================================================

   LOAD PAGE DATA

   ========================================================= */



$users = [];

$pageError = '';



if (!usersIsAdmin()) {

    http_response_code(403);

    $pageError = 'Access denied. Please sign in using an Admin account.';

} else {

    $result = usersSupabaseRequest(

        '/rest/v1/users?select=user_id,display_id,full_name,email,role,status,specialization_category,specialization_type,last_active,created_at,updated_at&order=created_at.desc'

    );



    if ($result['success'] && is_array($result['data'])) {

        $users = $result['data'];

    } else {

        $pageError = 'Could not load users from Supabase. ' .

            (string) ($result['error'] ?? 'Unknown error.');

    }

}



renderHead('User & Role Management', 'Users.css');

?>



<div class="container">





    <main class="card border-highlight">

        <?php renderPageHeader('User & Role Management'); ?>

        <div class="card-header">

            <div class="search-wrapper">

                <span class="search-icon">🔍</span>

                <input

                    type="text"

                    placeholder="Search users by name, email, or role"

                    class="search-bar"

                    id="userSearch"

                >

            </div>



            <select class="user-filter" id="userRoleFilter">

                <option value="">All Roles</option>

                <option value="admin">Admin</option>

                <option value="regular">Regular User</option>

                <option value="maintenance">Maintenance Staff</option>

            </select>



            <select class="user-filter" id="userStatusFilter">

                <option value="">All Statuses</option>

                <option value="Active">Active</option>

                <option value="Inactive">Inactive</option>

            </select>



            <button class="reset-btn" type="button" id="userReset">Reset</button>

            <button class="add-btn" type="button" id="addUserBtn">+ Add User</button>

        </div>



        <?php if ($pageError !== ''): ?>

            <div style="margin:16px;padding:14px;border-radius:8px;background:#fff1f0;color:#9f2727;">

                <?= e($pageError) ?>

            </div>

        <?php endif; ?>



        <table class="user-table">

            <thead>

                <tr>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Role</th>

                    <th>Status</th>

                    <th>Last Active</th>

                    <th>Actions</th>

                </tr>

            </thead>



            <tbody id="userTableBody">

                <?php foreach ($users as $user):

                    $role = (string) ($user['role'] ?? 'regular');

                    $status = (string) ($user['status'] ?? 'Inactive');

                    $name = (string) ($user['full_name'] ?? '');

                    $email = (string) ($user['email'] ?? '');

                    $userId = (string) ($user['user_id'] ?? '');

                ?>

                    <tr

                        data-user-id="<?= e($userId) ?>"

                        data-role="<?= e($role) ?>"

                        data-status="<?= e($status) ?>"

                    >

                        <td class="font-bold"><?= e($name) ?></td>

                        <td class="text-gray"><?= e($email) ?></td>

                        <td class="text-gray"><?= e(userRoleLabel($role)) ?></td>

                        <td>

                            <span class="status-badge status-<?= e(strtolower($status)) ?>">

                                <?= e($status) ?>

                            </span>

                        </td>

                        <td class="text-gray">

                            <?= e(userLastActiveLabel(isset($user['last_active']) ? (string) $user['last_active'] : null)) ?>

                        </td>

                        <td class="user-actions">

                            <button

                                class="btn-edit"

                                type="button"

                                data-user-id="<?= e($userId) ?>"

                                data-user-name="<?= e($name) ?>"

                                data-user-email="<?= e($email) ?>"

                                data-user-role="<?= e($role) ?>"

                                data-user-status="<?= e($status) ?>"
                                data-user-specialization-category="<?= e((string) ($user['specialization_category'] ?? '')) ?>"
                                data-user-specialization-type="<?= e((string) ($user['specialization_type'] ?? '')) ?>"
                            >Edit</button>



                            <button

                                class="btn-delete"

                                type="button"

                                data-user-id="<?= e($userId) ?>"

                                data-user-name="<?= e($name) ?>"

                            >Delete</button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>



        <div id="userNoResults" style="display:none;text-align:center;padding:40px;color:#687985;">

            <p style="font-size:15px;font-weight:600;">No users found matching your filters.</p>

        </div>



        <div class="table-footer">

            <span id="userCount"><?= count($users) ?> users shown</span>

        </div>

    </main>

</div>



<!-- ADD USER MODAL -->

<div class="static-modal" id="addUserModal" style="display:none;">

    <div class="static-modal-content">

        <div class="modal-header">

            <h3>Add New User</h3>

            <button type="button" class="modal-close" id="addUserClose">✕</button>

        </div>



        <div class="modal-field">

            <label for="newUserName">Full Name</label>

            <input type="text" id="newUserName" maxlength="100" placeholder="e.g. Juan Dela Cruz">

        </div>



        <div class="modal-field">

            <label for="newUserEmail">Email Address</label>

            <input type="email" id="newUserEmail" maxlength="150" placeholder="e.g. juan@qcu.edu.ph">

        </div>



        <div class="modal-field">

            <label for="newUserUsername">Username</label>

            <input type="text" id="newUserUsername" maxlength="50" placeholder="e.g. juandelacruz">

        </div>



        <div class="modal-field">

            <label for="newUserPassword">Temporary Password</label>

            <input type="password" id="newUserPassword" minlength="8" placeholder="At least 8 characters">

        </div>



        <div class="modal-field">

            <label for="newUserConfirmPassword">Confirm Password</label>

            <input type="password" id="newUserConfirmPassword" minlength="8" placeholder="Re-enter password">

        </div>



        <div class="modal-field">

            <label for="newUserRole">Role</label>

            <select id="newUserRole">

                <option value="">Select role</option>

                <option value="admin">Admin</option>

                <option value="regular">Regular User</option>

                <option value="maintenance">Maintenance Personnel</option>

            </select>

        </div>



        <div class="modal-field specialization-field" id="newSpecializationCategoryField" style="display:none;">
            <label for="newSpecializationCategory">Specialization Category *</label>
            <select id="newSpecializationCategory">
                <option value="">Select specialization category</option>
                <option value="Electrical">Electrical</option>
                <option value="Non-Electrical">Non-Electrical</option>
            </select>
        </div>

        <div class="modal-field specialization-field" id="newSpecializationTypeField" style="display:none;">
            <label for="newSpecializationType">Specialization Type *</label>
            <select id="newSpecializationType" disabled>
                <option value="">Select category first</option>
            </select>
        </div>

        <div class="modal-field">

            <label for="newUserStatus">Status</label>

            <select id="newUserStatus">

                <option value="Active">Active</option>

                <option value="Inactive">Inactive</option>

            </select>

        </div>



        <div class="modal-actions">

            <button type="button" class="modal-cancel" id="addUserCancel">Cancel</button>

            <button type="button" class="modal-save" id="addUserSave">Add User</button>

        </div>

    </div>

</div>



<!-- EDIT USER MODAL -->

<div class="static-modal" id="editUserModal" style="display:none;">

    <div class="static-modal-content">

        <div class="modal-header">

            <h3>Edit User</h3>

            <button type="button" class="modal-close" id="editUserClose">✕</button>

        </div>



        <div class="modal-field">

            <label for="editUserName">Full Name</label>

            <input type="text" id="editUserName" maxlength="100">

        </div>



        <div class="modal-field">

            <label for="editUserEmail">Email Address</label>

            <input type="email" id="editUserEmail" maxlength="150" required>

            <small>Email will be updated in Supabase Auth and the user profile.</small>

        </div>



        <div class="modal-field">

            <label for="editUserRole">Role</label>

            <select id="editUserRole">

                <option value="admin">Admin</option>

                <option value="regular">Regular User</option>

                <option value="maintenance">Maintenance Personnel</option>

            </select>

        </div>



        <div class="modal-field specialization-field" id="editSpecializationCategoryField" style="display:none;">
            <label for="editSpecializationCategory">Specialization Category *</label>
            <select id="editSpecializationCategory">
                <option value="">Select specialization category</option>
                <option value="Electrical">Electrical</option>
                <option value="Non-Electrical">Non-Electrical</option>
            </select>
        </div>

        <div class="modal-field specialization-field" id="editSpecializationTypeField" style="display:none;">
            <label for="editSpecializationType">Specialization Type *</label>
            <select id="editSpecializationType" disabled>
                <option value="">Select category first</option>
            </select>
        </div>

        <div class="modal-field">

            <label for="editUserStatus">Status</label>

            <select id="editUserStatus">

                <option value="Active">Active</option>

                <option value="Inactive">Inactive</option>

            </select>

        </div>



        <div class="modal-actions">

            <button type="button" class="modal-cancel" id="editUserCancel">Cancel</button>

            <button type="button" class="modal-save" id="editUserSave">Save Changes</button>

        </div>

    </div>

</div>



<!-- SHARED SYSTEM MODAL -->

<div class="system-modal-overlay" id="systemModal" role="dialog" aria-modal="true" style="display:none;">

    <div class="system-modal">

        <div class="system-modal-icon" id="systemModalIcon"></div>

        <h3 class="system-modal-title" id="systemModalTitle">Title</h3>

        <p class="system-modal-text" id="systemModalText">Message</p>

        <div class="system-modal-actions" id="systemModalActions"></div>

    </div>

</div>



<style>

.btn-delete {

    margin-left: 6px;

    padding: 6px 10px;

    border: 1px solid #f0caca;

    border-radius: 7px;

    background: #fff5f5;

    color: #b42318;

    cursor: pointer;

    font: inherit;

    font-size: 12px;

    white-space: nowrap;

}

.btn-delete:hover {

    background: #fee2e2;

}

.system-btn-danger {

    background: #b42318 !important;

    color: #fff !important;

}

.user-actions {

    white-space: nowrap;

}

</style>



<script>

window.SystemModal = (function () {

    const overlay = document.getElementById('systemModal');

    const iconBox = document.getElementById('systemModalIcon');

    const titleEl = document.getElementById('systemModalTitle');

    const textEl = document.getElementById('systemModalText');

    const actionsEl = document.getElementById('systemModalActions');



    const icons = {

        question: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',

        warning: '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',

        success: '<svg viewBox="0 0 24 24"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',

        danger: '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'

    };



    function close() {

        overlay.style.display = 'none';

    }



    function open(options) {

        const {

            icon = 'question',

            title = '',

            text = '',

            confirmText = 'OK',

            cancelText = null,

            confirmClass = 'system-btn-confirm',

            onConfirm = () => {},

            onCancel = () => {}

        } = options;



        iconBox.className = 'system-modal-icon icon-' + icon;

        iconBox.innerHTML = icons[icon] || icons.question;

        titleEl.textContent = title;

        textEl.textContent = text;

        actionsEl.innerHTML = '';



        if (cancelText) {

            const cancelBtn = document.createElement('button');

            cancelBtn.type = 'button';

            cancelBtn.className = 'system-btn system-btn-cancel';

            cancelBtn.textContent = cancelText;

            cancelBtn.addEventListener('click', () => {

                close();

                onCancel();

            });

            actionsEl.appendChild(cancelBtn);

        }



        const confirmBtn = document.createElement('button');

        confirmBtn.type = 'button';

        confirmBtn.className = 'system-btn ' + confirmClass;

        confirmBtn.textContent = confirmText;

        confirmBtn.addEventListener('click', () => {

            close();

            onConfirm();

        });

        actionsEl.appendChild(confirmBtn);



        overlay.style.display = 'flex';

    }



    overlay.addEventListener('click', event => {

        if (event.target === overlay) close();

    });



    document.addEventListener('keydown', event => {

        if (event.key === 'Escape' && overlay.style.display === 'flex') close();

    });



    return { open, close };

})();



document.addEventListener('DOMContentLoaded', function () {
    const specializationOptions = {
        'Electrical': ['Computer Technician', 'Network Technician', 'Electrician', 'Aircon Technician'],
        'Non-Electrical': ['Plumber', 'Pipefitter', 'Carpenter', 'Mason', 'Painter', 'General Maintenance Technician']
    };

    function populateSpecializationTypes(prefix, category, selectedType = '') {
        const typeSelect = document.getElementById(prefix + 'SpecializationType');
        typeSelect.innerHTML = '<option value="">Select technician type</option>';
        (specializationOptions[category] || []).forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            typeSelect.appendChild(option);
        });
        typeSelect.disabled = !(specializationOptions[category] || []).length;
        if (selectedType && (specializationOptions[category] || []).includes(selectedType)) {
            typeSelect.value = selectedType;
        }
    }

    function toggleSpecializationFields(prefix) {
        const roleSelect = document.getElementById(prefix === 'new' ? 'newUserRole' : 'editUserRole');
        const show = roleSelect.value === 'maintenance';
        document.getElementById(prefix + 'SpecializationCategoryField').style.display = show ? '' : 'none';
        document.getElementById(prefix + 'SpecializationTypeField').style.display = show ? '' : 'none';
        const categorySelect = document.getElementById(prefix + 'SpecializationCategory');
        const typeSelect = document.getElementById(prefix + 'SpecializationType');
        categorySelect.required = show;
        typeSelect.required = show;
        if (!show) {
            categorySelect.value = '';
            typeSelect.innerHTML = '<option value="">Select category first</option>';
            typeSelect.disabled = true;
        }
    }

    const newRoleSelect = document.getElementById('newUserRole');
    const editRoleSelect = document.getElementById('editUserRole');
    const newCategorySelect = document.getElementById('newSpecializationCategory');
    const editCategorySelect = document.getElementById('editSpecializationCategory');
    newRoleSelect.addEventListener('change', () => toggleSpecializationFields('new'));
    editRoleSelect.addEventListener('change', () => toggleSpecializationFields('edit'));
    newCategorySelect.addEventListener('change', () => populateSpecializationTypes('new', newCategorySelect.value));
    editCategorySelect.addEventListener('change', () => populateSpecializationTypes('edit', editCategorySelect.value));


    const search = document.getElementById('userSearch');

    const roleFilter = document.getElementById('userRoleFilter');

    const statusFilter = document.getElementById('userStatusFilter');

    const resetBtn = document.getElementById('userReset');

    const noResults = document.getElementById('userNoResults');

    const userCount = document.getElementById('userCount');

    const tbody = document.getElementById('userTableBody');



    const addModal = document.getElementById('addUserModal');

    const editModal = document.getElementById('editUserModal');



    let editingRow = null;



    function applyFilters() {

        const query = search.value.toLowerCase().trim();

        const role = roleFilter.value;

        const status = statusFilter.value;

        const rows = tbody.querySelectorAll('tr');



        let visible = 0;



        rows.forEach(row => {

            const text = row.textContent.toLowerCase();



            const show =

                (!query || text.includes(query)) &&

                (!role || row.dataset.role === role) &&

                (!status || row.dataset.status === status);



            row.style.display = show ? '' : 'none';



            if (show) visible++;

        });



        noResults.style.display = visible === 0 ? 'block' : 'none';

        userCount.textContent = visible + ' user' + (visible !== 1 ? 's' : '') + ' shown';

    }



    search.addEventListener('input', applyFilters);

    roleFilter.addEventListener('change', applyFilters);

    statusFilter.addEventListener('change', applyFilters);



    resetBtn.addEventListener('click', () => {

        search.value = '';

        roleFilter.value = '';

        statusFilter.value = '';

        applyFilters();

    });



    function roleLabel(role) {

        return role === 'admin'

            ? 'Admin'

            : (role === 'maintenance' ? 'Maintenance Staff' : 'Regular User');

    }



    function showError(title, message) {

        SystemModal.open({

            icon: 'danger',

            title,

            text: message,

            confirmText: 'OK'

        });

    }



    /* ADD USER MODAL */

    document.getElementById('addUserBtn').addEventListener('click', () => {

        [

            'newUserName',

            'newUserEmail',

            'newUserUsername',

            'newUserPassword',

            'newUserConfirmPassword'

        ].forEach(id => {

            document.getElementById(id).value = '';

        });



        document.getElementById('newUserRole').value = '';

        document.getElementById('newSpecializationCategory').value = '';
        populateSpecializationTypes('new', '');
        toggleSpecializationFields('new');
        document.getElementById('newUserStatus').value = 'Active';

        addModal.style.display = 'flex';

    });



    document.getElementById('addUserClose').addEventListener('click', () => {

        addModal.style.display = 'none';

    });



    document.getElementById('addUserCancel').addEventListener('click', () => {

        addModal.style.display = 'none';

    });



    document.getElementById('addUserSave').addEventListener('click', async function () {

        const name = document.getElementById('newUserName').value.trim();

        const email = document.getElementById('newUserEmail').value.trim();

        const username = document.getElementById('newUserUsername').value.trim().toLowerCase();

        const password = document.getElementById('newUserPassword').value;

        const confirmPassword = document.getElementById('newUserConfirmPassword').value;

        const role = document.getElementById('newUserRole').value;

        const status = document.getElementById('newUserStatus').value;



        if (!name || !email || !username || !password || !confirmPassword || !role) {

            SystemModal.open({

                icon: 'warning',

                title: 'Missing required fields',

                text: 'Please complete all fields before creating the user.',

                confirmText: 'OK'

            });

            return;

        }



        if (password.length < 8) {

            SystemModal.open({

                icon: 'warning',

                title: 'Password too short',

                text: 'Use a password with at least 8 characters.',

                confirmText: 'OK'

            });

            return;

        }



        if (password !== confirmPassword) {

            SystemModal.open({

                icon: 'warning',

                title: 'Passwords do not match',

                text: 'Please enter the same password in both password fields.',

                confirmText: 'OK'

            });

            return;

        }



        const saveButton = this;

        saveButton.disabled = true;

        saveButton.textContent = 'Creating...';



        try {

            const body = new URLSearchParams({

                users_api: '1',

                action: 'create',

                full_name: name,

                email,

                username,

                password,

                confirm_password: confirmPassword,
                role,
                specialization_category: document.getElementById('newSpecializationCategory').value,
                specialization_type: document.getElementById('newSpecializationType').value,
                status

            });



            const response = await fetch(window.location.pathname + '?users_api=1', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',

                    'X-Requested-With': 'XMLHttpRequest'

                },

                body: body.toString()

            });



            const result = await response.json();



            if (!response.ok || !result.success) {

                throw new Error(result.message || 'Could not create user.');

            }



            const user = result.user;

            const row = document.createElement('tr');



            row.dataset.userId = user.user_id;

            row.dataset.role = user.role;

            row.dataset.status = user.status;



            const nameCell = document.createElement('td');

            nameCell.className = 'font-bold';

            nameCell.textContent = user.full_name || name;



            const emailCell = document.createElement('td');

            emailCell.className = 'text-gray';

            emailCell.textContent = user.email || email;



            const roleCell = document.createElement('td');

            roleCell.className = 'text-gray';

            roleCell.textContent = roleLabel(user.role || role);



            const statusCell = document.createElement('td');

            const badge = document.createElement('span');

            badge.className = 'status-badge status-' + String(user.status || status).toLowerCase();

            badge.textContent = user.status || status;

            statusCell.appendChild(badge);



            const activeCell = document.createElement('td');

            activeCell.className = 'text-gray';

            activeCell.textContent = '—';



            const actionsCell = document.createElement('td');



            const editButton = document.createElement('button');

            editButton.type = 'button';

            editButton.className = 'btn-edit';

            editButton.dataset.userId = user.user_id;

            editButton.dataset.userName = user.full_name || name;

            editButton.dataset.userEmail = user.email || email;

            editButton.dataset.userRole = user.role || role;

            editButton.dataset.userStatus = user.status || status;
            editButton.dataset.userSpecializationCategory = user.specialization_category || '';
            editButton.dataset.userSpecializationType = user.specialization_type || '';

            editButton.textContent = 'Edit';

            actionsCell.appendChild(editButton);



            const deleteButton = document.createElement('button');

            deleteButton.type = 'button';

            deleteButton.className = 'btn-delete';

            deleteButton.dataset.userId = user.user_id;

            deleteButton.dataset.userName = user.full_name || name;

            deleteButton.textContent = 'Delete';

            actionsCell.appendChild(deleteButton);



            row.append(

                nameCell,

                emailCell,

                roleCell,

                statusCell,

                activeCell,

                actionsCell

            );



            tbody.prepend(row);

            bindEditButtons();

            bindDeleteButtons();

            applyFilters();



            addModal.style.display = 'none';



            SystemModal.open({

                icon: 'success',

                title: 'User created successfully',

                text: 'The account was created in Supabase Auth and the user profile was saved.',

                confirmText: 'OK'

            });

        } catch (error) {

            showError('Add User failed', error.message || 'An unexpected error occurred.');

        } finally {

            saveButton.disabled = false;

            saveButton.textContent = 'Add User';

        }

    });



    /* EDIT USER */

    function openEditModal(button) {

        editingRow = button.closest('tr');



        document.getElementById('editUserName').value = button.dataset.userName || '';

        document.getElementById('editUserEmail').value = button.dataset.userEmail || '';

        document.getElementById('editUserRole').value = button.dataset.userRole || 'regular';
        const category = button.dataset.userSpecializationCategory || '';
        document.getElementById('editSpecializationCategory').value = category;
        populateSpecializationTypes('edit', category, button.dataset.userSpecializationType || '');
        toggleSpecializationFields('edit');

        document.getElementById('editUserStatus').value = button.dataset.userStatus || 'Active';



        editModal.style.display = 'flex';

    }



    function bindEditButtons() {

        tbody.querySelectorAll('.btn-edit').forEach(button => {

            button.onclick = () => openEditModal(button);

        });

    }



    document.getElementById('editUserClose').addEventListener('click', () => {

        editModal.style.display = 'none';

    });



    document.getElementById('editUserCancel').addEventListener('click', () => {

        editModal.style.display = 'none';

    });



    document.getElementById('editUserSave').addEventListener('click', async function () {

        if (!editingRow) return;



        const name = document.getElementById('editUserName').value.trim();

        const email = document.getElementById('editUserEmail').value.trim();

        const role = document.getElementById('editUserRole').value;

        const status = document.getElementById('editUserStatus').value;



        if (!name || !email || !role) {

            SystemModal.open({

                icon: 'warning',

                title: 'Missing required fields',

                text: 'Please fill in the Full Name, Email Address, and Role.',

                confirmText: 'OK'

            });

            return;

        }



        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {

            SystemModal.open({

                icon: 'warning',

                title: 'Invalid email address',

                text: 'Please enter a valid email address.',

                confirmText: 'OK'

            });

            return;

        }



        const saveButton = this;

        saveButton.disabled = true;

        saveButton.textContent = 'Saving...';



        try {

            const body = new URLSearchParams({

                users_api: '1',

                action: 'update',

                user_id: editingRow.dataset.userId || '',
                full_name: name,
                email,
                role,
                specialization_category: document.getElementById('editSpecializationCategory').value,
                specialization_type: document.getElementById('editSpecializationType').value,
                status

            });



            const response = await fetch(window.location.pathname, {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',

                    'X-Requested-With': 'XMLHttpRequest'

                },

                body: body.toString()

            });



            const result = await response.json();



            if (!response.ok || !result.success) {

                throw new Error(result.message || 'Could not save changes.');

            }



            const user = result.user;



            editingRow.dataset.role = user.role;

            editingRow.dataset.status = user.status;



            const cells = editingRow.querySelectorAll('td');

            cells[0].textContent = user.full_name;

            cells[1].textContent = user.email;

            cells[2].textContent = roleLabel(user.role);



            cells[3].innerHTML = '';

            const badge = document.createElement('span');

            badge.className = 'status-badge status-' + user.status.toLowerCase();

            badge.textContent = user.status;

            cells[3].appendChild(badge);



            cells[4].textContent = 'Just now';



            const editButton = editingRow.querySelector('.btn-edit');

            editButton.dataset.userName = user.full_name;

            editButton.dataset.userEmail = user.email;

            editButton.dataset.userRole = user.role;

            editButton.dataset.userStatus = user.status;
            editButton.dataset.userSpecializationCategory = user.specialization_category || '';
            editButton.dataset.userSpecializationType = user.specialization_type || '';



            const deleteButton = editingRow.querySelector('.btn-delete');

            if (deleteButton) {

                deleteButton.dataset.userName = user.full_name;

            }



            editModal.style.display = 'none';

            applyFilters();



            SystemModal.open({

                icon: 'success',

                title: 'User updated',

                text: 'Changes for "' + user.full_name + '" were saved to Supabase.',

                confirmText: 'OK'

            });

        } catch (error) {

            showError('Update failed', error.message || 'An unexpected error occurred.');

        } finally {

            saveButton.disabled = false;

            saveButton.textContent = 'Save Changes';

        }

    });



    /* DELETE USER */

    function bindDeleteButtons() {

        tbody.querySelectorAll('.btn-delete').forEach(button => {

            button.onclick = () => {

                const userName = button.dataset.userName || 'this user';



                SystemModal.open({

                    icon: 'warning',

                    title: 'Delete account?',

                    text: 'Are you sure you want to permanently delete "' +

                        userName +

                        '" from Supabase Auth and the users database? This action cannot be undone.',

                    confirmText: 'Delete Account',

                    cancelText: 'Cancel',

                    confirmClass: 'system-btn-danger',



                    onConfirm: async () => {

                        button.disabled = true;

                        button.textContent = 'Deleting...';



                        try {

                            const body = new URLSearchParams({

                                users_api: '1',

                                action: 'delete',

                                user_id: button.dataset.userId || ''

                            });



                            const response = await fetch(

                                window.location.pathname + '?users_api=1',

                                {

                                    method: 'POST',

                                    headers: {

                                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',

                                        'X-Requested-With': 'XMLHttpRequest'

                                    },

                                    body: body.toString()

                                }

                            );



                            const result = await response.json();



                            if (!response.ok || !result.success) {

                                throw new Error(result.message || 'Could not delete this account.');

                            }



                            const row = button.closest('tr');

                            if (row) row.remove();



                            applyFilters();



                            SystemModal.open({

                                icon: 'success',

                                title: 'Account deleted',

                                text: 'The account and its user profile have been deleted.',

                                confirmText: 'OK'

                            });

                        } catch (error) {

                            button.disabled = false;

                            button.textContent = 'Delete';



                            showError(

                                'Delete failed',

                                error.message || 'An unexpected error occurred.'

                            );

                        }

                    }

                });

            };

        });

    }



    bindEditButtons();

    bindDeleteButtons();



    /* CLOSE MODALS */

    [addModal, editModal].forEach(modal => {

        modal.addEventListener('click', event => {

            if (event.target === modal) {

                modal.style.display = 'none';

            }

        });

    });



    document.addEventListener('keydown', event => {

        if (event.key === 'Escape') {

            addModal.style.display = 'none';

            editModal.style.display = 'none';

        }

    });

});

</script>



<?php renderFoot(); ?>

</html>