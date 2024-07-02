<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Exception;

class AdminFonction extends Controller {
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

            $map=[];
            $map["type"] = $validated["type"];
            $map["open_id"] = $validated["open_id"];

            $res = DB::table("admin")->select("avatar","name","type","token","email")->where($map)->first();
            if(empty($res)){
                $validated["token"] = md5(uniqid().rand(10000,99999));
                $validated["created_at"] = Carbon::now();
                $validated["expire_date"] = Carbon::now()->addDays(30);
                $user_id = DB::table("admin")->insertGetId($validated);
                $user_res = DB::table("admin")->select("avatar","name","type","token","email")->where("id","=",$user_id)->first();
                return ["code" => 0, "data" => $user_res, "msg" => "success"];
            }

            $expire_date = Carbon::now()->addDays(30);
            DB::table("admin")->where($map)->update(["expire_date"=>$expire_date]);

            return ["code" => 0, "data" => $res, "msg" => "success"];

        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e];
        }
    }

    public function get_all_users(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }

        try {
            // Récupérer le token validé
            $token = $validator->validated()['token'];

            // Vérifier si l'utilisateur avec ce token existe dans la table admin
            $admin = DB::table('admin')->where('token', $token)->first();

            if (!$admin) {
                return ["code" => -1, "data" => "", "msg" => "Unauthorized"];
            }

            // Récupérer tous les utilisateurs de la table users
            $users = DB::table('users')->get();

            return ["code" => 0, "data" => $users, "msg" => "success"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    public function get_games_admin(Request $request): array
    {
        // Example: Adding validation (adjust as needed)
        $validator = Validator::make($request->all(), [
            'filter' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }

        try {
            // Fetching games from the database
            $games = DB::table('game')->get();

            return ["code" => 0, "data" => $games, "msg" => "success"];
        } catch (\Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function create_user_admin(Request $request): array
    {
        // Valider les données de la requête
        $validator = Validator::make($request->all(), [
            'avatar' => 'required',
            'name' => 'required',
            'type' => 'required',
            'email' => 'required|max:50|email|unique:users,email', // Vérifie que l'email est unique
        ]);

        if ($validator->fails()) {
            return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
        }

        try {
            $validated = $validator->validated();

            // Générer un open_id unique
            $validated["open_id"] = md5(uniqid() . rand(10000, 99999));

            // Ajouter d'autres champs nécessaires
            $validated["token"] = md5(uniqid() . rand(10000, 99999));
            $validated["created_at"] = Carbon::now();
            $validated["expire_date"] = Carbon::now()->addDays(30);

            // Insérer l'utilisateur dans la base de données
            $user_id = DB::table("users")->insertGetId($validated);

            // Récupérer les informations de l'utilisateur créé
            $user_res = DB::table("users")->where("id", "=", $user_id)->first();

            return ["code" => 0, "data" => $user_res, "msg" => "success"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    public function upload_image_admin(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Exemple de validation pour le fichier image

            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer le fichier envoyé dans la requête
            $file = $request->file('file');

            // Récupérer les informations utilisateur


            // Générer un nom unique pour le fichier
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Déplacer le fichier vers le dossier de stockage
            $file->move(public_path('uploads'), $fileName);

            // Maintenant, vous pouvez mettre à jour les informations de l'utilisateur dans votre base de données
            // Exemple: mise à jour du nom d'utilisateur et du token

            // Retourner une réponse avec les détails du fichier téléchargé
            return ["code" => 0, "data" => ['file_name' => $fileName], "msg" => "File uploaded successfully"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    function calculateProportionalScore($gamers, $score, $cashPrise) {
        $totalScore = $gamers->sum('score');
        $numberOfGamers = $gamers->count();

        if ($numberOfGamers == 0 || $totalScore == 0) {
            return 0;
        }

        $proportionalScore = ($score / $totalScore) * $cashPrise;

        return round($proportionalScore, 1);
    }

    public function pay_gamers(Request $request)
    {
        // Validator pour vérifier les données d'entrée
        $validator = Validator::make($request->all(), [
            'gameId' => 'required|integer',
            'token' => 'required|string',
        ]);

        // Si la validation échoue, retourner les erreurs
        if ($validator->fails()) {
            return ['code' => -1 , 'message' => $validator->errors()->all()];
        }

        $gameId = $request->input("gameId");
        $adminToken = $request->input("token");

        // Vérifier si l'administrateur (utilisateur) existe dans la table admin
        $adminExists = DB::table('admin')
            ->where('token', $adminToken)
            ->exists();

        // Si l'administrateur n'existe pas, retourner une erreur 401 (Non autorisé)
        if (!$adminExists) {
            return ['code' => -1 ,'message' => 'Token administrateur invalide.'];
        }

        // Vérifier si le jeu existe dans la table game et obtenir ses informations
        $game = DB::table('game')
            ->where('id', $gameId)
            ->first(['cashPrise', 'premium']);

        // Si le jeu n'existe pas, retourner une erreur 404 (Non trouvé)
        if (!$game) {
            return ['code' => -1 , 'message' => 'Le jeu n\'existe pas dans la base de données.'];
        }

        // Vérifier si les joueurs ont déjà été payés pour ce jeu
        $gameAlreadyPaid = DB::table('payement_game')
            ->where('gameId', $gameId)
            ->exists();

        // Si les joueurs ont déjà été payés, retourner une erreur 400 (Demande incorrecte)
        if ($gameAlreadyPaid) {
            return [ 'code' => -2,'message' => 'Les joueurs ont déjà été payés pour ce jeu.'];
        }

        // Sélectionner les 100 meilleurs joueurs classés par ordre de score
        $gamers = DB::table('gamers')
            ->where('gameId', $gameId)
            ->orderByDesc('score')
            ->take(100)
            ->get();

        // Vérifier s'il y a des joueurs à payer
        if ($gamers->isEmpty()) {
            return ['code' => -1,'message' => 'Il n\'y a pas de joueurs à payer pour ce jeu.'];
        }

        $cashPrise = $game->cashPrise;
        $isPremium = $game->premium;

        // Distribuer les montants aux joueurs
        foreach ($gamers as $gamer) {
            $proportionalScore = $this->calculateProportionalScore($gamers, $gamer->score, $cashPrise);

            // Insérer les montants dans la table payement_game
            DB::table('payement_game')->insert([
                'gameId' => $gameId,
                'userToken' => $gamer->userToken,
                'amount' => $proportionalScore
            ]);

            // Mettre à jour le solde dans le portefeuille (wallet) de l'utilisateur
            $wallet = DB::table('wallet')
                ->where('userToken', $gamer->userToken)
                ->first();

            if ($wallet) {
                $newMoney = $wallet->money + $proportionalScore;
                if ($isPremium) {
                    $newMoney += $proportionalScore; // doubler le montant si premium
                }
                if ($newMoney > 999) {
                    $newMoney = 999;
                }
                DB::table('wallet')
                    ->where('userToken', $gamer->userToken)
                    ->update(['money' => $newMoney]);
            }
        }

        // Sélectionner les paiements effectués pour ce jeu
        $data = DB::table('payement_game')
            ->where('gameId', $gameId)
            ->take(100)
            ->get();

        $totalScore = $gamers->sum('score');
        $numberOfGamers = $gamers->count();

        // Retourner les données avec un code 200 (OK)
        return [
            'code' => 0,
            'cashPrise' => $cashPrise,
            'scoreTotale' => $totalScore,
            'numberOfGamers' => $numberOfGamers,
            'data' => $data
        ];
    }

    public function get_cash_out(): array
    {
        try {
            // Récupérer toutes les entrées de la table payment_out et les trier par ID décroissant
            $cashOuts = DB::table('payement_out')->where('isvalidate', false)->orderBy('id', 'desc')->get();

            return ["code" => 0, "data" => $cashOuts, "msg" => "Liste des cash out récupérée avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }

    public function get_pay_all(Request $request): array
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
                ->where('isvalidate', true)
                ->get();

            // Récupérer les paiements de payment_game pour l'utilisateur donné
            $paymentsGame = DB::table('payement_game')
                ->where('userToken', $userToken)
                ->get();

            return [
                "code" => 0,
                "data" => [
                    "payment_out" => $paymentsOut,
                    "payment_game" => $paymentsGame,
                ],
                "msg" => "Données de paiement récupérées avec succès pour l'utilisateur $userToken"
            ];
        } catch (Exception $e) {
            return ["code" => -1, "data" => [], "msg" => $e->getMessage()];
        }
    }
    public function validate_pay_out(Request $request): array
    {
        try {
            // Récupérer l'ID du paiement à valider depuis la requête
            $paymentId = $request->input('id');

            // Vérifier si le paiement existe et s'il n'est pas déjà validé
            $payment = DB::table('payement_out')->where('id', $paymentId)->where('isvalidate', false)->first();

            if (!$payment) {
                return ["code" => -1, "data" => "", "msg" => "Paiement sortant non trouvé ou déjà validé"];
            }

            // Mettre à jour le paiement pour le marquer comme validé
            DB::table('payement_out')
                ->where('id', $paymentId)
                ->update([
                    'isvalidate' => true,
                    'updated_at' => now() // Met à jour updated_at avec la date et l'heure actuelles
                ]);


            return ["code" => 0, "data" => $paymentId, "msg" => "Paiement sortant validé avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }
    public function ban_user(Request $request): array
    {
        try {
            // Récupérer le token de l'utilisateur depuis la requête
            $token = $request->input('token');

            // Vérifier si l'utilisateur existe dans la table users
            $user = DB::table('users')->where('token', $token)->first();

            if (!$user) {
                return ["code" => -1, "data" => "", "msg" => "Utilisateur non trouvé"];
            }

            // Transférer l'utilisateur à la table ban
            DB::table('ban')->insert([
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'avatar' => $user->avatar,
                'type' => $user->type,
                'open_id' => $user->open_id,
                'token' => $user->token,
                'expire_date' => $user->expire_date,
            ]);

            // Supprimer l'utilisateur de la table users
            DB::table('users')->where('token', $token)->delete();

            // Supprimer l'utilisateur de la table wallet
            DB::table('wallet')->where('userToken', $token)->delete();

            // Supprimer l'utilisateur de la table gamers
            DB::table('gamers')->where('userToken', $token)->delete();

            // Supprimer l'utilisateur de la table payement_game
            DB::table('payement_game')->where('userToken', $token)->delete();

            // Supprimer l'utilisateur de la table payement_out
            DB::table('payement_out')->where('userToken', $token)->delete();
            DB::table('rate')->where('userToken', $token)->delete();

            return ["code" => 0, "data" => $user->id, "msg" => "Utilisateur banni avec succès"];
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    public function set_or_edit_update_status(Request $request): array
    {
        try {
            // Validation des paramètres de la requête
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'version' => 'required|string',
                'updateAvailable' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
            }

            // Récupérer les données de la requête
            $adminToken = $request->input('token');
            $version = $request->input('version');
            $updateAvailable = $request->input('updateAvailable');

            // Vérifier si l'administrateur est authentifié
            $admin = DB::table('admin')->where('token', $adminToken)->first();
            if (!$admin) {
                return ["code" => -1, "data" => "", "msg" => "Administrateur non authentifié"];
            }

            // Vérifier si la version existe déjà dans la table mise_a_jour
            $existingUpdate = DB::table('mise_a_jour')->where('version', $version)->first();

            if ($existingUpdate) {
                // Mettre à jour l'état de la mise à jour
                DB::table('mise_a_jour')
                    ->where('version', $version)
                    ->update(['updateAvailable' => $updateAvailable]);

                return ["code" => 0, "data" => "", "msg" => "Mise à jour modifiée avec succès"];
            } else {
                // Insérer une nouvelle mise à jour
                DB::table('mise_a_jour')->insert([
                    'version' => $version,
                    'updateAvailable' => $updateAvailable,
                ]);

                return ["code" => 0, "data" => "", "msg" => "Mise à jour définie avec succès"];
            }
        } catch (Exception $e) {
            return ["code" => -1, "data" => "", "msg" => $e->getMessage()];
        }
    }


    // Fonction pour calculer le score proportionnel


// Fonction pour calculer le score proportionnel



}
