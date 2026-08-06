# Guide — 06 : Dashboard livreur

**Périmètre :** `D:\AlloDelevry\backend`
**Entrées (routes/canaux) :**
- `GET /api/dashboard` — tableau de bord du livreur connecté (auth:sanctum + role:driver)

**Référence cahier des charges :** F16 (tableau de bord, P0/P1), O8 (statistiques simples), §8.2 (revenus estimés)

---

## Vue d'ensemble

Le tableau de bord du livreur est un **endpoint unique** (`GET /api/dashboard`) qui agrège plusieurs indicateurs métier pour donner une vue d'ensemble rapide de son activité. L'endpoint est protégé par l'authentification Sanctum et le middleware `role:driver` — un client qui tente d'y accéder reçoit un **403 Forbidden**.

L'agrégation se fait directement en base via des requêtes Eloquent optimisées, sans passer par un service externe ni un cache. Chaque indicateur est calculé sur les données du livreur connecté uniquement (`driver_id = user.id`), conformément au RG01 (isolation des données).

**Indicateurs renvoyés :** demandes totales, missions en cours, missions en attente, missions livrées, CA estimé, CA encaissé, note moyenne, notifications non lues, 5 dernières demandes, 5 derniers messages du chat.

---

## Fichiers et rôles (exhaustif)

### Contrôleur principal

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Controllers/Api/DashboardController.php` | Endpoint unique `GET /api/dashboard` | Méthode `index()` : calcule 10 indicateurs scoped sur `$driverId` ; retourne via `$this->success(data: new DashboardResource([...]))` |

### Détail des indicateurs calculés dans `DashboardController::index()`

| Indicateur | Calcul | Détail |
|------------|--------|--------|
| `total_requests` | `DeliveryRequest::where('driver_id', $driverId)->count()` | Nombre total de demandes du livreur |
| `active_missions` | `DeliveryRequest::where('driver_id', $driverId)->active()->count()` | Statuts non terminaux (scope `active()` exclut livree/refusee/echec/annulee) |
| `pending_requests` | `->where('status', STATUS_EN_ATTENTE)->count()` | Demandes en attente d'une action du livreur |
| `delivered_missions` | `->where('status', STATUS_LIVREE)->count()` | Missions terminées avec succès |
| `estimated_revenue` | `->whereIn('status', [confirmee, colis_recupere, en_livraison, livree])->sum('proposed_price')` | CA des missions engagées ou terminées, formaté `"200.00"` via `number_format` |
| `collected_revenue` | `->where('status', STATUS_LIVREE)->sum('proposed_price')` | CA uniquement des livraisons terminées, formaté `"150.00"` |
| `average_rating` | `Review::whereHas('deliveryRequest', fn($q) => $q->where('driver_id', ...))->avg('rating')` | Note moyenne arrondie à 0,1 (`round(..., 1)`), null si aucun avis |
| `unread_notifications` | `Notification::where('user_id', $driverId)->whereNull('read_at')->count()` | Nombre de notifications non lues |
| `recent_requests` | `->latest()->limit(5)->get(['id', 'tracking_number', 'status', 'proposed_price', 'created_at'])` | 5 dernières demandes (colonnes sélectionnées) |
| `recent_messages` | `ChatMessage::whereHas(...)->with('sender:id,name')->latest()->limit(5)->get()` | 5 derniers messages du chat (id, sender_name, content, created_at) — transformés via `->map()` |

### Resource (API)

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Http/Resources/DashboardResource.php` | Formatage de la réponse API | Passthrough (`return $this->resource`) — renvoie le tableau d'indicateurs tel quel |

