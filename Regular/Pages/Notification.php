<?php
require_once '../../Components/layout.php';
require_once '../../Components/notification_view.php';

/* ---- Sample data ---- */
$notifications = [
    ['tone' => 'purple', 'title' => 'Maintenance Request Updated',   'text' => 'Your maintenance request MR-2026-001 – Leaking Faucet is now In Progress.', 'time' => 'Today, 9:00 AM', 'unread' => true,  'category' => 'maintenance'],
    ['tone' => 'blue',   'title' => 'Maintenance Request Assigned',  'text' => 'Your request has been assigned to Mang Juan, our maintenance staff.',       'time' => 'Today, 9:00 AM', 'unread' => true,  'category' => 'maintenance'],
    ['tone' => 'green',  'title' => 'Maintenance Request Submitted', 'text' => 'Your maintenance request MR-2026-001 has been successfully submitted.',      'time' => 'Today, 9:00 AM', 'unread' => false, 'category' => 'maintenance'],
];

/* Same notification design as the Admin page (shared view + stylesheet). */
renderHead('Notifications', 'Components/css/NotificationShared.css', 'regular');

renderNotificationPage('regular', 'Maintenance Reporting Portal • Barangay Gulod', $notifications, [
    ['key' => 'maintenance', 'label' => 'Maintenance Updates'],
]);

renderFoot();
