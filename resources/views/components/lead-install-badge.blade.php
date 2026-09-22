@props(['lead'])

@php
    [$status, $label] = match (true) {
        $lead->brix_status === 'ACTIVE' => ['active', 'Active'],
        in_array($lead->brix_status, ['INSTALLED', 'AUTHORIZED'], true) => ['attention', 'Installed'],
        $lead->brix_status === 'UNINSTALLED' => ['offline', 'Uninstalled'],
        $lead->tracking_link_id !== null => ['inactive', 'Awaiting install'],
        default => ['inactive', 'Not sent'],
    };
@endphp

<x-status-badge :status="$status" :label="$label" />
