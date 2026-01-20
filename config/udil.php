<?php

return [

    // Global Flags
    'update_meter_visuals_for_write_services' => env('UDIL_UPDATE_METER_VISUALS'),
    'update_udil_log_for_write_services'     => env('UDIL_UPDATE_UDIL_LOG'),

    // Global Messages
    'meter_not_exists' => env('UDIL_METER_NOT_EXISTS', "This meter does not exists in MDC. Please, create it first"),

];

// config('udil.update_meter_visuals_for_write_services');
// config('udil.update_udil_log_for_write_services');
// config('udil.meter_not_exists');


// private $update_meter_visuals_for_write_services = false; // private $update_udil_log_for_write_services = false; // private $meter_not_exists = "This meter does not exists in MDC.