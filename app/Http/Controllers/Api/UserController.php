<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Mail\RestPassword;
use App\Mail\WelcomeUser;
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

    private function verifyUser(Request $request, string $id)
    {
        try {

            $user = User::find($id);
            //dd($user);
            if (!$user) {
                return null;
            }

            $token = $request->header('Authorization');
            if ($token != null && str_starts_with($token, 'Bearer')) {
                $token = substr($token, 7);
            }

            if ($user->token != $token) {
                return null;
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function getToken($request)
    {
        $token = $request->header('Authorization');
        if ($token != null && str_starts_with($token, 'Bearer')) {
            $token = substr($token, 7);
        }
        return $token;
    }

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
            $user->verifyToken = Str::random(60);
            $user->tokenExpiredAt = null;
            $user->tokenExpiredAt = null;
            $user->save();
            // Envoie de mail
            Mail::to($user->email)->send(
                new WelcomeUser(
                    $user->email,
                    $request->input('identity.fullName'),
                    $user->verifyToken
                )
            );

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
            $user = User::where('verifyToken', $request->token)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Pas d\'acces',
                ]);
            }

            $user->verifyAd = now();
            $user->save();

            return ApiResponse::success();
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
            return ApiResponse::error("Email ou mot de passe incorrect.", null, 401);
        }

        Auth::user()->isOnLine = true;
        Auth::user()->save();
        return ApiResponse::success(['token' => $token, "user" => Auth::user()], "Connexion réussie.");
    }

    /**
     * Déconnecte l'utilisateur et met à jour son statut en ligne
     * 
     * @return \App\Http\Resources\ApiResponse
     * 
     * @throws \Exception En cas d'erreur lors de la déconnexion
     * 
     * @return \Illuminate\Http\JsonResponse Retourne une réponse JSON avec le statut de la déconnexion
     *      - En cas de succès : {"status": "success", "message": "Déconnexion réussie", "data": null}
     *      - En cas d'erreur : {"status": "error", "message": "Erreur lors de la déconnexion.", "errors": null}
     */
    public function logout()
    {
        try {
            Auth::logout();
            Auth::user()->isOnLine = false;
            Auth::user()->save();
            return ApiResponse::success(null, "Déconnexion réussie");
        } catch (\Exception $e) {
            return ApiResponse::error("Erreur lors de la déconnexion.", null, 500);
        }
    }

    /**
     * Met à jour les informations du profil d'un utilisateur.
     *
     * @param Request $request La requête contenant les nouvelles informations du profil.
     * @param int $id L'identifiant de l'utilisateur à mettre à jour.
     * @return JsonResponse La réponse JSON contenant le statut de l'opération.
     *
     * @throws \Exception En cas d'erreur serveur.
     */
    public function updateUserProfileInfos(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identity.fullName' => 'required|string|max:255',
                'identity.bio' => 'nullable|string',
                'identity.picture' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validation($validator->errors());
            }

            $user = Auth::user();
            $user->identity = array_merge($user->identity, [
                'fullName' => $request->input('identity.fullName'),
                'bio' => $request->input('identity.bio', ''),
                'picture' => $request->input('identity.picture', null),
            ]);
            $user->save();
            $token = auth()->refresh();;

            return ApiResponse::success(["token" => $token, "user" => Auth::user()], "Profil mis à jour avec succès");
        } catch (Exception $e) {
            return ApiResponse::error("Une erreur est survenue.", $e->getMessage(), 500);
        }
    }

    /**
     * Met à jour le mot de passe d'un utilisateur après vérification de l'ancien mot de passe.
     *
     * @param Request $request La requête contenant le mot de passe actuel et le nouveau mot de passe.
     * @param string $id L'identifiant de l'utilisateur.
     * @return JsonResponse La réponse JSON contenant le statut de l'opération.
     *
     * @throws \Exception En cas d'erreur serveur.
     */
    public function updateUserPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }


        if (Hash::check($request->input('current_password'), Auth::user()->password)) {
            Auth::user()->password = Hash::make($request->input('new_password'));
            Auth::user()->save();
            $token = UserController::getToken($request);
            return ApiResponse::success([
                "token" => $token,
                "user" => Auth::user()
            ], "Mot de passe modifié.");
        }

        return ApiResponse::error("Le mot de passe actuel est incorrect", null, 400);
    }

    /**
     * Envoie un lien de réinitialisation du mot de passe à l'utilisateur.
     *
     * @param Request $request La requête contenant les données nécessaires.
     * @param string $id L'identifiant de l'utilisateur.
     * @return JsonResponse La réponse JSON indiquant le statut de l'opération.
     *
     * @throws \Exception En cas d'erreur lors de l'envoi de l'email.
     */
    public function sendResetLink(Request $request, string $id)
    {
        try {
            $user = User::find($id);
            $user->verifyAd = Str::random(60);
            $user->save();
            Mail::to($user->email)->send(
                new WelcomeUser(
                    $user->email,
                    $request->input('identity.fullName'),
                    $user->verifyAd
                )
            );

            return ApiResponse::success(null, "Email de verification renvoyer avec succes");
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null,500);
        }
    }


    /**
     * Génère un token de réinitialisation et envoie un email à l'utilisateur pour réinitialiser son mot de passe.
     *
     * @param Request $request La requête contenant l'email de l'utilisateur.
     * @return JsonResponse La réponse JSON indiquant le statut de l'opération.
     *
     * @throws \Exception En cas d'erreur lors du traitement.
     */
    public function resetPassword(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            if(!$user){ return ApiResponse::error("Utilisateur invalid", null, 401);}
            $user->verifyAd = Str::random(60);
            $user->save();
            Mail::to($user->email)->send(new RestPassword(
                $user->email,
                $user->identity['fullName'],
                $user->verifyAd
            ));

            return ApiResponse::success(null, "Email de verification renvoyer avec succes");
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        }
    }

    // TODO : Fix this fonctionnality 
    public function updateProfilePicture(Request $request, string $id)
    {
        dd($id, $request->all());
        // Validation de l'image
        $validator = Validator::make($request->all(), [
            'profile_picture' => ['required', 'image', 'mimes:jpg,png', 'max:5120'], // 5 Mo = 5120 Ko
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = $this->verifyUser($request, $id);
    }

    /**
     * Désactive le compte d'un utilisateur après vérification du mot de passe.
     *
     * @param Request $request La requête HTTP contenant le mot de passe de l'utilisateur.
     * @param string $id L'identifiant unique de l'utilisateur.
     * @return JsonResponse Réponse JSON contenant le statut et un message.
     */
    public function desactivateAccount(Request $request)
    {
        // Validation des données entrées par l'utilisateur
        $validator = Validator::make($request->all(), [
            'password' => ['required'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Impossible d\'effectuer cette action', $validator->errors(), 500);
        }

        if (!Hash::check($request->password, Auth::user()->password)) {
            return ApiResponse::error('Impossible d\'effectuer cette action', null, 500);
        };

        try {
            Auth::user()->isActivated = false;
            Auth::user()->isOnLine = false;
            Auth::user()->save();
            Auth::logout();

            return ApiResponse::success(Auth::user(), "Compte desactiver");
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), "Une erreur s'est produite", 500);
        }
    }

    /**
     * Désactive le compte d'un utilisateur après vérification du mot de passe.
     *
     * @param Request $request La requête HTTP contenant le mot de passe de l'utilisateur.
     * @param string $id L'identifiant unique de l'utilisateur.
     * @return JsonResponse Réponse JSON contenant le statut et un message.
     */
    public function me(Request $request)
    {
        try {
            return ApiResponse::success([
                'user' => Auth::user(),
                'token' => UserController::getToken($request),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
