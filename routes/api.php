<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DiscussionController;
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

//Discussions
Route::post('/discussions/create', [DiscussionController::class, 'createGroupDiscussion']);
Route::patch('/discussions/{discussion}', [DiscussionController::class, 'updateDiscussion']);
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'deleteDiscussion']);
Route::post('/discussions/add-member/{discussion}', [DiscussionController::class, 'addMember']);
Route::post('/discussions/remove-member/{discussion}', [DiscussionController::class, 'removeMember']);
Route::post('/discussions/archive/{discussion}', [DiscussionController::class, 'archiveDiscussion']);
Route::get('/discussions/export/{discussion}', [DiscussionController::class, 'exportDiscussionToPdf']);
Route::patch('/discussions/assign-admin/{discussion}', [DiscussionController::class, 'assignAdmin']);
Route::patch('/discussions/archive/{discussion}', [DiscussionController::class, 'archiveDiscussion']);
Route::get('/discussions/unarchived', [DiscussionController::class, 'listUnarchivedDiscussions']);
Route::get('/discussions/archived', [DiscussionController::class, 'listArchivedDiscussions']);
Route::patch('/discussions/mute/{discussion}', [DiscussionController::class, 'muteDiscussion']);

//Contacts. Fait
Route::get('/contact/search', [ContactController::class, 'searchContacts']);

// Envoyer une demande de contact. Fait
Route::post('/contact/send-request', [ContactController::class, 'sendContactRequest']);

// Accepter une demande de contact. Fait
Route::patch('/contact/accept/{contact}', [ContactController::class, 'acceptContactRequest']);

// Refuser une demande de contact. Fait
Route::delete('/contact/reject/{contact}', [ContactController::class, 'rejectContactRequest']);

// Liste des demandes de contact reçues
Route::get('/contact/requests', [ContactController::class, 'listReceivedRequests']);

// Bloquer un contact. Fait
Route::patch('/contact/block/{contact}', [ContactController::class, 'blockContact']);

// Liste des contacts établis
Route::get('/contact/established', [ContactController::class, 'listEstablishedContacts']);

// Supprimer un contact. Fait 
Route::delete('/contact/delete/{contact}', [ContactController::class, 'deleteContact']);




