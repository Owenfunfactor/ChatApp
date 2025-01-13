<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Exception;

class UserController extends Controller
{
    public function register(Request $request)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'identity.fullName' => 'required|string',
                'identity.bio' => 'nullable|string',
                'identity.picture' => 'nullable|url',
                'email' => 'required|email|unique:users,email',
                'username' => 'required|string|unique:users,username',
                'password' => 'required|min:6',
            ], [
                'identity.fullName.required' => 'Le champ nom complet est obligatoire',
                'email.required' => 'Le champ email est obligatoire',
                'email.email' => 'Le champ email doit être une adresse email valide',
                'email.unique' => 'Cet email est déjà utilisé',
                'username.required' => 'Le champ nom d\'utilisateur est obligatoire',
                'username.unique' => 'Ce nom d\'utilisateur est déjà utilisé',
                'password.required' => 'Le champ mot de passe est obligatoire',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
            ]);

            // Vérification des erreurs de validation
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Bad Request',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Création de l'utilisateur
            $user = new User();
            $user->identity = [
                'fullName' => $request->input('identity.fullName'),
                'bio' => $request->input('identity.bio', ''),
                'picture' => $request->input('identity.picture', null),
            ];
            $user->email = $request->input('email');
            $user->username = $request->input('username');
            $user->isOnLine = false; // Par défaut, l'utilisateur n'est pas en ligne
            $user->isActivated = true; // Par défaut, l'utilisateur est activé
            $user->password = Hash::make($request->input('password'), [
                'rounds' => 12,
            ]);
            $user->verifyAd = null;
            $user->verifyToken = null;
            $user->tokenExpiredAt = null;
            $user->save();

            return response()->json([
                'status_code' => 200,
                'error' => false,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
