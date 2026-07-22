<?php

return [
    'cusotmer::customer.titles.customers' => [

        'admin.customer.index' => 'customer::customer.labels.list',
        'admin.customer.filters' => 'customer::customer.labels.filters',
        'admin.customer.create' => 'customer::customer.labels.create',
        'admin.customer.edit' => 'customer::customer.labels.edit',
        'admin.customer.delete' => 'customer::customer.labels.delete',
        'admin.customer.mass_delete' => 'customer::customer.labels.mass_delete',
        'admin.customer.deletedCustomer'=>'customer::customer.labels.deleted_customer',
        'admin.customer.deletedcustomerfilters' => 'customer::customer.labels.deleted_customer_filter',
        'admin.deletedCustomer.delete' => 'customer::customer.permissions.permanant_delete',
        'admin.deletedCustomer.mass_delete' => 'customer::customer.labels.deleted_customer_mass_delete',
        'admin.customer.mass_restore' => 'customer::customer.labels.deleted_customer_mass_restore',
        'admin.customer.restore' => 'customer::customer.labels.deleted_customer_restore',
        'admin.customer.address'    =>  'customer::customer.permissions.add_new_address', 
        'admin.address.get_address' =>  'customer::customer.permissions.get_address',
        'admin.address.delete'  =>  'customer::customer.permissions.delete_address',

        'admin.customer.group.index' => 'customer::customer_group.labels.list',
        'admin.customer.group.filters' => 'customer::customer_group.labels.filters',
        'admin.customer.group.create' => 'customer::customer_group.labels.create',
        'admin.customer.group.edit' => 'customer::customer_group.labels.edit',
        'admin.customer.group.delete' => 'customer::customer_group.labels.delete',
        'admin.customer.group.mass_delete' => 'customer::customer_group.labels.mass_delete',

        'admin.customerLog.index' => 'customer::customer_online_offline.labels.customer_online_log_list',
        'admin.customerLog.filters' => 'customer::customer_online_offline.labels.customer_online_log_filters',
        //'admin.customerLog.refreshGrid' => 'customer::customer_online_offline.labels.customer_online_refreshGrid',

        'admin.customerloginlog.index' => 'customer::customer.labels.customer_login_log_list',
        'admin.customerloginlog.filters' => 'customer::customer.labels.customer_login_log_filters', 
        'admin.customerloginlog.export' => 'customer::customer.labels.customer_login_log_export'
    ]
];