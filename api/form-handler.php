<?php
/**
 * Waitlist submission handler — thin wrapper around form-processor.php
 */
require_once __DIR__ . '/form-processor.php';

processForm([
    'table'    => 'waitlist_submissions',
    'required' => ['firstName', 'lastName', 'email', 'phone'],
    'subject'  => 'New Wait List Submission - {firstName} {lastName}',

    'fields' => function () {
        return [
            'firstName'   => clean('firstName'),
            'lastName'    => clean('lastName'),
            'phone'       => clean('phone'),
            'moveInDate'  => clean('moveInDate'),
            'budget'      => clean('budget'),
            'unit'        => clean('unit'),
            'unitType'    => clean('unitType'),
            'hearAboutUs' => clean('hearAboutUs'),
            'message'     => clean('message'),
            'trackingId'  => clean('tracking_id'),
        ];
    },

    'db_map' => function (array $f, string $ip) {
        return [
            'first_name'    => $f['firstName'],
            'last_name'     => $f['lastName'],
            'email'         => $f['email'],
            'phone'         => $f['phone'],
            'move_in_date'  => $f['moveInDate'],
            'budget'        => $f['budget'],
            'unit'          => $f['unit'],
            'unit_type'     => $f['unitType'],
            'hear_about_us' => $f['hearAboutUs'],
            'message'       => $f['message'],
            'ip_address'    => $ip,
            'tracking_id'   => $f['trackingId'],
        ];
    },

    'email_body' => function (array $f) {
        return <<<EOD
New Wait List Inquiry for The Eleanor:

Name: {$f['firstName']} {$f['lastName']}
Email: {$f['email']}
Phone: {$f['phone']}
Move-In Date: {$f['moveInDate']}
Budget: {$f['budget']}
Unit Type: {$f['unitType']}
How Did You Hear About Us: {$f['hearAboutUs']}

Message:
{$f['message']}
EOD;
    },
]);
