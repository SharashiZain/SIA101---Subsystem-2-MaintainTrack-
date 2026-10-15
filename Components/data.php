<?php
/* =========================================================
   SINGLE SOURCE OF TRUTH FOR DEMO DATA
========================================================= */

if (!function_exists('getDemoRequests')) {
    function getDemoRequests(): array
    {
        return [
            [
                'id'          => 'MT-2026-001',
                'title'       => 'Broken Aircon',
                'location'    => 'Barangay Hall · 2nd Floor',
                'facility'    => 'Barangay Hall',
                'area'        => '2nd Floor',
                'equipment'   => 'Aircon Unit 02',
                'category'    => 'Facility',
                'date'        => 'Sept 10, 2026',
                'technician'  => 'Pedro Santos',
                'status'      => 'In Progress',
                'statusClass' => 'in-progress',
                'priority'    => 'High',
                'description' => 'Aircon unit is not cooling properly and making unusual noise. Needs inspection and possible repair.',
            ],
            [
                'id'          => 'MT-2026-002',
                'title'       => 'Printer Not Working',
                'location'    => 'Barangay Hall · Records Office',
                'facility'    => 'Barangay Hall',
                'area'        => 'Records Office',
                'equipment'   => 'Printer',
                'category'    => 'Equipment',
                'date'        => 'Sept 9, 2026',
                'technician'  => 'Pending Assignment',
                'status'      => 'Pending',
                'statusClass' => 'pending',
                'priority'    => 'Medium',
                'description' => 'Printer is not responding to print commands. Paper feed may be jammed.',
            ],
            [
                'id'          => 'MT-2026-003',
                'title'       => 'Broken Light',
                'location'    => 'Covered Court · Main Area',
                'facility'    => 'Covered Court',
                'area'        => 'Main Area',
                'equipment'   => 'Light Fixture',
                'category'    => 'Electrical',
                'date'        => 'Sept 7, 2026',
                'technician'  => 'Carlo Reyes',
                'status'      => 'Completed',
                'statusClass' => 'completed',
                'priority'    => 'Low',
                'description' => 'Light fixture flickering and eventually stopped working.',
            ],
        ];
    }
}