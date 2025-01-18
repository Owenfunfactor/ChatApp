<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Message;
use Exception;

class DiscussionController extends Controller
{
    public function createGroupDiscussion(Request $request)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'description' => 'nullable|string',
                'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5048',
                'participants' => 'required|array|min:1', // Au moins une personne autre que le créateur
                'participants.*' => 'exists:users,_id',
                'tags' => 'required|in:GROUPE',
            ], [
                'name.required' => 'Le nom de la discussion est obligatoire.',
                'participants.required' => 'Les participants sont obligatoires.',
                'participants.min' => 'Une discussion de groupe nécessite au moins un participant autre que le créateur.',
                'participants.*.exists' => 'Un des participants spécifiés n\'existe pas.',
                'tags.required' => 'Le type de discussion est obligatoire.',
                'tags.in' => 'Le type de discussion doit être "GROUPE".',
                'picture.image' => 'Le fichier de l\'image doit être une image valide.',
                'picture.mimes' => 'L\'image doit être au format jpeg, png, jpg, ou gif.',
                'picture.max' => 'La taille maximale de l\'image est de 5 Mo.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Bad Request',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Gérer l'upload de l'image si fournie
            $picturePath = null;
            if ($request->hasFile('picture')) {
                $picturePath = $request->file('picture')->store('discussions', 'public'); // Stockage dans le dossier "discussions"
            }

            // Création de la discussion
            $discussion = new Discussion();
            $discussion->name = $request->name;
            $discussion->description = $request->description ?? '';
            $discussion->picture = $picturePath ? url("storage/{$picturePath}") : null; // URL complète si l'image est stockée
            $discussion->tags = $request->tags;
            $discussion->createdBy = auth()->id(); // Utilisateur connecté

            // Ajout des participants
            $participants = collect($request->participants)->map(function ($id) {
                return [
                    'id' => $id,
                    'isSilent' => false,
                    'isArchived' => false,
                    'isDelected' => false,
                    'isAdmin' => false,
                    'hasNotification' => true,
                ];
            });

            // Ajouter le créateur comme administrateur
            $participants->push([
                'id' => $request->senderId,
                'isSilent' => false,
                'isArchived' => false,
                'isDelected' => false,
                'isAdmin' => true,
                'hasNotification' => true,
            ]);

            $discussion->participants = $participants->toArray();

            $discussion->save();

            return response()->json([
                'status_code' => 201,
                'message' => 'Discussion créée avec succès.',
                'data' => $discussion,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la création de la discussion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Ajouter un membre.
    public function addMember(Request $request, Discussion $discussion)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if (!$discussion) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Discussion not found',
                ], 404);
            }

            if ($discussion->tags === 'PRIVATE') {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Cannot add members to a private discussion',
                ], 403);
            }

            $currentUserId = auth()->id();
            $currentUser = collect($discussion->participants)->filter(function($participant) use ($currentUserId) {
                return $participant['id'] == $currentUserId;
            })->first();   

            if (!$currentUser || !$currentUser['isAdmin']) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Only admins can add members to this discussion',
                ], 403);
            }

            if (collect($discussion->participants)->contains('id', $request->userId)) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'User is already a participant',
                ], 400);
            }

            // Ajouter un nouveau participant au tableau
            $discussion->participants = array_merge($discussion->participants, [
                [
                    'id' => $request->userId,
                    'isSilent' => false,
                    'isArchived' => false,
                    'isDelected' => false,
                    'isAdmin' => false,
                    'hasNotification' => true,
                ]
            ]);

            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Member added successfully',
                'data' => $discussion,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Retirer un membre.
    public function removeMember(Request $request, Discussion $discussion)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,_id',
            ], [
                'userId.required' => 'L\'identifiant de l\'utilisateur est requis.',
                'userId.exists' => 'L\'utilisateur spécifié n\'existe pas.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Vérifier si la discussion existe
            if (!$discussion) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Discussion introuvable.',
                ], 404);
            }

            $currentUserId = auth()->id();

            // Vérification des droits de l'utilisateur actuel
            $currentUser = collect($discussion->participants)->firstWhere('id', $currentUserId);
            if (!$currentUser || !$currentUser['isAdmin']) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Seuls les administrateurs peuvent retirer des membres de cette discussion.',
                ], 403);
            }

            // Vérifier que l'utilisateur à supprimer est bien dans la discussion
            $userToRemove = collect($discussion->participants)->firstWhere('id', $request->userId);
            if (!$userToRemove) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Le membre spécifié n\'est pas un participant de cette discussion.',
                ], 404);
            }

            // Vérifier que la discussion aura au moins deux participants après suppression
            if (count($discussion->participants) <= 2) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Impossible de supprimer ce membre. Une discussion doit contenir au moins deux participants.',
                ], 403);
            }

            // Supprimer le membre de la liste des participants
            $discussion->participants = collect($discussion->participants)
                ->filter(fn($participant) => $participant['id'] !== $request->userId)
                ->values()
                ->toArray();

            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Membre supprimé avec succès.',
                'data' => $discussion,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Modifier une discussion.
    public function updateDiscussion(Request $request, Discussion $discussion)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if (!$discussion) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Discussion not found',
                ], 404);
            }

            $currentUserId = auth()->id();
            $currentUser = collect($discussion->participants)->firstWhere('id', $currentUserId);

            if (!$currentUser || !$currentUser['isAdmin']) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Only admins can update this discussion',
                ], 403);
            }

            $discussion->name = $request->name ?? $discussion->name;
            $discussion->description = $request->description ?? $discussion->description;
            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussion updated successfully',
                'data' => $discussion,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Exporter une discussion en PDF. Pas vérifier si l'exportateur fait partie de la discussion
    public function exportDiscussionToPdf(Discussion $discussion)
    {
        try {

            if (!$discussion) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Discussion non trouvée.',
                ], 404);
            }

            // Récupérer tous les messages de la discussion
            $messages = Message::where('discussionId', $discussion->id)->orderBy('createdAt', 'asc')->get();

            // Générer le contenu du PDF
            $pdfContent = view('pdf.discussion', compact('discussion', 'messages'))->render();

            // Créer le PDF
            $pdf = Pdf::loadHTML($pdfContent)->setPaper('a4', 'portrait');

            // Télécharger le PDF
            return $pdf->download($discussion->name . '_messages.pdf');
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de l\'exportation de la discussion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Nommer un administrateur.
    public function assignAdmin(Request $request, Discussion $discussion)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,_id',
            ], [
                'userId.required' => 'L\'ID de l\'utilisateur est requis.',
                'userId.exists' => 'L\'utilisateur spécifié n\'existe pas.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Bad Request',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Vérifier que la discussion est de type GROUPE ou DIFFUSION
            if (!in_array($discussion->tags, ['GROUPE', 'DIFFUSION'])) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Cette action n\'est permise que pour les discussions de type GROUPE ou DIFFUSION.',
                ], 403);
            }

            $currentUserId = auth()->id();
            $currentUser = collect($discussion->participants)->firstWhere('id', $currentUserId);

            // Vérifier que l'utilisateur actuel est un administrateur
            if (!$currentUser || !$currentUser['isAdmin']) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Seuls les administrateurs peuvent nommer un autre administrateur.',
                ], 403);
            }

            $targetUserId = $request->userId;
            $targetUser = collect($discussion->participants)->firstWhere('id', $targetUserId);

            // Vérifier que l'utilisateur cible appartient à la discussion
            if (!$targetUser) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'L\'utilisateur doit appartenir à la discussion.',
                ], 403);
            }

            // Nommer l'utilisateur comme administrateur
            $updatedParticipants = collect($discussion->participants)->map(function ($participant) use ($targetUserId) {
                if ($participant['id'] === $targetUserId) {
                    $participant['isAdmin'] = true; // Nommer comme administrateur
                }
                return $participant;
            })->toArray();

            $discussion->participants = $updatedParticipants;
            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Utilisateur nommé administrateur avec succès.',
                'data' => $discussion,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la nomination de l\'administrateur.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Archiver une discussion.
    public function archiveDiscussion(Request $request, Discussion $discussion)
    {
        try {
            $currentUserId = auth()->id();

            // Vérifier si l'utilisateur appartient à la discussion
            $participantKey = collect($discussion->participants)->search(function ($participant) use ($currentUserId) {
                return $participant['id'] === $currentUserId;
            });

            if ($participantKey === false) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Vous ne faites pas partie de cette discussion.',
                ], 403);
            }

            // Mettre à jour la propriété isArchived à true pour le participant
            $participants = $discussion->participants;
            $participants[$participantKey]['isArchived'] = true;

            // Sauvegarder la mise à jour
            $discussion->participants = $participants;
            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussion archivée avec succès.',
                'data' => $discussion,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de l\'archivage de la discussion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Lister les discussions non archivées. Enlever le $request
    public function listUnarchivedDiscussions()
    {
        try {
            
            $currentUserId = auth()->id();

            // Récupérer les discussions où l'utilisateur est participant et non archivé
            $discussions = Discussion::where('participants', 'elemMatch', [
                'id' => $currentUserId,
                'isArchived' => false,
            ])->get();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussions non archivées récupérées avec succès.',
                'data' => $discussions,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la récupération des discussions non archivées.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Lister les discussions archivées. 
    public function listArchivedDiscussions()
    {
        try {
            $currentUserId = auth()->id();

            // Récupérer les discussions où l'utilisateur est participant et archivé
            $discussions = Discussion::where('participants', 'elemMatch', [
                'id' => $currentUserId,
                'isArchived' => true,
            ])->get();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussions archivées récupérées avec succès.',
                'data' => $discussions,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la récupération des discussions archivées.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Mettre en silencieux une discussion.
    public function muteDiscussion(Discussion $discussion)
    {
        try {
            $currentUserId = auth()->id();

            // Vérifier si l'utilisateur est un participant de la discussion
            $participants = collect($discussion->participants);
            $participantIndex = $participants->search(fn($participant) => $participant['id'] === $currentUserId);

            if ($participantIndex === false) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Vous ne faites pas partie de cette discussion.',
                ], 403);
            }

            // Modifier la collection en recréant le tableau des participants
            $updatedParticipants = $participants->map(function ($participant, $index) use ($participantIndex) {
                if ($index === $participantIndex) {
                    $participant['isSilent'] = true;
                }
                return $participant;
            });

            // Sauvegarder la modification dans la base de données
            $discussion->participants = $updatedParticipants->toArray();
            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussion mise en silencieux avec succès.',
                'data' => $discussion,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la mise en silencieux de la discussion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Supprimer une discussion. 
    public function deleteDiscussion(Request $request, Discussion $discussion)
    {
        try {
            $currentUserId = auth()->id();

            // Vérifier si l'utilisateur est un participant de la discussion
            $participants = collect($discussion->participants);
            $participantIndex = $participants->search(fn($participant) => $participant['id'] === $currentUserId);

            if ($participantIndex === false) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'Vous ne faites pas partie de cette discussion.',
                ], 403);
            }

            // Si l'utilisateur est le créateur de la discussion
            if ($discussion->createdBy === $currentUserId) {
                // Supprimer la discussion pour tout le monde
                $discussion->delete();

                return response()->json([
                    'status_code' => 200,
                    'message' => 'Discussion supprimée avec succès pour tous les participants.',
                ], 200);
            }

            // Sinon, marquer la discussion comme supprimée uniquement pour cet utilisateur
            $updatedParticipants = $participants->map(function ($participant, $index) use ($participantIndex) {
                if ($index === $participantIndex) {
                    $participant['isDelected'] = true;
                }
                return $participant;
            });

            // Réaffecter les participants modifiés au modèle
            $discussion->participants = $updatedParticipants->toArray();
            $discussion->save();

            return response()->json([
                'status_code' => 200,
                'message' => 'Discussion supprimée avec succès pour l\'utilisateur.',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Une erreur est survenue lors de la suppression de la discussion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
