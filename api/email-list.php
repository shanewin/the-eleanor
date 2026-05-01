<?php
/**
 * Mailing list signup handler — thin wrapper around form-processor.php
 *
 * Uses CORS instead of CSRF. No phone field.
 */
require_once __DIR__ . '/form-processor.php';

processForm([
    'table'           => 'mailing_list',
    'required'        => ['firstName', 'lastName', 'email'],
    'subject'         => 'New Email List Signup - {firstName} {lastName}',
    'success_message' => 'Successfully joined email list',
    'use_csrf'        => false,
    'use_cors'        => true,
    'has_phone'       => false,
    'validate'        => function () {
        $consent = isset($_POST['consent']) ? 'Yes' : 'No';
        if ($consent !== 'Yes') {
            return 'Please agree to receive updates';
        }
        return null;
    },
    'cors_origins'    => [
        'https://theeleanorbushwick.com',
        'http://localhost:8080',
        'http://localhost:8083',
    ],

    'fields' => function () {
        $consent = isset($_POST['consent']) ? 'Yes' : 'No';
        return [
            'firstName'  => clean('firstName'),
            'lastName'   => clean('lastName'),
            'interests'  => clean('interests'),
            'consent'    => $consent,
            'trackingId' => clean('tracking_id'),
        ];
    },

    'db_map' => function (array $f, string $ip) {
        return [
            'first_name'  => $f['firstName'],
            'last_name'   => $f['lastName'],
            'email'       => $f['email'],
            'interests'   => $f['interests'],
            'consent'     => $f['consent'],
            'tracking_id' => $f['trackingId'],
            'ip_address'  => $ip,
        ];
    },

    'email_body' => function (array $f) {
        return implode("\n", [
            "New Email List Signup:",
            "",
            "Name: " . $f['firstName'] . " " . $f['lastName'],
            "Email: " . $f['email'],
            "Interests: " . ($f['interests'] ?: 'Not specified'),
            "Consent: " . $f['consent'],
            "Date: " . date('Y-m-d H:i:s'),
        ]);
    },

    'enrich_args' => function (array $f) {
        return [$f['email'], $f['firstName'], $f['lastName']];
    },
]);
