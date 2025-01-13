<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    
   // Connexion
   public function login(Request $request)
   {
       $validator = Validator::make($request->all(), [
           'email' => 'required|email',
           'password' => 'required|string|min:3',
       ]);

       if ($validator->fails()) {
           return response()->json([
               'status_code' => 422,
               'message' => 'Validation Failed',
               'errors' => $validator->errors(),
           ], 422);
       }

       if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
           return response()->json(['error' => 'Email ou mot de passe incorrect'], 401);
       }

       $user = Auth::user();
       $token = $user->createToken('API Token')->plainTextToken;

       return response()->json([
           'status_code' => 200,
           'message' => 'Connexion réussie',
           'token' => $token,
           'user' => $user,
       ], 200);
   }

   // Déconnexion
   public function logout(Request $request)
   {
       try {
           $user = $request->user();
           if ($user) {
               $user->tokens()->delete();
               return response()->json(['message' => 'Déconnexion réussie']);
           }
           return response()->json(['message' => 'Aucun utilisateur authentifié'], 401);
       } catch (Exception $e) {
           return response()->json(['message' => $e->getMessage()], 500);
       }
   }

   // Mise à jour des informations de profil
   public function updateUserProfileInfos(Request $request)
   {
       try {
           $user = $request->user();

           $validator = Validator::make($request->all(), [
               'identity.fullName' => 'required|string|max:255',
               'identity.bio' => 'nullable|string',
               'identity.picture' => 'nullable|url',
           ]);

           if ($validator->fails()) {
               return response()->json([
                   'status' => false,
                   'errors' => $validator->errors(),
               ], 422);
           }

           $user->identity = array_merge($user->identity, [
               'fullName' => $request->input('identity.fullName'),
               'bio' => $request->input('identity.bio', ''),
               'picture' => $request->input('identity.picture', null),
           ]);
           $user->save();

           return response()->json([
               'status' => true,
               'message' => 'Profil mis à jour avec succès',
               'data' => $user,
           ], 200);
       } catch (Exception $e) {
           return response()->json([
               'status' => false,
               'message' => $e->getMessage(),
           ], 500);
       }
   }

   // Mise à jour du mot de passe
   public function updateUserPassword(Request $request)
   {
       $validator = Validator::make($request->all(), [
           'current_password' => 'required|string',
           'new_password' => 'required|string|min:8|confirmed',
       ]);

       if ($validator->fails()) {
           return response()->json([
               'status' => false,
               'errors' => $validator->errors(),
           ], 422);
       }

       $user = $request->user();
       if (Hash::check($request->input('current_password'), $user->password)) {
           $user->password = Hash::make($request->input('new_password'));
           $user->save();

           return response()->json(['message' => 'Mot de passe mis à jour avec succès'], 200);
       }

       return response()->json(['message' => 'Le mot de passe actuel est incorrect'], 400);
   }

   public function sendResetLink(Request $request)
{
    // Validation de l'e-mail
    $validator = Validator::make($request->all(), [
        'email' => ['required', 'email', 'exists:users,email'],
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    // Trouver l'utilisateur
    $user = \App\Models\User::where('email', $request->email)->first();

    if ($user) {
        // Générer un token de réinitialisation (UUID)
        $token = \Str::uuid()->toString();

        // Enregistrer le token dans l'utilisateur
        $user->resetToken = $token;
        $user->tokenExpiredAt = now()->addMinutes(60); // Définir une expiration de 60 minutes
        $user->save();

        // Envoyer la notification avec le token
        $user->notify(new CustomResetPassword($user->email, $token));

        // Journalisation pour audit
        \Log::info("Lien de réinitialisation envoyé à : " . $request->email);

        return response()->json(['message' => 'Lien de réinitialisation envoyé avec succès.'], 200);
    }

    return response()->json(['error' => 'Une erreur est survenue.'], 500);
}

public function resetPassword(Request $request)
{
    // Validation des données
    $validator = Validator::make($request->all(), [
        'email' => ['required', 'email', 'exists:users,email'],
        'token' => ['required'],
        'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    // Vérification du token et de l'utilisateur
    $user = \App\Models\User::where('email', $request->email)
        ->where('resetToken', $request->token)
        ->where('tokenExpiredAt', '>', now())
        ->first();

    if ($user) {
        // Réinitialiser le mot de passe
        $user->password = \Hash::make($request->password);
        $user->resetToken = null; // Invalider le token
        $user->tokenExpiredAt = null;
        $user->save();

        // Invalider toutes les sessions actives
        $user->tokens()->delete();

        // Envoyer une confirmation par e-mail
        \Mail::to($user->email)->send(new PasswordResetMail($user));

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.'], 200);
    }

    return response()->json(['error' => 'Le token est invalide ou expiré.'], 400);
}

public function updateProfilePicture(Request $request)
{
    // Validation de l'image
    $validator = Validator::make($request->all(), [
        'profile_picture' => ['required', 'image', 'mimes:jpg,png', 'max:5120'], // 5 Mo = 5120 Ko
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    // Récupérer l'utilisateur connecté
    $user = auth()->user();

    // Récupération de l'image
    $image = $request->file('profile_picture');

    // Générer un nom unique pour le fichier
    $imageName = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();

    // Définir le chemin de stockage (ex : storage/app/public/profile_pictures)
    $path = $image->storeAs('public/profile_pictures', $imageName);

    // Si l'utilisateur a déjà une photo de profil, la supprimer pour éviter l'encombrement
    if (!empty($user->profile_picture)) {
        $oldImagePath = str_replace('storage', 'public', $user->profile_picture); // Convertir le chemin pour l'utiliser avec Storage
        if (\Storage::exists($oldImagePath)) {
            \Storage::delete($oldImagePath);
        }
    }

    // Mettre à jour le chemin de la photo dans la base de données
    $user->profile_picture = str_replace('public', 'storage', $path); // Stockage public pour l'accès via HTTP
    $user->save();

    // Réponse
    return response()->json(['message' => 'Photo de profil mise à jour avec succès.', 'profile_picture_url' => asset($user->profile_picture)], 200);
}


public function deactivateAccount(Request $request)
{
    // Validation des données entrées par l'utilisateur
    $validator = Validator::make($request->all(), [
        'password' => ['required'],
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    // Récupérer l'utilisateur connecté
    $user = auth()->user();

    // Vérification du mot de passe fourni
    if (!\Hash::check($request->password, $user->password)) {
        return response()->json(['error' => 'Mot de passe incorrect.'], 403);
    }

    // Désactivation du compte
    $user->isActive = false;
    $user->deactivatedAt = now(); // Optionnel : enregistrer la date de désactivation
    $user->save();

    // Déconnexion des sessions actives
    $user->tokens()->delete();

    // Journalisation pour suivi
    \Log::info("Compte désactivé pour l'utilisateur : " . $user->email);

    // Réponse
    return response()->json(['message' => 'Votre compte a été désactivé avec succès.'], 200);
}

   // Obtenir les informations de l'utilisateur connecté
   public function me(Request $request)
   {
       return response()->json($request->user());
   }
}
