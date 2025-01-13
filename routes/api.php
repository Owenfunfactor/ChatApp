<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MessageController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//User
Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::patch('updateUserPassword', [UserController::class, 'updateUserPassword']);
Route::patch('updateUserProfileInfos', [UserController::class, 'updateUserProfileInfos']);


//Messages
Route::post('/messages/send', [MessageController::class, 'sendTextMessage']);
Route::patch('/messages/{message}', [MessageController::class, 'editMessage']);
Route::delete('/messages/{message}', [MessageController::class, 'deleteMessage']);
Route::post('/messages/report/{message}', [MessageController::class, 'reportMessage']);
Route::post('/messages/send-file', [MessageController::class, 'sendFileMessage']);
Route::get('/messages/search', [MessageController::class, 'searchMessages']);
Route::post('/messages/transfer/{message}', [MessageController::class, 'transferMessage']);
