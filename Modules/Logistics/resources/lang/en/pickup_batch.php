<?php

return [
    'titles' => [
        'pickup_batches' => 'Pickup batches',
        'list' => 'Pickup batches',
        'show' => 'Pickup batch :manifest',
        'shipments' => 'Packages',
        'details' => 'Details',
    ],

    'labels' => [
        'list' => 'List',
        'filters' => 'Filter',
        'show' => 'View',
        'back' => 'Back',
        'search' => 'Manifest no or AWB',
        'status' => 'Status',
        'pickup_date' => 'Pickup date',
        'seller_search' => 'Seller code or store',
        'warehouse' => 'Pickup address',
        'download' => 'Print manifest',
        'courier_manifest' => 'Courier manifest',
    ],

    'columns' => [
        'id' => 'ID',
        'manifest_no' => 'Manifest No',
        'seller' => 'Seller',
        'carrier_name' => 'Courier',
        'pickup_date' => 'Pickup Date',
        'shipments_count' => 'Packages',
        'status' => 'Status',
        'created_at' => 'Created At',
    ],

    'statuses' => [
        'open' => 'Pickup not scheduled',
        'manifested' => 'Pickup scheduled',
        'picked_up' => 'Picked up',
        'partially_picked' => 'Partly picked up',
    ],

    'messages' => [
        'not_found' => 'Pickup batch not found.',
        'one_warehouse' => 'Select orders from one pickup address at a time.',
        'not_booked' => 'Order :order has no AWB yet.',
        'already_batched' => 'Order :order is already on a manifest or has been picked up.',
        'pickup_not_scheduled' => 'The manifest was created but the courier did not confirm the pickup. Logistics Ops will follow up.',
    ],

    'seller' => [
        'menu' => 'Manifests',
        'title' => 'Manifests',
        'hint' => 'Each manifest lists the packed orders handed to one courier on a pickup date. Print it and have the courier agent sign it at pickup.',
        'packed_orders' => 'Packed orders',
        'empty_title' => 'No manifests yet',
        'empty_text' => 'Select packed orders on the order desk and choose "Create manifest".',
        'actions' => 'Actions',
        'print' => 'Print',
        'courier_manifest' => 'Courier copy',
        'created' => 'Manifest :manifest created for :count package(s).',
    ],

    'pdf' => [
        'title' => 'Pickup manifest',
        'manifest_no' => 'Manifest no',
        'pickup_date' => 'Pickup date',
        'courier' => 'Courier',
        'pickup_reference' => 'Pickup reference',
        'pickup_from' => 'Pickup from',
        'awb' => 'AWB',
        'sub_order' => 'Sub-order',
        'items' => 'Items',
        'weight' => 'Weight (kg)',
        'payment' => 'Payment',
        'cod_amount' => 'COD amount',
        'totals' => ':count package(s) · COD to collect',
        'handover' => 'Handover',
        'handed_over_by' => 'Handed over by (seller)',
        'received_by' => 'Received by (courier agent)',
        'name' => 'Name',
        'signature_date' => 'Signature, date and time',
        'packages_received' => 'Packages received in good condition: :count',
    ],
];
