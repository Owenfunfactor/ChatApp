<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MessageController;

Route::get('/test', function () {
    return response()->json(['message' => 'Test API Groupe 4']);
})->middleware('auth:sanctum');

// Route pour obtenir les informations de l'utilisateur connecté (protégé par Sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Routes publiques (pas besoin de token)
Route::post('register', [UserController::class, 'register']);
Route::get('verify-email', [UserController::class, 'verifyEmail']);
Route::get('send-reset-link/{user}', [UserController::class, 'sendResetLink']);
Route::post('reset-password/', [UserController::class, 'resetPassword']);
Route::post('login', [UserController::class, 'login'])->name('login');


// Users
Route::get('/user', [UserController::class, 'me'])->middleware('auth')->middleware('auth');
Route::patch('/user/update-profile', [UserController::class, 'updateUserProfileInfos'])->middleware('auth');
Route::patch('/user/update-password', [UserController::class, 'updateUserPassword'])->middleware('auth');
Route::post('/user/update-profile-picture/{user}', [UserController::class, 'updateProfilePicture'])->middleware('auth');
Route::delete('/user/desactivate', [UserController::class, 'desactivateAccount'])->middleware('auth');
Route::post('/user/logout', [UserController::class, 'logout'])->middleware('auth')->middleware("auth");

// Messages
Route::post('/messages/send', [MessageController::class, 'sendTextMessage']);
Route::patch('/messages/{message}', [MessageController::class, 'editMessage']);
Route::delete('/messages/{message}', [MessageController::class, 'deleteMessage']);
Route::post('/messages/report/{message}', [MessageController::class, 'reportMessage']);
Route::post('/messages/send-file', [MessageController::class, 'sendFileMessage']);
Route::get('/messages/search', [MessageController::class, 'searchMessages']);
Route::post('/messages/transfer/{message}', [MessageController::class, 'transferMessage']);

// Discussions
Route::post('/discussions/create', [DiscussionController::class, 'createGroupDiscussion']);
Route::patch('/discussions/{discussion}', [DiscussionController::class, 'updateDiscussion']);
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'deleteDiscussion']);
Route::post('/discussions/add-member/{discussion}', [DiscussionController::class, 'addMember']);
Route::post('/discussions/remove-member/{discussion}', [DiscussionController::class, 'removeMember']);
Route::patch('/discussions/mute/{discussion}', [DiscussionController::class, 'muteDiscussion']);
Route::get('/discussions/unarchived', [DiscussionController::class, 'listUnarchivedDiscussions']);
Route::get('/discussions/archived', [DiscussionController::class, 'listArchivedDiscussions']);
Route::patch('/discussions/assign-admin/{discussion}', [DiscussionController::class, 'assignAdmin']);
Route::get('/discussions/export/{discussion}', [DiscussionController::class, 'exportDiscussionToPdf']);

// Contacts
Route::get('/contact/search', [ContactController::class, 'searchContacts']);
Route::post('/contact/send-request', [ContactController::class, 'sendContactRequest']);
Route::patch('/contact/accept/{contact}', [ContactController::class, 'acceptContactRequest']);
Route::delete('/contact/reject/{contact}', [ContactController::class, 'rejectContactRequest']);
Route::get('/contact/requests', [ContactController::class, 'listReceivedRequests']);
Route::patch('/contact/block/{contact}', [ContactController::class, 'blockContact']);
Route::get('/contact/established', [ContactController::class, 'listEstablishedContacts']);
Route::delete('/contact/delete/{contact}', [ContactController::class, 'deleteContact']);
