<?php

return [
    'contact::contact.permissions.contacts' => [
        'admin.contact.index' => 'contact::contact.permissions.list',
        'admin.contact.filters' => 'contact::contact.permissions.filters',
        'admin.contact.create' => 'contact::contact.permissions.create',
        'admin.contact.export' => 'contact::contact.permissions.export',
        'admin.contact.edit' => 'contact::contact.permissions.edit',
        'admin.contact.delete' => 'contact::contact.permissions.delete',
        'admin.contact.mass_delete' => 'contact::contact.permissions.mass_delete',
        'admin.contact.view'=>'contact::contact.permissions.view'
    ],
];