### Modèles utilisés

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `app/Models/DeliveryRequest.php` (scope `active()`) | Filtre les statuts non terminaux | Scope : `whereNotIn('status', [livree, refusee, echec, annulee])` — utilisé pour `active_missions` ; constantes de statut : `STATUS_EN_ATTENTE`, `STATUS_PRIX_PROPOSE`, `STATUS_CONFIRMEE`, `STATUS_COLIS_RECUPERE`, `STATUS_EN_LIVRAISON`, `STATUS_LIVREE`, `STATUS_REFUSEE`, `STATUS_ECHEC`, `STATUS_ANNULEE` |
| `app/Models/DeliveryRequest.php` (relations) | Relations utilisées par le dashboard | `belongsTo(User, 'client_id')`, `belongsTo(User, 'driver_id')`, `hasOne(Review)`, `hasMany(ChatMessage)`, `hasMany(Notification)` |
| `app/Models/DriverProfile.php` | Modèle du profil livreur (utilisé indirectement) | Champs : user_id, brand_name, slug, logo_path, city, rib, is_available ; cast `is_available` → boolean |
| `app/Http/Controllers/Api/DriverProfileController.php` | CRUD profil + endpoints publics | `apiResource driver-profiles` (groupe `role:driver`) ; **`showPublic(slug)`** et **`qrCode(slug)`** publiques (`throttle:60,1`) pour la fiche publique et le QR code (guide 03) |
| `app/Http/Requests/StoreDriverProfileRequest.php` / `UpdateDriverProfileRequest.php` | Validation du profil | `brand_name`/`slug` requis (Store) ; `logo_path` nullable ; `city`/`rib` nullable ; `is_available` boolean — validation clés du profil public |
| `app/Http/Resources/DriverProfileResource.php` | Sérialisation du profil | Version publique **allégée** (pas de téléphone/RIB — correction B1) ; la version complète reste côté owner |
| `database/migrations/2026_07_22_141302_create_driver_profiles_table.php` | Table profils | `user_id` (unique, cascade), `brand_name`, `slug` (unique), `logo_path`, `city`, `rib`, `is_available` |
| `app/Models/Notification.php` | Comptage des notifications non lues | Utilisé via `Notification::where('user_id', ...)->whereNull('read_at')->count()` |

### Routes

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `routes/api.php` (ligne 34) | Route du dashboard | `GET /dashboard` dans le groupe `middleware(['auth:sanctum', 'role:driver'])` — accessible uniquement aux utilisateurs avec le rôle `driver` |

### Tests

| Fichier | Rôle dans la feature | Points clés |
|---------|----------------------|-------------|
| `tests/Feature/DashboardTest.php` | Tests du tableau de bord | 3 tests Pest : (1) **indicateurs exacts** — crée 6 demandes (2 livrées à 100/50, 1 confirmée à 30, 1 colis récupéré à 20, 1 en attente, 1 refusée) + 2 notifs non lues + 1 lue + 1 avis 4/5 + 1 message → vérifie toutes les valeurs (total=6, active=3, pending=1, delivered=2, estimated=200.00, collected=150.00, rating=4, unread=2, 5 recent requests, message content="Bonjour") ; (2) client → 403 ; (3) non authentifié → 401 |

### Helpers de test (dans `DashboardTest.php`)

| Fonction | Rôle | Détail |
|----------|------|--------|
| `dashboardDriver()` | Crée un livreur avec profil | Factory User::driver() + DriverProfile avec slug `dashboard-driver-slug` |
| `dashboardMission($driver, $status, $price)` | Crée une demande pour le livreur | Factory DeliveryRequest::forDriver() avec statut et prix |

---

## Actions passées (rapports liés)

- **[rapport_ar39_dashboard.md](../rapport/rapport_ar39_dashboard.md)** — AR-39 : implémentation du tableau de bord livreur. Détail des indicateurs, tests (3), commit `e316371`.

---

## Pièges et points d'attention

1. **Middleware `role:driver`** : La route est protégée par `role:driver`. Un client qui appelle `GET /api/dashboard` reçoit un **403 Forbidden**. Ce middleware doit être vérifié côté frontend (masquer le dashboard si l'utilisateur n'est pas driver).

2. **`number_format` pour les revenus** : Le CA est retourné en string formatée (`"200.00"`, pas `200.00`). Le frontend doit parser la valeur si besoin de calculs.

3. **`round` pour la note moyenne** : La note est arrondie à 0,1 (`round($averageRating, 1)`) et retournée en float. Si aucun avis, la valeur est `null` (pas `0`).

4. **Scope `active()`** : Les missions en cours excluent les statuts terminaux (livree, refusee, echec, annulee). Les statuts inclus sont : en_attente, prix_propose, confirmee, colis_recupere, en_livraison.

5. **Pas de cache** : Les indicateurs sont calculés à chaque appel. Pour un dashboard à forte volumétrie, un cache Redis pourrait être ajouté (non prévu actuellement).

6. **Chat recent** : Les 5 derniers messages sont chargés avec `with('sender:id,name')` pour éviter le N+1. Les messages sont transformés en tableau personnalisé (pas de `ChatMessageResource`).

7. **Comment tester manuellement** :
   - Authentification : `curl -H "Authorization: Bearer {token}" GET /api/dashboard`
   - Vérifier le rôle : l'utilisateur doit avoir `role = driver`
   - `php artisan test --compact --filter=DashboardTest`
