<?php

return [
    'title' => 'Settings',
    'defaults_section' => 'System Defaults',
    'default_language' => 'Default Language',
    'default_currency' => 'Default Currency',
    'flash' => [
        'updated' => 'Settings updated successfully.',
    ],
    'office_section' => 'Office location (soft geofence)',
    'office_hint' => 'One company office. Punches outside the radius are still accepted, but the employee, team leader, and admin are warned.',
    'office_latitude' => 'Office latitude',
    'office_longitude' => 'Office longitude',
    'office_radius_meters' => 'Allowed radius (meters)',
    'office_radius_hint' => 'Default 1000 m (1 km). Leave lat/lng empty to disable distance checks (GPS is still required on punch).',
    'impersonation_otp' => 'Access code',
    'impersonation_unlock' => 'Unlock',
    'impersonation_disable' => 'Lock',
    'impersonation_active' => 'Support access is on.',
    'impersonation_otp_missing' => 'Support access is not configured on this server.',
];
