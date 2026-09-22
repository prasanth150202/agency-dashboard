@props(['stage'])

<x-status-badge
    :status="match($stage) {
        'ACTIVE' => 'active',
        'INSTALLED', 'INSTALL_STARTED', 'CONTACTED', 'INTERESTED' => 'attention',
        'NOT_INTERESTED', 'LOST' => 'offline',
        default => 'inactive',
    }"
    :label="ucwords(strtolower(str_replace('_', ' ', $stage)))"
/>
