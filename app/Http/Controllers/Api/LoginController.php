<?php
namespace App\Http\Controllers\Api;
use Exception;
//use App\Models\Article;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
//use \Kreait\Firebase\Contract\Messaging;
use Illuminate\Support\Facades\Validator;
//use Kreait\Firebase\Messaging\CloudMessage;
//use Kreait\Firebase\Messaging\ApnsConfig;
use Error;
use Kreait\Firebase\Messaging\CloudMessage;

class LoginController extends Controller
{

     function generateUniqueToken() {
        do {
            $token = md5(uniqid() . rand(10000, 99999));
            $tokenExists = DB::table('users')->where('token', $token)->exists();
        } while ($tokenExists);

        return $token;
    }


    public function login(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required',
            'name' => 'required',
            'type' => 'required',
            'open_id' => 'required',
            'email' => 'max:50',
        ]);
        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }
        try {
            //1:google，2:mail
            $validated = $validator->validated();

            $map = [];
            $map["type"] = $validated["type"];
            $map["open_id"] = $validated["open_id"];
            $email = $validated["email"];

            // Vérifier si l'utilisateur est banni
            $bannedUser = DB::table("ban")->where('email', $email)->first();
            if ($bannedUser) {
                return ["code" => -1, "data" => "", "msg" => "Utilisateur banni"];
            }

            $res = DB::table("users")->select("avatar", "name", "type", "token", "email")->where($map)->first();
            if (empty($res)) {
                do {
                    $validated["token"] = md5(uniqid() . rand(10000, 99999));
                    $token = $validated["token"] ;
                    $tokenExists = DB::table('users')->where('token', $token)->exists();
                } while ($tokenExists);

                $validated["created_at"] = Carbon::now();
                $validated["expire_date"] = Carbon::now()->addDays(30);
                $user_id = DB::table("users")->insertGetId($validated);
                $user_res = DB::table("users")->select("avatar", "name", "type", "token", "email")->where("id", "=", $user_id)->first();
                return ["code" => 0, "data" => $user_res, "msg" => "success"];
            }

            $expire_date = Carbon::now()->addDays(30);
            DB::table("users")->where($map)->update(["expire_date" => $expire_date]);

            return ["code" => 0, "data" => $res, "msg" => "success"];

        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function wallet(Request $request): array
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            $token = $request->input('token');

            // Recherche du portefeuille correspondant au jeton donné
            $wallet = DB::table('wallet')->where('userToken', $token)->first();

            // Si un portefeuille est trouvé
            if ($wallet) {
                return ["code" => 0, "data" => $wallet, "msg" => "Success"];
            } else {
                // Si aucun portefeuille n'est trouvé, insérer un nouveau portefeuille avec des valeurs par défaut
                $newWalletId = DB::table('wallet')->insertGetId([
                    'key' => 0, // Valeur par défaut pour 'key'
                    'money' => 0, // Valeur par défaut pour 'money'
                    'energie' => 4, // Valeur par défaut pour 'energie'
                    "heart" => 2,
                    'userToken' => $token,
                ]);

                // Récupérer le nouveau portefeuille créé
                $newWallet = DB::table('wallet')->find($newWalletId);

                return ["code" => 0, "data" => $newWallet, "msg" => "Wallet created successfully"];
            }
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

        public function create_game()
    {
        DB::table('game')->insert([
            'name' => 'Flappy Birds',
            'image' => 'uploads/flappy_birds_bg.jpg',
            'startTime' => Carbon::now(),
            'endTime' => Carbon::now()->addDays(4),
            'premium' => true, // Laravel will convert this to 1
            'url' => 'https://bobakamate.github.io/fruit-ninja-webgl/'
        ]);
        DB::table('game')->insert([
            'name' => 'Flappy Birds',
            'image' => 'uploads/flappy_birds_bg.jpg',
            'startTime' => Carbon::now(),
            'endTime' => Carbon::now()->addDays(4),
            'premium' => false, // Laravel will convert this to 1
            'url' => 'https://bobakamate.github.io/fruit-ninja-webgl/'
        ]);


        return response()->json(['message' => 'Game created successfully']);
    }

    public function get_games(Request $request): array
    {
        // Example: Adding validation (adjust as needed)
        $validator = Validator::make($request->all(), [
            'filter' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }

        try {
            // Récupération des jeux depuis la base de données avec le filtre sur la date
            $games = DB::table('game')
                ->where('endTime', '>=', Carbon::now('UTC')->toDateTimeString())
               ->orderBy("id","desc") ->get();

            if ($games->isEmpty()) {
                // Aucun jeu disponible, retourner un jeu fictif
                $noGame = [
                    'id' => null,
                    'name' => 'No Game',
                    'image' => 'uploads/no_game.jpg',
                    'startTime' => Carbon::now('UTC')->toDateTimeString(),
                    'endTime' => Carbon::now('UTC')->toDateTimeString(),
                    'premium' => 0,
                    'url' => 'url',
                    'cashPrise' => 0
                ];

                return ["code" => 0, "data" => [$noGame], "msg" => "No games available, returning default game"];
            }

            return ["code" => 0, "data" => $games, "msg" => "success"];
        } catch (\Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function save_wallet(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
                'key' => 'required|integer',
                'money' => 'required',
                'energie' => 'required|integer',
                'heart' => 'required|integer',
            ]);

            // Vérifie si la validation échoue
            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupère les données du portefeuille depuis la requête
            $token = $request->input('userToken');
            $key = $request->input('key');
            $money = $request->input('money');
            $energie = $request->input('energie');
            $heart = $request->input('heart');

            // Recherche du portefeuille correspondant au jeton donné
            $wallet = DB::table('wallet')->where('userToken', $token)->first();

            if ($wallet) {
                // Si un portefeuille est trouvé, mettez à jour les informations

                if($wallet->money < $money ){
                    $money = $wallet->money;
                }
                DB::table('wallet')
                    ->where('userToken', $token)
                    ->update([
                        'key' => $key,
                        'money' => $money,
                        'energie' => $energie,
                        'heart' => $heart,
                    ]);

                // Récupérer le portefeuille mis à jour
                $updatedWallet = DB::table('wallet')->where('userToken', $token)->first();

                return ["code" => 0, "data" => $updatedWallet, "msg" => "Wallet updated successfully"];
            } else {
                // Si aucun portefeuille n'est trouvé, insérer un nouveau portefeuille
                $newWalletId = DB::table('wallet')->insertGetId([
                    'key' => $key,
                    'money' => 3.0,
                    'energie' => $energie,
                    'heart' => $heart,
                    'userToken' => $token,
                ]);

                // Récupérer le nouveau portefeuille créé
                $newWallet = DB::table('wallet')->find($newWalletId);

                return ["code" => 0, "data" => $newWallet, "msg" => "Wallet created successfully"];
            }
        } catch (Exception $e) {
            // Gère les exceptions en retournant un message d'erreur
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function creates_game(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'startTime' => 'required|date',
                'endTime' => 'required|date',
                'premium' => 'required',
                'image' => 'required|string',
                'url' => 'required|string',
                "cashPrise" => "required"

             ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => false, "msg" => $validator->errors()->first()];
            }

            // Récupérer les données du jeu depuis la requête
            $gameData = $request->only(['name', 'startTime', 'endTime', 'premium', 'image', 'url',"cashPrise"]);

            // Insérer un nouveau jeu dans la table 'game'
            $gameId = DB::table('game')->insertGetId($gameData);

            // Inscrire automatiquement tous les utilisateurs au nouveau jeu


            // Récupérer le jeu créé
            $game = DB::table('game')->find($gameId);

            return ["code" => 0, "data" => true, "msg" => "Game created and all users are registered"];
        } catch (\Exception $e) {
            return ["code" => -1, "data" => false, "msg" => $e->getMessage()];
        }
    }
    public function register_to_gamers(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'gameId' => 'required|integer',
                'userToken' => 'required|string',
                'score' => 'required|integer',
                'isLock' => 'required',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données du joueur depuis la requête
            $gameId = $request->input('gameId');
            $userToken = $request->input('userToken');
            $score = $request->input('score');
            $isLock = $request->input('isLock');

            // Vérifier si le tournoi est toujours en cours
            $game = DB::table('game')->where('id', $gameId)->first();
            if (!$game) {
                return ["code" => -1, "data" => "", "msg" => "Le jeu spécifié n'existe pas"];
            }

            $now = Carbon::now('UTC')->addDays(2)->toDateTimeString();
            if (Carbon::parse($now)->greaterThan(Carbon::parse($game->endTime))) {
                return ["code" => -1, "data" => "", "msg" => "Le tournoi est déjà terminé"];
            }

            // Vérifier si le joueur est déjà enregistré pour ce jeu
            $existingEntry = DB::table('gamers')
                ->where('gameId', $gameId)
                ->where('userToken', $userToken)
                ->first();

            if ($existingEntry) {
                return ["code" => 0, "data" => $existingEntry, "msg" => "L'utilisateur est déjà enregistré pour ce jeu"];
            }

            // Insérer le joueur dans la table des joueurs
            DB::table('gamers')->insert([
                'gameId' => $gameId,
                'userToken' => $userToken,
                'score' => $score,
                'isLock' => $isLock,
            ]);

            return ["code" => 0, "data" => "", "msg" => "L'utilisateur a été enregistré avec succès pour ce jeu"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function gamers_premium(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'gameId' => 'required|integer',
                'userToken' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données de la requête
            $gameId = $request->input('gameId');
            $userToken = $request->input('userToken');

            // Vérifier si l'utilisateur est inscrit au jeu
            $existingEntry = DB::table('gamers')
                ->where('gameId', $gameId)
                ->where('userToken', $userToken)
                ->first();

            if (!$existingEntry) {
                // Si l'utilisateur n'est pas inscrit, l'inscrire et définir isLock à false
                DB::table('gamers')->insert([
                    'gameId' => $gameId,
                    'userToken' => $userToken,
                    'score' => 0,
                    'isLock' => 0,
                ]);

                return ["code" => 0, "data" => "", "msg" => "L'utilisateur a été inscrit avec succès et le jeu est désormais disponible."];
            }

            // Si l'utilisateur est déjà inscrit, mettre isLock à false
            DB::table('gamers')
                ->where('gameId', $gameId)
                ->where('userToken', $userToken)
                ->update(['isLock' => 0]);

            return ["code" => 0, "data" => "", "msg" => "Le jeu est désormais disponible pour l'utilisateur."];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }



    public function get_gamers(Request $request): array
    {
        try {
            // Validation des paramètres de la requête


            // Récupérer le token de l'utilisateur depuis la requête


            // Récupérer l'utilisateur actuel


            // Vérifier si l'utilisateur actuel existe

            // Récupérer tous les jeux disponibles
            $gameIds = DB::table('gamers')
                ->distinct()
                ->pluck('gameId');

            $allGamers = [];

            // Pour chaque jeu, récupérer les 100 meilleurs joueurs classés par score
            foreach ($gameIds as $gameId) {
                $topGamers = DB::table('gamers')
                    ->join('users', 'gamers.userToken', '=', 'users.token')
                    ->where('gamers.gameId', $gameId)
                    ->orderBy('score', 'desc')
                    ->limit(100)
                    ->select('users.name', 'gamers.gameId', 'users.avatar', 'gamers.score')
                    ->get();

                // Ajouter l'utilisateur actuel à la liste


                $allGamers[] = $topGamers;
            }

            return ["code" => 0, "data" => $allGamers, "msg" => "Liste des 100 meilleurs joueurs récupérée avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function get_gamer_user(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le token de l'utilisateur depuis la requête
            $userToken = $request->input('userToken');

            // Récupérer l'utilisateur actuel
            $currentUser = DB::table('users')->where('token', $userToken)->first();

            // Vérifier si l'utilisateur actuel existe
            if (!$currentUser) {
                return ["code" => -1, "data" => "", "msg" => "Utilisateur non trouvé"];
            }

            // Récupérer les détails du joueur depuis la table 'gamers'
            $gamerDetails = DB::table('gamers')
                ->where('userToken', $userToken)
                ->select('gameId', 'score', 'isLock','userToken')
                ->get();

            // Ajouter les détails de l'utilisateur aux détails du joueur


            return ["code" => 0, "data" => $gamerDetails, "msg" => "Détails du joueur récupérés avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function update_score(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'gameId' => 'required|integer',
                'userToken' => 'required|string',
                'score' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données de la requête
            $gameId = $request->input('gameId');
            $userToken = $request->input('userToken');
            $newScore = $request->input('score');

            // Vérifier si l'utilisateur est inscrit au jeu
            $existingEntry = DB::table('gamers')
                ->where('gameId', $gameId)
                ->where('userToken', $userToken)
                ->first();

            if ($existingEntry) {
                // Si l'utilisateur est inscrit, mettre à jour le score
                DB::table('gamers')
                    ->where('gameId', $gameId)
                    ->where('userToken', $userToken)
                    ->update(['score' => $newScore]);

                return ["code" => 0, "data" => "", "msg" => "Le score de l'utilisateur a été mis à jour avec succès."];
            } else {
                return ["code" => -1, "data" => "", "msg" => "L'utilisateur n'est pas inscrit au jeu."];
            }
        } catch (\Exception $e) {
            return ["code" => -1, "data" => "", "msg" => "Une erreur est survenue : " . $e->getMessage()];
        }
    }
    public function update_date(Request $request)
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'gameId' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données de la requête
            $gameId = $request->input('gameId');

            // Calculer la nouvelle date de fin (now + 4 jours)
            $newEndTime = Carbon::now()->addDays(4);

            // Mettre à jour la table game
            $updated = DB::table('game')
                ->where('id', $gameId)
                ->update(['endTime' => $newEndTime]);

            if ($updated) {
                return ["code" => 0, "data" => "", "msg" => "La date de fin du jeu a été mise à jour avec succès."];
            } else {
                return ["code" => -1, "data" => "", "msg" => "La mise à jour a échoué ou aucun enregistrement n'a été trouvé."];
            }
        } catch (\Exception $e) {
            return ["code" => -1, "data" => "", "msg" => "Une erreur est survenue : " . $e->getMessage()];
        }
    }



    public function save_user(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'avatar' => 'string|nullable', // Avatar est une chaîne de caractères (optionnelle)
                'name' => 'string|nullable', // Name est une chaîne de caractères (optionnelle)
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données de la requête
            $token = $request->input('token');
            $avatar = $request->input('avatar');
            $name = $request->input('name');

            // Vérifier si l'utilisateur existe
            $existingUser = DB::table('users')->where('token', $token)->first();

            if ($existingUser) {
                // Si l'utilisateur existe, mettre à jour ses informations
                $updateData = [];
                if ($avatar !== null) {
                    $updateData['avatar'] = $avatar;
                }
                if ($name !== null) {
                    $updateData['name'] = $name;
                }

                DB::table('users')->where('token', $token)->update($updateData);

                return ["code" => 0, "data" => "", "msg" => "Informations de l'utilisateur mises à jour avec succès"];
            } else {
                // Si l'utilisateur n'existe pas, retourner une erreur
                return ["code" => -1, "data" => "", "msg" => "Utilisateur non trouvé"];
            }
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    public function get_user_info(Request $request): array
    {
        try {
            // Validation du token
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le token de la requête
            $token = $request->input('token');

            // Rechercher l'utilisateur correspondant au token
            $user = DB::table('users')->where('token', $token)->first();

            if ($user) {
                // Si l'utilisateur est trouvé, retourner ses informations
                return ["code" => 0, "data" => $user, "msg" => "Informations de l'utilisateur récupérées avec succès"];
            } else {
                // Si l'utilisateur n'est pas trouvé, retourner une erreur
                return ["code" => -1, "data" => "", "msg" => "Utilisateur non trouvé"];
            }
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function cashOut(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le token de l'utilisateur depuis la requête
            $userToken = $request->input('userToken');
            $amount = $request->input('amount');

            // Vérifier si l'utilisateur actuel existe
            $currentUser = DB::table('users')->where('token', $userToken)->first();

            if (!$currentUser) {
                return ["code" => -1, "data" => "", "msg" => "Utilisateur non trouvé"];
            }

            // Vérifier si l'utilisateur a suffisamment d'argent pour retirer
            $wallet = DB::table('wallet')->where('userToken', $userToken)->first();

            if (!$wallet) {
                return ["code" => -1, "data" => "", "msg" => "Portefeuille non trouvé pour cet utilisateur"];
            }

            if ($wallet->money < $amount) {
                return ["code" => -1, "data" => "", "msg" => "Solde insuffisant pour effectuer ce retrait"];
            }

            // Créer une demande de paiement
            DB::table('payment_out')->insert([
                'userToken' => $userToken,
                'amount' => $amount,
                'isvalidate' => false, // Par défaut, la demande n'est pas validée
            ]);

            // Mettre à jour le solde de l'utilisateur
            DB::table('wallet')->where('userToken', $userToken)->decrement('money', $amount);

            return ["code" => 0, "data" => "", "msg" => "Demande de paiement effectuée avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    public function get_pay_history(Request $request): array
    {
        try {
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => [], "msg" => $validator->errors()->first()];
            }

            $userToken = $request->input("userToken");

            // Récupérer les paiements de payment_out pour l'utilisateur donné
            $paymentsOut = DB::table('payement_out')
                ->where('userToken', $userToken)
                ->orderBy('id', 'desc') // Tri par ordre décroissant sur la colonne 'id'
                ->get();


            // Récupérer les paiements de payment_game pour l'utilisateur donné


            return [
                "code" => 0,
                "data" => [
                    "payment_out" => $paymentsOut,

                ],
                "msg" => "Données de paiement récupérées avec succès pour l'utilisateur $userToken"
            ];
        } catch (Exception $e) {
            return ["code" => -1, "data" => [], "msg" => $e->getMessage()];
        }
    }
    public function get_pay_out(Request $request): array
    {
        try {
            // Validation des données de la requête
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
                'amount' => 'required|numeric',
                'email' => 'nullable|email',
                'name' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => [], "msg" => $validator->errors()->first()];
            }

            // Récupérer les données validées
            $userToken = $request->input('userToken');
            $amount = $request->input('amount');
            $email = $request->input('email');
            $name = $request->input('name');

            // Insérer le nouvel enregistrement dans la table payement_out
            $id = DB::table('payement_out')->insertGetId([
                'userToken' => $userToken,
                'amount' => $amount,
                'isvalidate' => 0, // Par défaut, isvalidate est 0
                'email' => $email,
                'name' => $name,
                'created_at' => now(), // Si vous avez un champ created_at
                'updated_at' => now(), // Si vous avez un champ updated_at
            ]);
            DB::table('wallet')
                ->where('userToken', $userToken)
                ->decrement('money', $amount);

            return [
                "code" => 0,
                "data" => [
                    "id" => $id
                ],
                "msg" => "Demande de paiement créée avec succès"
            ];
        } catch (Exception $e) {
            return ["code" => -1, "data" => [], "msg" => $e->getMessage()];
        }
    }
    public function upload_image(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Exemple de validation pour le fichier image
                'userName' => 'required|string',
                'userToken' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le fichier envoyé dans la requête
            $file = $request->file('file');

            // Récupérer les informations utilisateur
            $userName = $request->input('userName');
            $userToken = $request->input('userToken');

            // Générer un nom unique pour le fichier
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Déplacer le fichier vers le dossier de stockage
            $file->move(public_path('uploads'), $fileName);

            // Récupérer l'ancienne image de l'utilisateur
            $user = DB::table('users')->where('token', $userToken)->first();
            $oldFileName = basename($user->avatar);

            // Supprimer l'ancienne image si ce n'est pas une image par défaut
            $defaultImages = [
                'default_image_from_bdd_1.png',
                'default_image_from_bdd_2.png',
                'default_image_from_bdd_3.png',
                'default_image_from_bdd_4.png',
                'default_image_from_bdd_5.png',
                'default_image_from_bdd_6.png',
                'default_image_from_bdd_7.png',
                'default_image_from_bdd_8.png',
                'default_image_from_bdd_9.png',
                'default_image_from_bdd_10.png',
                'default_image_from_bdd_11.png',
                'default_image_from_bdd_12.png',
                'default_image_from_bdd_13.png',
                'default_image_from_bdd_14.png',
                'default_image_from_bdd_15.png',
                'default_image_from_bdd_16.png',
                'default_image_from_bdd_17.png',
                'default_image_from_bdd_18.png',
                'default_image_from_bdd_19.png',
                'default_image_from_bdd_20.png',
                'default_image_from_bdd_21.png',
                'default_image_from_bdd_22.png',
                'default_image_from_bdd_23.png',
                'default_image_from_bdd_24.png',
                'default_image_from_bdd_25.png',
                'default_image_from_bdd_26.png',
                'default_image_from_bdd_27.png',
                'default_image_from_bdd_28.png',
                'default_image_from_bdd_29.png',
                'default_image_from_bdd_30.png',
                'default_image_from_bdd_31.png',
                'default_image_from_bdd_32.png',
                'default_image_from_bdd_33.png',
                'default_image_from_bdd_34.png',
                'default_image_from_bdd_35.png',
                'default_image_from_bdd_36.png',
                'default_image_from_bdd_37.png',
                'default_image_from_bdd_38.png',
                'default_image_from_bdd_39.png',
                'default_image_from_bdd_40.png',
                'default_image_from_bdd_41.png',
                'default_image_from_bdd_42.png',
                'default_image_from_bdd_43.png',
                'default_image_from_bdd_44.png',
                'default_image_from_bdd_45.png',
                'default_image_from_bdd_46.png',
                'default_image_from_bdd_47.png',
                'default_image_from_bdd_48.png',
                'default_image_from_bdd_49.png',
                'default_image_from_bdd_50.png',
                'default_image_from_bdd_51.png',
                'default_image_from_bdd_52.png',
                'default_image_from_bdd_53.png',
                'default_image_from_bdd_54.png',
                'default_image_from_bdd_55.png',
                'default_image_from_bdd_56.png',
                'default_image_from_bdd_57.png',
                'default_image_from_bdd_58.png',
                'default_image_from_bdd_59.png',
                'default_image_from_bdd_60.png',
                'default_image_from_bdd_61.png',
                'default_image_from_bdd_62.png',
                'default_image_from_bdd_63.png',
                'default_image_from_bdd_64.png',
                'default_image_from_bdd_65.png',
                'default_image_from_bdd_66.png',
                'default_image_from_bdd_67.png',
                'default_image_from_bdd_68.png',
                'default_image_from_bdd_69.png',
                'default_image_from_bdd_70.png',
                'default_image_from_bdd_71.png',
                'default_image_from_bdd_72.png',
                'default_image_from_bdd_73.png',
                'default_image_from_bdd_74.png',
                'default_image_from_bdd_75.png',
                'default_image_from_bdd_76.png',
                'default_image_from_bdd_77.png',
                'default_image_from_bdd_78.png',
                'default_image_from_bdd_79.png',
                'default_image_from_bdd_80.png',
                'default_image_from_bdd_81.png',
                'default_image_from_bdd_82.png',
                'default_image_from_bdd_83.png',
                'default_image_from_bdd_84.png',
                'default_image_from_bdd_85.png',
                'default_image_from_bdd_86.png',
                'default_image_from_bdd_87.png',
                'default_image_from_bdd_88.png',
                'default_image_from_bdd_89.png',
                'default_image_from_bdd_90.png',
                // Ajoutez ici toutes les autres images par défaut
            ];

            if (!in_array($oldFileName, $defaultImages)) {
                $oldFilePath = public_path('uploads') . '/' . $oldFileName;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // Mettre à jour les informations de l'utilisateur dans la base de données
            DB::table('users')->where('token', $userToken)->update(['name' => $userName, 'avatar' => $fileName]);

            // Retourner une réponse avec les détails du fichier téléchargé
            return ["code" => 0, "data" => ['file_name' => $fileName], "msg" => "File uploaded successfully"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function delete_account(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'userToken' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le token utilisateur
            $userToken = $request->input('userToken');

            // Récupérer les informations de l'utilisateur
            $user = DB::table('users')->where('token', $userToken)->first();

            if (!$user) {
                return ["code" => -1, "data" => "", "msg" => "User not found"];
            }

            // Supprimer l'avatar de l'utilisateur s'il n'est pas une image par défaut
            $defaultImages = [
                'default_image_from_bdd_1.png',
                'default_image_from_bdd_2.png',
                'default_image_from_bdd_3.png',
                'default_image_from_bdd_4.png',
                'default_image_from_bdd_5.png',
                'default_image_from_bdd_6.png',
                'default_image_from_bdd_7.png',
                'default_image_from_bdd_8.png',
                'default_image_from_bdd_9.png',
                'default_image_from_bdd_10.png',
                'default_image_from_bdd_11.png',
                'default_image_from_bdd_12.png',
                'default_image_from_bdd_13.png',
                'default_image_from_bdd_14.png',
                'default_image_from_bdd_15.png',
                'default_image_from_bdd_16.png',
                'default_image_from_bdd_17.png',
                'default_image_from_bdd_18.png',
                'default_image_from_bdd_19.png',
                'default_image_from_bdd_20.png',
                'default_image_from_bdd_21.png',
                'default_image_from_bdd_22.png',
                'default_image_from_bdd_23.png',
                'default_image_from_bdd_24.png',
                'default_image_from_bdd_25.png',
                'default_image_from_bdd_26.png',
                'default_image_from_bdd_27.png',
                'default_image_from_bdd_28.png',
                'default_image_from_bdd_29.png',
                'default_image_from_bdd_30.png',
                'default_image_from_bdd_31.png',
                'default_image_from_bdd_32.png',
                'default_image_from_bdd_33.png',
                'default_image_from_bdd_34.png',
                'default_image_from_bdd_35.png',
                'default_image_from_bdd_36.png',
                'default_image_from_bdd_37.png',
                'default_image_from_bdd_38.png',
                'default_image_from_bdd_39.png',
                'default_image_from_bdd_40.png',
                'default_image_from_bdd_41.png',
                'default_image_from_bdd_42.png',
                'default_image_from_bdd_43.png',
                'default_image_from_bdd_44.png',
                'default_image_from_bdd_45.png',
                'default_image_from_bdd_46.png',
                'default_image_from_bdd_47.png',
                'default_image_from_bdd_48.png',
                'default_image_from_bdd_49.png',
                'default_image_from_bdd_50.png',
                'default_image_from_bdd_51.png',
                'default_image_from_bdd_52.png',
                'default_image_from_bdd_53.png',
                'default_image_from_bdd_54.png',
                'default_image_from_bdd_55.png',
                'default_image_from_bdd_56.png',
                'default_image_from_bdd_57.png',
                'default_image_from_bdd_58.png',
                'default_image_from_bdd_59.png',
                'default_image_from_bdd_60.png',
                'default_image_from_bdd_61.png',
                'default_image_from_bdd_62.png',
                'default_image_from_bdd_63.png',
                'default_image_from_bdd_64.png',
                'default_image_from_bdd_65.png',
                'default_image_from_bdd_66.png',
                'default_image_from_bdd_67.png',
                'default_image_from_bdd_68.png',
                'default_image_from_bdd_69.png',
                'default_image_from_bdd_70.png',
                'default_image_from_bdd_71.png',
                'default_image_from_bdd_72.png',
                'default_image_from_bdd_73.png',
                'default_image_from_bdd_74.png',
                'default_image_from_bdd_75.png',
                'default_image_from_bdd_76.png',
                'default_image_from_bdd_77.png',
                'default_image_from_bdd_78.png',
                'default_image_from_bdd_79.png',
                'default_image_from_bdd_80.png',
                'default_image_from_bdd_81.png',
                'default_image_from_bdd_82.png',
                'default_image_from_bdd_83.png',
                'default_image_from_bdd_84.png',
                'default_image_from_bdd_85.png',
                'default_image_from_bdd_86.png',
                'default_image_from_bdd_87.png',
                'default_image_from_bdd_88.png',
                'default_image_from_bdd_89.png',
                'default_image_from_bdd_90.png',
                // Ajoutez toutes les autres images par défaut ici
            ];

            $oldFileName = basename($user->avatar);
            if (!in_array($oldFileName, $defaultImages)) {
                $oldFilePath = public_path('uploads') . '/' . $oldFileName;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // Supprimer les enregistrements dans la table wallet
            DB::table('wallet')->where('userToken', $userToken)->delete();

            // Supprimer les enregistrements dans la table gamers
            DB::table('gamers')->where('userToken', $userToken)->delete();
            DB::table('rate')->where('userToken', $userToken)->delete();
            DB::table('payement_game')->where('userToken', $userToken)->delete();
            DB::table('payement_out')->where('userToken', $userToken)->delete();

            // Supprimer l'utilisateur de la table users
            DB::table('users')->where('token', $userToken)->delete();


            return ["code" => 0, "data" => "", "msg" => "User account deleted successfully"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function rate_app(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'userToken' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }

        try {
            $validated = $validator->validated();
            $userToken = $validated['userToken'];

            // Vérifier si l'utilisateur a déjà noté l'application
            $existingRate = DB::table("rate")->where('userToken', $userToken)->first();
            if ($existingRate) {

                return ["code" => -1, "data" => "", "msg" => "L'utilisateur a déjà noté l'application"];
            }

            // Enregistrer l'utilisateur dans la table rate
            DB::table("rate")->insert([
                'userToken' => $userToken,
                'isRate' => true,
            ]);

            return ["code" => 0, "data" => "", "msg" => "L'utilisateur a noté l'application avec succès"];

        } catch (Exception $e) {
            return ["code" => -2, "data" => "", "msg" => $e->getMessage()];
        }
    }


    function get_now_time_utc()
    {

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => Carbon::now('UTC')->toDateTimeString()
        ];
    }

    public function check_update_availability(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'version' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer la version depuis la requête
            $version = $request->input('version');

            // Vérifier si une mise à jour est disponible
            $update = DB::table('mise_a_jour')->where('version', $version)->first();

            if (!$update) {
                return ["code" => -1, "data" => "", "msg" => "Version non trouvée"];
            }

            $isUpdateAvailable = $update->updateAvailable;

            return ["code" => 0, "data" => $isUpdateAvailable, "msg" => "Disponibilité de la mise à jour vérifiée avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    /*public function get_profile(Request $request): array
    {
         $token = $request->user_token;
         $res = DB::table("users")->select("avatar","name","description","online")->where("token","=",$token)->first();

         return ["code" => 0, "data" => $res, "msg" => "success"];
    }

    public function update_profile(Request $request): array
    {
      $token = $request->user_token;

      $validator = Validator::make($request->all(), [
        'online' => 'required',
        'description' => 'required',
        'name' => 'required',
        'avatar' => 'required',
      ]);
      if ($validator->fails()) {
        return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
      }
      try {
        // ...

        $validated = $validator->validated();

        $map=[];
        $map["token"] = $token;

        $res = DB::table("users")->where($map)->first();
        if(!empty($res)){

          $validated["updated_at"] = Carbon::now();
          DB::table("users")->where($map)->update($validated);

          return ["code" => 0, "data" => "", "msg" => "success"];
        }

        return ["code" => -1, "data" => "", "msg" => "error"];

      } catch (Exception $e) {
        return ["code" => -1, "data" => "", "msg" => "error"];
      }
    }

    public function bind_fcmtoken(Request $request): array
    {
        $token = $request->user_token;
        $fcmtoken = $request->input("fcmtoken");

        if(empty($fcmtoken)){
             return ["code" => -1, "data" => "", "msg" => "error"];
        }

        DB::table("users")->where("token","=",$token)->update(["fcmtoken"=>$fcmtoken]);

        return ["code" => 0, "data" => "", "msg" => "success"];
    }
    public function contact(Request $request): array
    {
        $token = $request->user_token;
        $res =DB::table("users")->select("avatar","name","description","online","token")->where("token","!=",$token)->get();
        return ["code" => 0, "data" => $res, "msg" => "success"];

    }
    public function send_notice(Request $request): array
    {
        $user_token = $request->user_token;
        $user_avatar = $request->user_avatar;
        $user_name = $request->user_name;
        $to_token = $request->input("to_token");
        $to_name = $request->input("to_name");
        $to_avatar = $request->input("to_avatar");
        $call_type = $request->input("call_type");
        ////1. voice 2. video 3. text, 4.cancel
        $res =DB::table("users")->select("avatar","name","token","fcmtoken")->where("token","=",$to_token)->first();
        if(empty($res)){
            return ["code" => -1, "data" => "", "msg" => "user not exist"];
        }

        $deviceToken = $res->fcmtoken;
          try {

          if(!empty($deviceToken)){

          $messaging = app('firebase.messaging');
          if($call_type=="cancel"){
             $message = CloudMessage::fromArray([
           'token' => $deviceToken, // optional
           'data' => [
              'token' => $user_token,
              'avatar' => $user_avatar,
              'name' => $user_name,
              'call_type' => $call_type,
          ]]);

           $messaging->send($message);

          }else if($call_type=="voice"){

          $message = CloudMessage::fromArray([
           'token' => $deviceToken, // optional
          'data' => [
              'token' => $user_token,
              'avatar' => $user_avatar,
              'name' => $user_name,
              'call_type' => $call_type,
          ],
          'android' => [
              "priority" => "high",
              "notification" => [
                  "channel_id"=> "com.bouba.mychatapp.call",
                  'title' => "Appel vocal de ".$user_name,
                  'body' => "Veuillez cliquer pour répondre",
                  ]
              ],
              'apns' => [
              // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
              'headers' => [
                  'apns-priority' => '10',
              ],
              'payload' => [
                  'aps' => [
                      'alert' => [
                         'title' => "Appel vocal de ".$user_name,
                         'body' => "Veuillez cliquer pour répondre",
                      ],
                      'badge' => 1,
                      'sound' =>'task_cancel.caf'
                  ],
              ],
          ],
          ]);

         $messaging->send($message);

          }else if($call_type=="video"){
         $message = CloudMessage::fromArray([
           'token' => $deviceToken, // optional
          'data' => [
              'token' => $user_token,
              'avatar' => $user_avatar,
              'name' => $user_name,
              'call_type' => $call_type,
          ],
          'android' => [
              "priority" => "high",
              "notification" => [
                  "channel_id"=> "com.bouba.mychatapp.call",
                  'title' => "Appel vidéo de ".$user_name,
                  'body' => "Veuillez cliquer pour répondre",
                  ]
              ],
              'apns' => [
              // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
              'headers' => [
                  'apns-priority' => '10',
              ],
              'payload' => [
                  'aps' => [
                      'alert' => [
                          'title' => "Appel vidéo de ".$user_name,
                          'body' => "Veuillez cliquer pour répondre",
                      ],
                      'badge' => 1,
                      'sound' =>'task_cancel.caf'
                  ],
              ],
          ],
          ]);

         $messaging->send($message);

           }else if($call_type=="text"){

                $message = CloudMessage::fromArray([
           'token' => $deviceToken, // optional
          'data' => [
              'token' => $user_token,
              'avatar' => $user_avatar,
              'name' => $user_name,
              'call_type' => $call_type,
          ],
          'android' => [
              "priority" => "high",
              "notification" => [
                  "channel_id"=> "com.bouba.mychatapp.message",
                  'title' => "Message de ".$user_name,
                  'body' => "Veuillez cliquer pour répondre",
                  ]
              ],
              'apns' => [
              // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
              'headers' => [
                  'apns-priority' => '10',
              ],
              'payload' => [
                  'aps' => [
                      'alert' => [
                          'title' => "Message de ".$user_name,
                          'body' => "Veuillez cliquer pour répondre",
                      ],
                      'badge' => 1,
                      'sound' =>'ding.caf'
                  ],
              ],
          ],
          ]);

         $messaging->send($message);


           }

          return ["code" => 0, "data" => $to_token, "msg" => "success"];

         }else{
           return ["code" => -1, "data" => "", "msg" => "fcmtoken empty"];
         }


        }catch (\Exception $exception){
            return ["code" => -1, "data" => "", "msg" => $exception->getMessage()];
          }
    }

    /*public function send_notice_test(): void
    {
            $deviceToken = "d9Zbwl67Ro2IgFm0jFoAlt:APA91bEhU_Ve7o6_aWUt3ex1ML_cyWPMO0t5nHBcLCLFpFkeDQa__akuPL6RciGilpOevgdZDA2Zw6Z1JgZ5746eld9R9nvGH_BWyAnNe7B6q_JK38kbbwnboYdtuxMC7MzpiOysuf40";
         $messaging = app('firebase.messaging');
             $message = CloudMessage::fromArray([
           'token' => $deviceToken, // optional
          'data' => [
              'token' => "test",
              'avatar' => "test",
              'name' => "test",
              'call_type' => "test",
          ],
          'android' => [
              "priority" => "high",
              "notification" => [
                  "channel_id"=> "com.dbestech.chatty.message",
                  'title' => "Message made by ",
                  'body' => "Please click to answer the Message",
                  ]
              ],
              'apns' => [
              // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
              'headers' => [
                  'apns-priority' => '10',
              ],
              'payload' => [
                  'aps' => [
                      'alert' => [
                          'title' => "Message made by ",
                          'body' => "Please click to answer the Message",
                      ],
                      'badge' => 1,
                      'sound' =>'ding.caf'
                  ],
              ],
          ],
          ]);

         $messaging->send($message);
    }
  */

  public function upload_photo(Request $request): array
  {

         $file = $request->file('file');

         try {
         $extension = $file->getClientOriginalExtension();

         $fullFileName = uniqid(). '.'. $extension;
         $timedir = date("Ymd");
         $file->storeAs($timedir, $fullFileName,  ['disk' => 'public']);

         $url = env('APP_URL').'/uploads/'.$timedir.'/'.$fullFileName;
       return ["code" => 0, "data" => $url, "msg" => "success"];
     } catch (Exception $e) {
       return ["code" => -1, "data" => "", "msg" => "error"];
    }
  }

}
