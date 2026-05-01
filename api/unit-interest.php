<?php
/**
 * Unit inquiry handler — thin wrapper around form-processor.php
 */
require_once __DIR__ . '/form-processor.php';

processForm([
    'table'           => 'unit_inquiries',
    'required'        => ['firstName', 'lastName', 'email', 'phone'],
    'subject'         => 'New Unit Inquiry: {unit}',
    'success_message' => 'Thank you for your interest!',

    'fields' => function () {
        return [
            'firstName'   => clean('firstName'),
            'lastName'    => clean('lastName'),
            'phone'       => clean('phone'),
            'unit'        => clean('unit'),
            'moveInDate'  => clean('moveInDate'),
            'budget'      => clean('budget'),
            'hearAboutUs' => clean('hearAboutUs'),
            'message'     => clean('message'),
            'trackingId'  => clean('tracking_id'),
        ];
    },

    'db_map' => function (array $f, string $ip) {
        return [
            'unit'          => $f['unit'],
            'first_name'    => $f['firstName'],
            'last_name'     => $f['lastName'],
            'email'         => $f['email'],
            'phone'         => $f['phone'],
            'move_in_date'  => $f['moveInDate'],
            'budget'        => $f['budget'],
            'hear_about_us' => $f['hearAboutUs'],
            'message'       => $f['message'],
            'ip_address'    => $ip,
            'tracking_id'   => $f['trackingId'],
        ];
    },

    'email_body' => function (array $f) {
        return implode("\n", [
            "New Unit Inquiry details:",
            "",
            "Unit: " . $f['unit'],
            "Name: " . $f['firstName'] . " " . $f['lastName'],
            "Email: " . $f['email'],
            "Phone: " . $f['phone'],
            "Move-in Date: " . $f['moveInDate'],
            "Message:",
            $f['message'],
        ]);
    },
]);
