<?php
require_once '../../Components/layout.php';
require_once '../../Components/notification_view.php';

$notifications = [
    ['tone' => 'orange', 'title' => 'New Submission Received', 'text' => 'MT-00131 PC won\'t turn on submitted by Juan Dela Cruz.', 'time' => 'Oct 14, 2026 · 9:15 AM', 'unread' => true,  'category' => 'submission'],
    ['tone' => 'orange', 'title' => 'New Submission Received', 'text' => 'MT-00132 Broken aircon submitted by Maria Santos.',        'time' => 'Oct 14, 2026 · 8:45 AM', 'unread' => true,  'category' => 'submission'],
    ['tone' => 'green',  'title' => 'Request Approved',        'text' => 'MT-00133 has been approved and sent to Admin.',            'time' => 'Oct 13, 2026 · 3:20 PM', 'unread' => false, 'category' => 'decision'],
    ['tone' => 'red',    'title' => 'Request Rejected',        'text' => 'MT-00135 was rejected — duplicate of earlier request.',    'time' => 'Oct 12, 2026 · 4:30 PM', 'unread' => false, 'category' => 'decision'],
];

/* Same notification design as the Admin page (shared view + stylesheet). */
renderHead('Notifications', 'Components/css/NotificationShared.css', 'secretary');

renderNotificationPage('secretary', 'Secretary Portal · Barangay Gulod', $notifications, [
    ['key' => 'submission', 'label' => 'New Submissions'],
    ['key' => 'decision',   'label' => 'Decisions'],
]);

renderFoot();
