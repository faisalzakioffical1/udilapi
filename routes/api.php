<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\DeviceCreationController;
use App\Http\Controllers\Api\WakeupSimController;
use App\Http\Controllers\Api\TimeSynchronizationController;
use App\Http\Controllers\Api\AuxRelayController;
use App\Http\Controllers\Api\DeviceMetadataController;
use App\Http\Controllers\Api\IpPortController;
use App\Http\Controllers\Api\SanctionedLoadController;
use App\Http\Controllers\Api\LoadSheddingController;
use App\Http\Controllers\Api\TimeOfUseController;
use App\Http\Controllers\Api\MeterStatusController;
use App\Http\Controllers\Api\MeterDataSamplingController;
use App\Http\Controllers\Api\ApmsTrippingController;
use App\Http\Controllers\Api\MdiResetController;
use App\Http\Controllers\Api\OnDemandDataController;
use App\Http\Controllers\Api\ParameterizationCancellationController;
use App\Http\Controllers\Api\TransactionStatusController;
use App\Http\Controllers\Api\TransactionCancelController;
use App\Http\Controllers\Api\OnDemandParameterController;
use App\Http\Controllers\Api\OpticalPortController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Route::post('/authorization_service', [AuthorizationController::class, 'authorizeClient']);


Route::post('/authorization_service', [AuthorizationController::class, 'authorizeClient']);
Route::middleware(['api', 'api.validate'])->group(function () {
Route::post('/device_creation', [DeviceCreationController::class, 'create']) ->name('device_creation');
Route::post('/update_wake_up_sim_number', [WakeupSimController::class, 'update']) ->name('update_wake_up_sim_number');
Route::post('/time_synchronization', [TimeSynchronizationController::class, 'synchronize']) ->name('time_synchronization');
Route::post('/aux_relay_operations', [AuxRelayController::class, 'operateRelay']) ->name('aux_relay_operations');
Route::post('/update_device_metadata', [DeviceMetadataController::class, 'update']) ->name('update_device_metadata');
Route::post('/update_ip_port', [IpPortController::class, 'update']) ->name('update_ip_port');
Route::post('/sanctioned_load_control', [SanctionedLoadController::class, 'program']) ->name('sanctioned_load_control');
Route::post('/load_shedding_scheduling', [LoadSheddingController::class, 'program'])->name('load_shedding_scheduling');
Route::post('/update_time_of_use', [TimeOfUseController::class, 'update'])->name('update_time_of_use'); // not completed yet
Route::post('/update_meter_status', [MeterStatusController::class, 'update'])->name('update_meter_status');
Route::post('/meter_data_sampling', [MeterDataSamplingController::class, 'program'])->name('meter_data_sampling');
Route::post('/activate_meter_optical_port', [OpticalPortController::class, 'activate'])->name('activate_meter_optical_port');
Route::post('/apms_tripping_events', [APMSTrippingController::class, 'program'])->name('apms_tripping_events');
Route::post('/update_mdi_reset_date', [MDIResetController::class, 'updateMdiResetDate'])->name('update_mdi_reset_date') ;
Route::post('/on_demand_parameter_read', [OnDemandParameterController::class, 'read'])->name('on_demand_parameter_read');// not final
Route::post('/parameterization_cancellation', [ParameterizationCancellationController::class, 'cancel'])->name('parameterization_cancellation') ;
Route::post('/transaction_status', [TransactionStatusController::class, 'transactionStatus'])->name('transaction_status');   
Route::post('/on_demand_data_read', [OnDemandDataController::class, 'read']) ->name('on_demand_data_read');
Route::post('/transaction_cancel', [TransactionCancelController::class, 'cancel'])->name('transaction_cancel');

});
