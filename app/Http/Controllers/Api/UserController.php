<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use Exception;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    /**
     * Enregistre un nouvel utilisateur dans la base de données.
     *
     * @param Request $request L'objet de requête contenant les données fournies par le client.
     *
     * @return JsonResponse Une réponse JSON indiquant le statut de l'opération.
     *
     * @throws \Exception Si une erreur inattendue survient lors de l'exécution.
     */
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
                    'message' => 'Bad Request, Veuillez bien remplir le formulaire svp.',
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
            $user->tokenExpiredAt = null;
            $token = Str::random(60);
            $user->token = $token;
            $user->save();

            // Envoie de mail
            Mail::to($user->email)->send(
                new WelcomeUser(
                    $user->email,
                    $request->input('identity.fullName'),
                    $user->token
                )
            );

            return response()->json([
                'status_code' => 200,
                'error' => false,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user,
                "token" => $token
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifie l'email d'un utilisateur en utilisant un jeton (token).
     *
     * @param Request $request L'objet de requête contenant le jeton pour la vérification de l'email.
     *
     * @return JsonResponse Une réponse JSON indiquant le statut de l'opération.
     *
     * @throws \Exception Si une erreur survient lors du traitement.
     */
    public function verifyEmail(Request $request)
    {
        try {
            $user = User::where('token', $request->token)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Pas d\'acces',
                ]);
            }

            $user->token = Str::random(60);
            $user->verifyAd = now();
            $user->save();

            return response()->json([
                'status_code' => 200,
                'error' => false,
                'message' => 'Email verifier avec succès',
                "token" => $user->token
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Authentifie un utilisateur et retourne un jeton d'accès en cas de succès.
     *
     * @param Request $request L'objet de requête contenant les informations d'authentification de l'utilisateur.
     *
     * @return JsonResponse Une réponse JSON contenant le statut, un message, et le jeton d'accès en cas de succès.
     *
     * @throws ValidationException Si les données de validation ne respectent pas les contraintes définies.
     */
    public function login(Request $request)
    {
        // Validation des entrées
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Préparer les identifiants pour l'authentification
        $credentials = $request->only('email', 'password');

        // Essayer de générer un token
        if (!$token = Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        // Si tout est bon, retourner le token
        return response()->json([
            'status' => 'success',
            'message' => 'Connexion réussie.',
            'token' => Auth::user()->token,
            'user' => Auth::user(),
        ], 200);
    }

    // Déconnexion
    public function logout()
    {
        try {
            Auth::logout(); // Invalide le token
            return response()->json([
                'status' => 'success',
                'message' => 'Déconnexion réussie.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la déconnexion.',
            ], 500);
        }
    }

    // Mise à jour des informations de profil
    public function updateUserProfileInfos(Request $request, $id)
    {
        try {

            $token = $request->header('Authorization');
            if ($token && str_starts_with($token, 'Bearer ')) { $token = substr($token, 7); }

            $user = User::find($id);
            if (!$user) {return response()->json(['message' => 'Utilisateur non trouvé'], 404);}
            if (!$user->token === $token) {
                return response()->json([
                    'message' => 'Token invalide.',
                ], 403);
            }

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
    // Uploa une photo de profil 
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

        // Mise à jour de l'utilisateur avec le chemin de l'image
        $user->identity['picture'] = $path;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Photo de profil mise à jour avec succès.',
            'data' => $user,
        ], 200);
    }


    public function desactivateAccount(Request $request)
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
