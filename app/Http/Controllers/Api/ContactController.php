<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    // Rechercher un contact
    public function searchContacts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
        ], [
            'query.required' => 'Le champ de recherche est requis.',
            'query.min' => 'La recherche doit contenir au moins 3 caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }
        
        $results = User::where('username', 'like', '%' . $request->input('query') . '%')->get();

        return response()->json([
            'status_code' => 200,
            'message' => 'Résultats de recherche obtenus.',
            'data' => $results,
        ]);
    }

    // Envoyer une demande de contact
    public function sendContactRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idUser2' => 'required|exists:users,id',
        ], [
            'idUser2.required' => 'L\'identifiant de l\'utilisateur est requis.',
            'idUser2.exists' => 'L\'utilisateur spécifié n\'existe pas.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $existingContact = Contact::where(function ($query) use ($request) {
            $query->where('idUser1', auth()->id())
                  ->where('idUser2', $request->idUser2)
                  ->orWhere(function ($query) use ($request) {
                      $query->where('idUser1', $request->idUser2)
                            ->where('idUser2', auth()->id());
                  });
        })->first();

        if ($existingContact) {
            return response()->json([
                'status_code' => 400,
                'message' => 'Vous êtes déjà en contact avec cet utilisateur ou une demande est en attente.',
            ], 400);
        }
        $contact = new Contact();
        $contact->idUser1 = auth()->id();
        $contact->idUser2 = $request->idUser2;
        $contact->isBlockedUser1 = false;
        $contact->isBlockedUser2 = false;
        $contact->isAccepted = false;
        $contact->save();

        return response()->json([
            'status_code' => 201,
            'message' => 'Demande de contact envoyée avec succès.',
            'data' => $contact,
        ], 201);
    }

    // Accepter une demande de contact
    public function acceptContactRequest(Contact $contact)
    {
        if ($contact->idUser2 !== auth()->id()) {
            return response()->json([
                'status_code' => 403,
                'message' => 'Vous n\'avez pas la permission d\'accepter cette demande.',
            ], 403);
        }

        $contact->isAccepted = true;
        $contact->save();

        return response()->json([
            'status_code' => 200,
            'message' => 'Demande de contact acceptée.',
            'data' => $contact,
        ], 200);
    }

    // Refuser une demande de contact
    public function rejectContactRequest(Contact $contact)
    {
        if ($contact->idUser2 !== auth()->id()) {
            return response()->json([
                'status_code' => 403,
                'message' => 'Vous n\'avez pas la permission de refuser cette demande.',
            ], 403);
        }

        $contact->delete();

        return response()->json([
            'status_code' => 200,
            'message' => 'Demande de contact refusée.',
        ], 200);
    }

    // Liste des demandes de contact reçues
    public function listReceivedRequests()
    {
        $requests = Contact::where('idUser2', auth()->id())->where('isAccepted', false)->get();

        return response()->json([
            'status_code' => 200,
            'message' => 'Liste des demandes de contact reçues.',
            'data' => $requests,
        ]);
    }

    // Bloquer un contact
    public function blockContact(Contact $contact, Request $request)
    {
        if ($contact->idUser1 !== auth()->id() && $contact->idUser2 !== auth()->id()) {
            return response()->json([
                'status_code' => 403,
                'message' => 'Vous n\'avez pas la permission de bloquer ce contact.',
            ], 403);
        }

        if ($contact->idUser1 === auth()->id()) {
            $contact->isBlockedUser1 = true;
        } else {
            $contact->isBlockedUser2 = true;
        }

        $contact->save();

        return response()->json([
            'status_code' => 200,
            'message' => 'Contact bloqué avec succès.',
            'data' => $contact,
        ]);
    }

    // Liste des contacts établis
    public function listEstablishedContacts()
    {
        $contacts = Contact::where(function ($query) {
            $query->where('idUser1', auth()->id())
                  ->orWhere('idUser2', auth()->id());
        })->where('isAccepted', true)->get();

        return response()->json([
            'status_code' => 200,
            'message' => 'Liste des contacts établis.',
            'data' => $contacts,
        ]);
    }

    // Supprimer un contact
    public function deleteContact(Contact $contact)
    {
        if ($contact->idUser1 !== auth()->id() && $contact->idUser2 !== auth()->id()) {
            return response()->json([
                'status_code' => 403,
                'message' => 'Vous n\'avez pas la permission de supprimer ce contact.',
            ], 403);
        }

        $contact->delete();

        return response()->json([
            'status_code' => 200,
            'message' => 'Contact supprimé avec succès.',
        ]);
    }
}
