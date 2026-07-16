<?php

use App\Http\Controllers\Api\AisensyCampaignTriggerController;
use App\Http\Controllers\Api\FaceVerifyApproveController;
use Illuminate\Support\Facades\Route;

Route::post('v1/campaign/t1/api/v2', AisensyCampaignTriggerController::class)
    ->name('api.aisensy.campaign.trigger');

Route::post('face-verify/approve', FaceVerifyApproveController::class)
    ->name('api.face-verify.approve');
