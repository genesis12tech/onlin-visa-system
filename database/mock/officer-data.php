<?php

return [

    'officer' => [
        'name' => 'Priya Mehta',
        'initials' => 'PM',
        'role' => 'Senior Visa Officer',
        'officer_id' => 'OFF-001',
    ],

    // Flat top-level keys used by Phase 3 widgets
    'assigned_to_me' => 8,
    'approved_today' => 4,
    'pending_queue_count' => 12,
    'avg_decision_days' => 2.4,

    // Nested stats kept for backward compatibility
    'stats' => [
        'assigned_to_me' => ['value' => 8, 'label' => 'Active cases'],
        'approved_today' => ['value' => 4, 'label' => '+2 vs yesterday'],
        'pending_queue' => ['value' => 12, 'label' => 'Awaiting review'],
        'avg_decision_time' => ['value' => '2.4d', 'label' => '0.3d faster'],
    ],

    'priority_queue' => [
        [
            'reference' => 'VA-2024-A1F3K2',
            'applicant' => 'Arjun Mehta',
            'type' => 'Tourist',
            'days_pending' => 2,
            'priority' => 'low',
        ],
        [
            'reference' => 'VA-2024-C3H5M4',
            'applicant' => 'James Okonkwo',
            'type' => 'Work',
            'days_pending' => 7,
            'priority' => 'high',
        ],
        [
            'reference' => 'VA-2024-D4I6N5',
            'applicant' => 'Maria Santos',
            'type' => 'Business',
            'days_pending' => 1,
            'priority' => 'low',
        ],
        [
            'reference' => 'VA-2024-E5J706',
            'applicant' => 'Ahmed Al-Rashid',
            'type' => 'Tourist',
            'days_pending' => 2,
            'priority' => 'low',
        ],
    ],

    'team_workload' => [
        [
            'name' => 'Priya Mehta',
            'initials' => 'PM',
            'specialisations' => 'Tourist & Business',
            'assigned' => 8,
            'capacity' => 12,
            'bar_color' => 'bg-yellow-400',
        ],
        [
            'name' => 'Rahul Sharma',
            'initials' => 'RS',
            'specialisations' => 'Work & Student',
            'assigned' => 6,
            'capacity' => 12,
            'bar_color' => 'bg-green-400',
        ],
        [
            'name' => 'Anita Desai',
            'initials' => 'AD',
            'specialisations' => 'Medical & Transit',
            'assigned' => 10,
            'capacity' => 12,
            'bar_color' => 'bg-red-400',
        ],
        [
            'name' => 'Mohammed Khan',
            'initials' => 'MK',
            'specialisations' => 'Business & Work',
            'assigned' => 4,
            'capacity' => 12,
            'bar_color' => 'bg-green-400',
        ],
    ],

    'todays_activity' => [
        [
            'type' => 'approved',
            'description' => 'Approved VA-2024-G7L9Q8 (Lucas Müller)',
            'ago' => '2h ago',
        ],
        [
            'type' => 'requested',
            'description' => 'Requested docs for VA-2024-C3H5M4',
            'ago' => '4h ago',
        ],
        [
            'type' => 'assigned',
            'description' => 'Assigned VA-2024-B2G4L3 to self',
            'ago' => '5h ago',
        ],
    ],

];
